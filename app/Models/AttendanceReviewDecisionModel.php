<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceReviewDecisionModel extends Model
{
    protected $table            = 'attendance_review_decisions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'event_id',
        'decision',
        'reviewer_id',
        'reviewer_username',
        'review_reason',
        'applied_record_id',
        'decided_at',
        'created_at',
    ];

    protected $useTimestamps = false;
}

