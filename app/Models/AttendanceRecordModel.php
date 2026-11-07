<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceRecordModel extends Model
{
    protected $table            = 'attendance_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'employee_id',
        'work_date',
        'check_in_time',
        'check_out_time',
        'status',
        'confidence_score',
        'device_id',
        'client_uuid',
        'sync_source',
        'is_corrected',
        'version',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function countTodayPunches(): int
    {
        $today = date('Y-m-d');
        return $this->where('work_date', $today)->countAllResults();
    }
}

