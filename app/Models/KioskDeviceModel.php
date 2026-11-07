<?php

namespace App\Models;

use CodeIgniter\Model;

class KioskDeviceModel extends Model
{
    protected $table            = 'kiosk_devices';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'device_name',
        'device_identifier',
        'device_type',
        'device_model',
        'hardware_tier',
        'terminal_mode',
        'device_epoch_id',
        'last_event_sequence',
        'status',
        'device_token_hash',
        'token_created_at',
        'token_expires_at',
        'token_rotated_at',
        'paired_at',
        'last_seen_at',
        'revoked_at',
        'revocation_reason',
        'created_by',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function countActive(): int
    {
        return $this->where('status', 'ACTIVE')->countAllResults();
    }
}

