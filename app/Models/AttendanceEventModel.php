<?php

namespace App\Models;

use CodeIgniter\Model;

class AttendanceEventModel extends Model
{
    protected $table            = 'attendance_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'client_uuid',
        'employee_id',
        'device_id',
        'device_epoch_id',
        'device_event_sequence',
        'event_type',
        'terminal_mode',
        'client_recorded_at',
        'client_synced_at',
        'server_received_at',
        'client_timezone',
        'client_monotonic_ms',
        'server_anchor_id',
        'browser_session_id',
        'device_wall_clock_at',
        'anchor_elapsed_ms',
        'clock_trust_status',
        'synchronization_source',
        'payload_hash',
        'processing_status',
        'notes',
        'created_at',
    ];

    protected $useTimestamps = false;

    public static function generateUuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}

