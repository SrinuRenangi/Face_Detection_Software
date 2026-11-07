<?php

namespace App\Models;

use CodeIgniter\Model;

class IdempotencyKeyModel extends Model
{
    protected $table            = 'idempotency_keys';
    protected $primaryKey       = 'key_value';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'key_value',
        'endpoint',
        'employee_id',
        'status_code',
        'response_body',
        'created_at',
    ];

    protected $useTimestamps = false;
}

