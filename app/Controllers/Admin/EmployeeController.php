<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\AdminAuditLogModel;
use CodeIgniter\HTTP\ResponseInterface;

class EmployeeController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected AdminAuditLogModel $auditModel;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->auditModel    = new AdminAuditLogModel();
    }

    /**
     * Helper to verify administrative permissions
     */
    protected function requireAdminRole(): bool
    {
        $role = session()->get('admin_role');
        return $role === 'ROLE_ADMIN';
    }

    /**
     * Employee Directory Listing (Accessible by ROLE_ADMIN and ROLE_MANAGER)
     */
    public function index()
    {
        $search     = trim((string) $this->request->getGet('search'));
        $status     = trim((string) $this->request->getGet('status'));
        $department = trim((string) $this->request->getGet('department'));

        $page    = max(1, (int) $this->request->getGet('page'));
        $perPage = 15;
        $offset  = ($page - 1) * $perPage;

        $totalFiltered = $this->employeeModel->countFiltered($search, $status, $department);
        $employees     = $this->employeeModel->getWithBiometricStatus($search, $status, $department, $perPage, $offset);
        $counts        = $this->employeeModel->getCountsSummary();
        $departments   = $this->employeeModel->getDistinctDepartments();

        $totalPages = max(1, (int) ceil($totalFiltered / $perPage));

        return view('admin/employees/index', [
            'pageTitle'     => 'Staff Directory & Employee Management',
            'employees'     => $employees,
            'counts'        => $counts,
            'departments'   => $departments,
            'search'        => $search,
            'status'        => $status,
            'department'    => $department,
            'currentPage'   => $page,
            'totalPages'    => $totalPages,
            'totalFiltered' => $totalFiltered,
            'userRole'      => session()->get('admin_role'),
            'adminFullName' => session()->get('admin_full_name'),
        ]);
    }

    /**
     * Store a newly created employee (ADMIN only, CSRF protected)
     */
    public function store()
    {
        if (!$this->requireAdminRole()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'status'  => 'error',
                    'code'    => 'FORBIDDEN',
                    'message' => 'Access denied. Manager role does not have permission to add employees.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Access denied. Only Administrators can add employees.');
        }

        $rules = [
            'employee_id'   => 'required|min_length[2]|max_length[32]|regex_match[/^[A-Za-z0-9\-_]+$/]|is_unique[employees.employee_id]',
            'full_name'     => 'required|min_length[2]|max_length[128]|regex_match[/^[A-Za-z0-9\s\.\'\-]+$/]',
            'department'    => 'permit_empty|max_length[64]',
            'designation'   => 'permit_empty|max_length[64]',
            'joining_date'  => 'permit_empty|valid_date[Y-m-d]',
            'contact_phone' => 'permit_empty|max_length[32]|regex_match[/^[0-9\+\-\s\(\)]+$/]',
            'status'        => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ];

        $messages = [
            'employee_id' => [
                'is_unique' => 'Employee ID already exists. Please choose a unique Employee ID.',
                'regex_match' => 'Employee ID may only contain alphanumeric characters, dashes, and underscores.',
            ],
            'full_name' => [
                'regex_match' => 'Full Name contains invalid characters.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status' => 'error',
                    'errors' => $this->validator->getErrors(),
                ]);
            }
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $id = EmployeeModel::generateUuidV4();
        $employeeId = strtoupper(trim((string) $this->request->getPost('employee_id')));
        $fullName   = trim((string) $this->request->getPost('full_name'));
        $department = trim((string) $this->request->getPost('department')) ?: 'General';
        $designation = trim((string) $this->request->getPost('designation')) ?: 'Staff';
        $joiningDate = trim((string) $this->request->getPost('joining_date')) ?: null;
        $contactPhone = trim((string) $this->request->getPost('contact_phone')) ?: null;
        $status     = strtoupper(trim((string) $this->request->getPost('status'))) ?: 'ACTIVE';

        $data = [
            'id'            => $id,
            'employee_id'   => $employeeId,
            'full_name'     => $fullName,
            'department'    => $department,
            'designation'   => $designation,
            'joining_date'  => $joiningDate,
            'contact_phone' => $contactPhone,
            'status'        => $status,
            'pin_hash'      => null,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $this->employeeModel->insert($data);

        // Audit Log
        $actor = session()->get('admin_username') ?: 'admin';
        $this->auditModel->log(
            $actor,
            'EMPLOYEE_CREATED',
            json_encode([
                'employee_id' => $employeeId,
                'full_name'   => $fullName,
                'department'  => $department,
                'designation' => $designation,
                'status'      => $status,
            ]),
            'EMPLOYEE',
            $id
        );

        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode(201)->setJSON([
                'status'   => 'success',
                'message'  => "Employee {$fullName} ({$employeeId}) created successfully.",
                'employee' => $data,
            ]);
        }

        return redirect()->to('/admin/employees')->with('success', "Employee {$fullName} ({$employeeId}) created successfully.");
    }

    /**
     * Update existing employee details (ADMIN only, CSRF protected)
     */
    public function update(string $id)
    {
        if (!$this->requireAdminRole()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'status'  => 'error',
                    'code'    => 'FORBIDDEN',
                    'message' => 'Access denied. Only Administrators can update employee records.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Access denied. Only Administrators can update employee records.');
        }

        $existing = $this->employeeModel->find($id);
        if (!$existing) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Employee record not found.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Employee record not found.');
        }

        $rules = [
            'employee_id'   => "required|min_length[2]|max_length[32]|regex_match[/^[A-Za-z0-9\-_]+$/]|is_unique[employees.employee_id,id,{$id}]",
            'full_name'     => 'required|min_length[2]|max_length[128]|regex_match[/^[A-Za-z0-9\s\.\'\-]+$/]',
            'department'    => 'permit_empty|max_length[64]',
            'designation'   => 'permit_empty|max_length[64]',
            'joining_date'  => 'permit_empty|valid_date[Y-m-d]',
            'contact_phone' => 'permit_empty|max_length[32]|regex_match[/^[0-9\+\-\s\(\)]+$/]',
            'status'        => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ];

        if (!$this->validate($rules)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status' => 'error',
                    'errors' => $this->validator->getErrors(),
                ]);
            }
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $newEmployeeId   = strtoupper(trim((string) $this->request->getPost('employee_id')));
        $newFullName     = trim((string) $this->request->getPost('full_name'));
        $newDepartment   = trim((string) $this->request->getPost('department')) ?: 'General';
        $newDesignation  = trim((string) $this->request->getPost('designation')) ?: 'Staff';
        $newJoiningDate  = trim((string) $this->request->getPost('joining_date')) ?: null;
        $newContactPhone = trim((string) $this->request->getPost('contact_phone')) ?: null;
        $newStatus       = strtoupper(trim((string) $this->request->getPost('status'))) ?: $existing['status'];

        // Compute before/after diff for audit trail
        $diff = [];
        if ($existing['employee_id'] !== $newEmployeeId)   $diff['employee_id']   = ['from' => $existing['employee_id'],   'to' => $newEmployeeId];
        if ($existing['full_name'] !== $newFullName)       $diff['full_name']     = ['from' => $existing['full_name'],     'to' => $newFullName];
        if ($existing['department'] !== $newDepartment)     $diff['department']    = ['from' => $existing['department'],    'to' => $newDepartment];
        if (($existing['designation'] ?? '') !== $newDesignation) $diff['designation'] = ['from' => $existing['designation'] ?? '', 'to' => $newDesignation];
        if (($existing['joining_date'] ?? null) !== $newJoiningDate) $diff['joining_date'] = ['from' => $existing['joining_date'] ?? null, 'to' => $newJoiningDate];
        if (($existing['contact_phone'] ?? null) !== $newContactPhone) $diff['contact_phone'] = ['from' => $existing['contact_phone'] ?? null, 'to' => $newContactPhone];
        if ($existing['status'] !== $newStatus)             $diff['status']        = ['from' => $existing['status'],        'to' => $newStatus];

        $updateData = [
            'employee_id'   => $newEmployeeId,
            'full_name'     => $newFullName,
            'department'    => $newDepartment,
            'designation'   => $newDesignation,
            'joining_date'  => $newJoiningDate,
            'contact_phone' => $newContactPhone,
            'status'        => $newStatus,
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $this->employeeModel->update($id, $updateData);

        // Audit Log
        if (!empty($diff)) {
            $actor = session()->get('admin_username') ?: 'admin';
            $this->auditModel->log(
                $actor,
                'EMPLOYEE_UPDATED',
                json_encode([
                    'employee_id' => $newEmployeeId,
                    'changes'     => $diff,
                ]),
                'EMPLOYEE',
                $id
            );
        }

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => "Employee {$newFullName} updated successfully.",
            ]);
        }

        return redirect()->to('/admin/employees')->with('success', "Employee {$newFullName} updated successfully.");
    }

    /**
     * Toggle ACTIVE / INACTIVE status (Non-destructive, preserves attendance history)
     */
    public function toggleStatus(string $id)
    {
        if (!$this->requireAdminRole()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'status'  => 'error',
                    'code'    => 'FORBIDDEN',
                    'message' => 'Access denied. Only Administrators can change employee status.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Access denied. Only Administrators can change employee status.');
        }

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Employee not found.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Employee not found.');
        }

        $newStatus = ($employee['status'] === 'ACTIVE') ? 'INACTIVE' : 'ACTIVE';
        $action = ($newStatus === 'ACTIVE') ? 'EMPLOYEE_ACTIVATED' : 'EMPLOYEE_DEACTIVATED';

        $this->employeeModel->update($id, [
            'status'     => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $actor = session()->get('admin_username') ?: 'admin';
        $this->auditModel->log(
            $actor,
            $action,
            json_encode([
                'employee_id' => $employee['employee_id'],
                'full_name'   => $employee['full_name'],
                'from_status' => $employee['status'],
                'to_status'   => $newStatus,
            ]),
            'EMPLOYEE',
            $id
        );

        $statusLabel = ($newStatus === 'ACTIVE') ? 'activated' : 'deactivated';
        $message = "Employee {$employee['full_name']} ({$employee['employee_id']}) has been successfully {$statusLabel}. Historical attendance records remain fully preserved.";

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'    => 'success',
                'message'   => $message,
                'newStatus' => $newStatus,
            ]);
        }

        return redirect()->to('/admin/employees')->with('success', $message);
    }

    /**
     * Deletion safeguard (Hard deletion prevented if attendance or biometric records exist)
     */
    public function delete(string $id)
    {
        if (!$this->requireAdminRole()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'code'    => 'FORBIDDEN',
                'message' => 'Access denied. Only Administrators can perform deletion actions.',
            ]);
        }

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Employee not found.',
            ]);
        }

        // Safeguard 1: Prevent deletion if historical attendance exists
        if ($this->employeeModel->hasAttendanceRecords($id)) {
            return $this->response->setStatusCode(409)->setJSON([
                'status'  => 'error',
                'code'    => 'ATTENDANCE_HISTORY_EXISTS',
                'message' => "Cannot delete employee {$employee['full_name']}. Historical attendance records exist in the database. Please deactivate the employee instead to maintain audit compliance.",
            ]);
        }

        // Safeguard 2: Prevent deletion if biometric template exists
        if ($this->employeeModel->hasFaceTemplates($id)) {
            return $this->response->setStatusCode(409)->setJSON([
                'status'  => 'error',
                'code'    => 'BIOMETRIC_TEMPLATE_EXISTS',
                'message' => "Cannot delete employee {$employee['full_name']}. Registered biometric face template exists. Please remove template or deactivate the employee instead.",
            ]);
        }

        // Safe hard delete only when zero history exists
        $this->employeeModel->delete($id);

        $actor = session()->get('admin_username') ?: 'admin';
        $this->auditModel->log(
            $actor,
            'EMPLOYEE_HARD_DELETED',
            json_encode([
                'employee_id' => $employee['employee_id'],
                'full_name'   => $employee['full_name'],
            ]),
            'EMPLOYEE',
            $id
        );

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => 'success',
                'message' => "Employee {$employee['full_name']} deleted successfully.",
            ]);
        }

        return redirect()->to('/admin/employees')->with('success', "Employee {$employee['full_name']} deleted successfully.");
    }

    /**
     * Single employee detail query (AJAX API)
     */
    public function show(string $id)
    {
        $employee = $this->employeeModel->findByIdWithBiometrics($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Employee not found.',
            ]);
        }

        return $this->response->setJSON([
            'status'   => 'success',
            'employee' => $employee,
        ]);
    }

    /**
     * Assign or Reset 6-digit numeric employee PIN (ADMIN only, CSRF protected)
     * Stores only bcrypt hash. Never logs or echoes raw PIN values.
     */
    public function setPin(string $id)
    {
        if (!$this->requireAdminRole()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(403)->setJSON([
                    'status'  => 'error',
                    'code'    => 'FORBIDDEN',
                    'message' => 'Access denied. Only Administrators can manage employee PINs.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Access denied. Only Administrators can manage employee PINs.');
        }

        $existing = $this->employeeModel->find($id);
        if (!$existing) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(404)->setJSON([
                    'status'  => 'error',
                    'message' => 'Employee record not found.',
                ]);
            }
            return redirect()->to('/admin/employees')->with('error', 'Employee record not found.');
        }

        $rules = [
            'pin'         => 'required|exact_length[6]|numeric',
            'pin_confirm' => 'required|matches[pin]',
        ];

        $messages = [
            'pin' => [
                'required'     => 'A six-digit numeric PIN is required.',
                'exact_length' => 'PIN must be exactly 6 digits.',
                'numeric'      => 'PIN must contain only numbers (0-9).',
            ],
            'pin_confirm' => [
                'required' => 'PIN confirmation is required.',
                'matches'  => 'PIN confirmation does not match the entered PIN.',
            ],
        ];

        if (!$this->validate($rules, $messages)) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status' => 'error',
                    'errors' => $this->validator->getErrors(),
                ]);
            }
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $rawPin = (string) $this->request->getPost('pin');
        $isReset = !empty($existing['pin_hash']);
        $action = $isReset ? 'EMPLOYEE_PIN_RESET' : 'EMPLOYEE_PIN_ASSIGNED';

        $this->employeeModel->setEmployeePin($id, $rawPin);

        $actor = session()->get('admin_username') ?: 'admin';
        $this->auditModel->log(
            $actor,
            $action,
            json_encode([
                'employee_id' => $existing['employee_id'],
                'full_name'   => $existing['full_name'],
                'action'      => $action,
            ]),
            'EMPLOYEE',
            $id
        );

        $verb = $isReset ? 'reset' : 'assigned';
        $message = "Six-digit PIN successfully {$verb} for employee {$existing['full_name']} ({$existing['employee_id']}).";

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'         => 'success',
                'message'        => $message,
                'pin_configured' => true,
            ]);
        }

        return redirect()->to('/admin/employees')->with('success', $message);
    }

    /**
     * Enrolls or updates 128-dimensional facial vector template for an employee
     */
    public function enrollFace(string $id)
    {
        if (!$this->requireAdminRole()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'message' => 'Access denied. Only Administrators can enroll face templates.',
            ]);
        }

        $employee = $this->employeeModel->find($id);
        if (!$employee) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Employee not found.',
            ]);
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $embedding = $json['embedding'] ?? null;
        if (!is_array($embedding) || count($embedding) !== 128) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid facial embedding. A 128-dimensional vector is required.',
            ]);
        }

        $qualityScore = (float) ($json['quality_score'] ?? 0.95);
        $modelName = trim((string) ($json['model_name'] ?? 'face-api-resnet34'));

        $templateModel = new \App\Models\EmployeeFaceTemplateModel();
        
        // Remove prior templates for this employee to maintain single authoritative template
        $db = \Config\Database::connect();
        $db->table('employee_face_templates')->where('employee_id', $id)->delete();

        $templateId = (string) time() . '_' . bin2hex(random_bytes(6));
        $templateModel->insert([
            'id'                => $templateId,
            'employee_id'       => $id,
            'template_version'  => 'v1.0',
            'vector_dimensions' => 128,
            'embedding_data'    => json_encode($embedding),
            'model_name'        => $modelName,
            'quality_score'     => $qualityScore,
            'registered_by'     => session()->get('admin_username') ?? 'admin',
        ]);

        $this->auditModel->log(
            session()->get('admin_username') ?? 'admin',
            'EMPLOYEE_FACE_ENROLLED',
            json_encode([
                'employee_id' => $employee['employee_id'],
                'full_name'   => $employee['full_name'],
                'dimensions'  => 128,
            ]),
            'EMPLOYEE',
            $id
        );

        return $this->response->setJSON([
            'status'  => 'success',
            'message' => "Facial template successfully enrolled for {$employee['full_name']}.",
        ]);
    }
}

