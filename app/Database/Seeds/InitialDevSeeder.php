<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class InitialDevSeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        $adminPassword   = env('INITIAL_ADMIN_PASSWORD', bin2hex(random_bytes(8)));
        $managerPassword = env('INITIAL_MANAGER_PASSWORD', bin2hex(random_bytes(8)));

        // Initial Admin Accounts (Passwords loaded from environment and hashed with Bcrypt)
        $adminData = [
            [
                'id'            => 'a0000000-0000-0000-0000-000000000001',
                'username'      => env('INITIAL_ADMIN_USER', 'admin'),
                'email'         => env('INITIAL_ADMIN_EMAIL', 'admin@edifies.school'),
                'password_hash' => password_hash($adminPassword, PASSWORD_BCRYPT),
                'full_name'     => 'System Administrator',
                'role'          => 'ROLE_ADMIN',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'a0000000-0000-0000-0000-000000000002',
                'username'      => env('INITIAL_MANAGER_USER', 'manager'),
                'email'         => env('INITIAL_MANAGER_EMAIL', 'manager@edifies.school'),
                'password_hash' => password_hash($managerPassword, PASSWORD_BCRYPT),
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
    }
}

