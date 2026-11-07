<?php

namespace App\Models;

use CodeIgniter\Model;

class EmployeeFaceTemplateModel extends Model
{
    protected $table            = 'employee_face_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'employee_id',
        'template_version',
        'vector_dimensions',
        'embedding_data',
        'model_name',
        'quality_score',
        'registered_by',
        'created_at',
        'updated_at',
    ];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Retrieves all active face templates joined with employee details
     */
    public function getActiveEnrolledTemplates(): array
    {
        return $this->db->table($this->table)
            ->select('employee_face_templates.id as template_id, employee_face_templates.employee_id, employee_face_templates.embedding_data, employee_face_templates.quality_score, employees.employee_id as employee_code, employees.full_name, employees.department, employees.status')
            ->join('employees', 'employees.id = employee_face_templates.employee_id')
            ->where('employees.status', 'ACTIVE')
            ->get()
            ->getResultArray();
    }
}

