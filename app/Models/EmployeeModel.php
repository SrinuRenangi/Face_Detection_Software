<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeModel extends Model
{
    protected $table            = 'employees';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'employee_id',
        'full_name',
        'gender',
        'department',
        'designation',
        'joining_date',
        'contact_phone',
        'status',
        'pin_hash',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Generates an RFC 4122 compliant UUID v4 string
     */
    public static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Count total active employees
     */
    public function countActive(): int
    {
        return $this->where('status', 'ACTIVE')->countAllResults();
    }

    /**
     * Get aggregate counts: total, active, inactive, face registered
     */
    public function getCountsSummary(): array
    {
        $total = $this->countAllResults(false);
        $active = $this->where('status', 'ACTIVE')->countAllResults(false);
        $inactive = $this->where('status', 'INACTIVE')->countAllResults(false);

        $faceRegistered = $this->db->table('employee_face_templates eft')
            ->join('employees e', 'e.id = eft.employee_id')
            ->where('e.status', 'ACTIVE')
            ->countAllResults();

        return [
            'total'          => $total,
            'active'         => $active,
            'inactive'       => $inactive,
            'faceRegistered' => $faceRegistered,
        ];
    }

    /**
     * Find employee by Employee ID (e.g. EMP-001)
     */
    public function findByEmployeeId(string $employeeId): ?array
    {
        return $this->where('employee_id', $employeeId)->first();
    }

    /**
     * Find single employee by UUID with joined biometric face registration status
     */
    public function findByIdWithBiometrics(string $id): ?array
    {
        $builder = $this->db->table('employees e')
            ->select("
                e.*,
                eft.id as face_template_id,
                eft.template_version,
                eft.quality_score,
                eft.registered_by as face_registered_by,
                eft.created_at as face_registered_at,
                CASE
                    WHEN e.status = 'INACTIVE' AND eft.id IS NOT NULL THEN 'DISABLED'
                    WHEN eft.id IS NOT NULL THEN 'REGISTERED'
                    ELSE 'NOT_REGISTERED'
                END as face_status
            ")
            ->join('employee_face_templates eft', 'eft.employee_id = e.id', 'left')
            ->where('e.id', $id);

        $result = $builder->get()->getRowArray();
        return $result ?: null;
    }

    /**
     * Fetch paginated employees with computed biometric registration states
     */
    public function getWithBiometricStatus(?string $search = null, ?string $status = null, ?string $department = null, int $limit = 20, int $offset = 0): array
    {
        $builder = $this->db->table('employees e')
            ->select("
                e.id,
                e.employee_id,
                e.full_name,
                e.gender,
                e.department,
                e.designation,
                e.joining_date,
                e.contact_phone,
                e.status,
                e.created_at,
                e.updated_at,
                eft.id as face_template_id,
                eft.template_version,
                eft.quality_score,
                CASE
                    WHEN e.status = 'INACTIVE' AND eft.id IS NOT NULL THEN 'DISABLED'
                    WHEN eft.id IS NOT NULL THEN 'REGISTERED'
                    ELSE 'NOT_REGISTERED'
                END as face_status
            ")
            ->join('employee_face_templates eft', 'eft.employee_id = e.id', 'left');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('e.employee_id', $search)
                ->orLike('e.full_name', $search)
                ->groupEnd();
        }

        if (!empty($status) && in_array(strtoupper($status), ['ACTIVE', 'INACTIVE'], true)) {
            $builder->where('e.status', strtoupper($status));
        }

        if (!empty($department)) {
            $builder->where('e.department', $department);
        }

        return $builder->orderBy('e.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Count total employees matching given filters
     */
    public function countFiltered(?string $search = null, ?string $status = null, ?string $department = null): int
    {
        $builder = $this->db->table('employees e');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('e.employee_id', $search)
                ->orLike('e.full_name', $search)
                ->groupEnd();
        }

        if (!empty($status) && in_array(strtoupper($status), ['ACTIVE', 'INACTIVE'], true)) {
            $builder->where('e.status', strtoupper($status));
        }

        if (!empty($department)) {
            $builder->where('e.department', $department);
        }

        return $builder->countAllResults();
    }

    /**
     * Get distinct department list for filtering dropdowns
     */
    public function getDistinctDepartments(): array
    {
        $rows = $this->select('department')
            ->distinct()
            ->where('department IS NOT NULL')
            ->where("department != ''")
            ->orderBy('department', 'ASC')
            ->findAll();

        return array_column($rows, 'department');
    }

    /**
     * Check if an employee has historical attendance records
     */
    public function hasAttendanceRecords(string $id): bool
    {
        return $this->db->table('attendance_records')
            ->where('employee_id', $id)
            ->countAllResults() > 0;
    }

    /**
     * Check if an employee has registered biometric templates
     */
    public function hasFaceTemplates(string $id): bool
    {
        return $this->db->table('employee_face_templates')
            ->where('employee_id', $id)
            ->countAllResults() > 0;
    }

    /**
     * Verifies an employee's PIN against their stored bcrypt hash for API integration compatibility.
     */
    public function verifyEmployeePin(string $employeeIdOrCode, string $rawPin): ?array
    {
        if (!preg_match('/^\d{6}$/', $rawPin)) {
            return null;
        }

        $employee = $this->where('employee_id', $employeeIdOrCode)
            ->orWhere('id', $employeeIdOrCode)
            ->first();

        if (!$employee || $employee['status'] !== 'ACTIVE' || empty($employee['pin_hash'])) {
            return null;
        }

        if (!password_verify($rawPin, $employee['pin_hash'])) {
            return null;
        }

        unset($employee['pin_hash']);
        return $employee;
    }
}
