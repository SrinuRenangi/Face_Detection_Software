<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialDevSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // Initial Admin Accounts
        $adminData = [
            [
                'id'            => 'a0000000-0000-0000-0000-000000000001',
                'username'      => 'admin',
                'password_hash' => password_hash('Admin@12345', PASSWORD_BCRYPT),
                'full_name'     => 'System Administrator',
                'role'          => 'ROLE_ADMIN',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'a0000000-0000-0000-0000-000000000002',
                'username'      => 'manager',
                'password_hash' => password_hash('Manager@12345', PASSWORD_BCRYPT),
                'full_name'     => 'Operations Manager',
                'role'          => 'ROLE_MANAGER',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        foreach ($adminData as $user) {
            $existing = $this->db->table('admin_users')->where('username', $user['username'])->get()->getRow();
            if (!$existing) {
                $this->db->table('admin_users')->insert($user);
            }
        }

        // Initial Test Device: Strictly PENDING_PAIRING with NO hardcoded token
        // Device requires explicit administrator pairing in accordance with Stage 3.3.2 security policy
        $deviceIdent = 'DEV-TABLET-001';
        $existingDev = $this->db->table('kiosk_devices')->where('device_identifier', $deviceIdent)->get()->getRow();
        if (!$existingDev) {
            $deviceData = [
                'id'                  => 'd0000000-0000-0000-0000-000000000001',
                'device_name'         => 'School Lobby Tablet',
                'device_identifier'   => $deviceIdent,
                'device_type'         => 'TABLET',
                'device_model'        => 'Pending Hardware Registration',
                'hardware_tier'       => 'UNKNOWN',
                'terminal_mode'       => 'ENTRY_ONLY',
                'device_epoch_id'     => 'd0000000-0000-0000-0000-000000000002',
                'last_event_sequence' => 0,
                'status'              => 'PENDING_PAIRING',
                'device_token_hash'   => null,
                'token_created_at'    => null,
                'token_expires_at'    => null,
                'token_rotated_at'    => null,
                'paired_at'           => null,
                'last_seen_at'        => null,
                'revoked_at'          => null,
                'revocation_reason'   => null,
                'created_by'          => 'admin',
                'created_at'          => $now,
                'updated_at'          => $now,
            ];
            $this->db->table('kiosk_devices')->insert($deviceData);
        } else {
            // Idempotent safeguard: DEV-TABLET-001 is a retired development fixture and must never be ACTIVE.
            // If it is not already REVOKED, transition it to REVOKED.
            // If already REVOKED, preserve existing revocation timestamps and reason without overwriting.
            if ($existingDev->status !== 'REVOKED') {
                $this->db->table('kiosk_devices')
                    ->where('id', $existingDev->id)
                    ->update([
                        'status'            => 'REVOKED',
                        'revoked_at'        => $existingDev->revoked_at ?: $now,
                        'revocation_reason' => $existingDev->revocation_reason ?: 'Retired development fixture; credential containment enforced',
                        'token_expires_at'  => $existingDev->token_expires_at ?: $now,
                        'updated_at'        => $now,
                    ]);
            }
        }

        // Initial Test Employee
        $empData = [
            'id'          => 'e0000000-0000-0000-0000-000000000001',
            'employee_id' => 'EMP-001',
            'full_name'   => 'Katherine Johnson',
            'department'  => 'Mathematics',
            'status'      => 'ACTIVE',
            'pin_hash'    => null,
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        $existingEmp = $this->db->table('employees')->where('employee_id', $empData['employee_id'])->get()->getRow();
        if (!$existingEmp) {
            $this->db->table('employees')->insert($empData);
        }
    }
}

