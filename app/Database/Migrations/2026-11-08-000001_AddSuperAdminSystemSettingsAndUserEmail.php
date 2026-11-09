<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuperAdminSystemSettingsAndUserEmail extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1. Expand role enum on admin_users and add email column
        if ($db->tableExists('admin_users')) {
            $fields = $db->getFieldData('admin_users');
            $existingFieldNames = array_map(static fn($f) => $f->name, $fields);

            if (!in_array('email', $existingFieldNames, true)) {
                $this->forge->addColumn('admin_users', [
                    'email' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 128,
                        'null'       => true,
                        'after'      => 'username',
                    ],
                ]);
            }

            // Update role ENUM definition
            $db->query("
                ALTER TABLE admin_users 
                MODIFY COLUMN role ENUM('ROLE_SUPER_ADMIN','ROLE_ADMIN','ROLE_MANAGER','ROLE_SCANNER') 
                NOT NULL DEFAULT 'ROLE_ADMIN'
            ");
        }

        // 2. Create system_settings table
        if (!$db->tableExists('system_settings')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'setting_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'setting_value' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'updated_by' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('setting_key', 'idx_setting_key');
            $this->forge->createTable('system_settings', true);
        }

        // 3. Seed default system settings
        $now = date('Y-m-d H:i:s');
        $defaultSettings = [
            [
                'id'            => 'cf000000-0000-0000-0000-000000000001',
                'setting_key'   => 'app_killswitch_active',
                'setting_value' => '0',
                'description'   => 'Master Kill-Switch (0 = Active / Normal, 1 = Suspended / Maintenance)',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'cf000000-0000-0000-0000-000000000002',
                'setting_key'   => 'killswitch_message',
                'setting_value' => 'Application access is temporarily suspended. Please contact the system provider.',
                'description'   => 'Public lockout banner message shown when the killswitch is engaged',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'cf000000-0000-0000-0000-000000000003',
                'setting_key'   => 'school_name',
                'setting_value' => 'Edifies School',
                'description'   => 'Institution / Organization Name',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'cf000000-0000-0000-0000-000000000004',
                'setting_key'   => 'academic_year',
                'setting_value' => '2026-2027',
                'description'   => 'Active Academic / Operational Year',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'cf000000-0000-0000-0000-000000000005',
                'setting_key'   => 'system_timezone',
                'setting_value' => 'Asia/Kolkata',
                'description'   => 'Official Institution Timezone',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
            [
                'id'            => 'cf000000-0000-0000-0000-000000000006',
                'setting_key'   => 'kiosk_cooldown_seconds',
                'setting_value' => '60',
                'description'   => 'Duplicate face punch throttle duration in seconds',
                'updated_by'    => 'SYSTEM',
                'created_at'    => $now,
                'updated_at'    => $now,
            ],
        ];

        foreach ($defaultSettings as $setting) {
            $exists = $db->table('system_settings')->where('setting_key', $setting['setting_key'])->countAllResults();
            if ($exists === 0) {
                $db->table('system_settings')->insert($setting);
            }
        }

        // 4. Seed default Super-Admin account (Loaded from environment)
        $superAdminExists = $db->table('admin_users')->where('username', env('INITIAL_SUPERADMIN_USER', 'superadmin'))->countAllResults();
        if ($superAdminExists === 0) {
            $superAdminPassword = env('INITIAL_SUPERADMIN_PASSWORD', bin2hex(random_bytes(10)));
            $db->table('admin_users')->insert([
                'id'            => 's0000000-0000-0000-0000-000000000001',
                'username'      => env('INITIAL_SUPERADMIN_USER', 'superadmin'),
                'email'         => env('INITIAL_SUPERADMIN_EMAIL', 'developer@edifies.school'),
                'password_hash' => password_hash($superAdminPassword, PASSWORD_BCRYPT),
                'full_name'     => 'Lead System Developer',
                'role'          => 'ROLE_SUPER_ADMIN',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // 5. Seed default Scanner account (Loaded from environment)
        $scannerExists = $db->table('admin_users')->where('username', 'scanner')->countAllResults();
        if ($scannerExists === 0) {
            $scannerPassword = env('INITIAL_SCANNER_PASSWORD', bin2hex(random_bytes(10)));
            $db->table('admin_users')->insert([
                'id'            => 's0000000-0000-0000-0000-000000000002',
                'username'      => 'scanner',
                'email'         => 'scanner@edifies.school',
                'password_hash' => password_hash($scannerPassword, PASSWORD_BCRYPT),
                'full_name'     => 'Kiosk Scanner Terminal User',
                'role'          => 'ROLE_SCANNER',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        // 6. Update existing admin and manager accounts with default emails if null
        $db->table('admin_users')->where('username', 'admin')->where('email', null)->update(['email' => 'admin@edifies.school']);
        $db->table('admin_users')->where('username', 'manager')->where('email', null)->update(['email' => 'manager@edifies.school']);
    }

    public function down()
    {
        if ($this->db->tableExists('system_settings')) {
            $this->forge->dropTable('system_settings', true);
        }

        if ($this->db->tableExists('admin_users')) {
            $fields = $this->db->getFieldData('admin_users');
            $existingFieldNames = array_map(static fn($f) => $f->name, $fields);
            if (in_array('email', $existingFieldNames, true)) {
                $this->forge->dropColumn('admin_users', 'email');
            }
            $this->db->query("
                ALTER TABLE admin_users 
                MODIFY COLUMN role ENUM('ROLE_ADMIN','ROLE_MANAGER') 
                NOT NULL DEFAULT 'ROLE_ADMIN'
            ");
        }
    }
}
