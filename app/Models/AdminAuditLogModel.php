<?php

namespace App\Models;

use CodeIgniter\Model;

class AdminAuditLogModel extends Model
{
    protected $table            = 'admin_audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = false;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'id',
        'actor_username',
        'action',
        'target_type',
        'target_id',
        'details',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $useTimestamps = false;

    /**
     * Helper to write an audit log entry
     */
    public function log(string $actor, string $action, ?string $details = null, ?string $targetType = null, ?string $targetId = null): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);
        $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));

        $request = service('request');

        $this->insert([
            'id'             => $id,
            'actor_username' => $actor,
            'action'         => $action,
            'target_type'    => $targetType,
            'target_id'      => $targetId,
            'details'        => $details,
            'ip_address'     => $request->getIPAddress(),
            'user_agent'     => substr((string) $request->getUserAgent(), 0, 255),
            'created_at'     => date('Y-m-d H:i:s'),
        ]);

        return $id;
    }
}

