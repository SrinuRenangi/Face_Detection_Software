<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateInitialSchema extends Migration
{
    public function up()
    {
        // 1. Admin Users Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'username' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'password_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['ROLE_ADMIN', 'ROLE_MANAGER'],
                'default'    => 'ROLE_ADMIN',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 1,
            ],
            'last_login_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('username');
        $this->forge->addKey(['role', 'is_active']);
        $this->forge->createTable('admin_users', true);

        // 2. Admin Security Audit Logs
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'actor_username' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'action' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'target_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'target_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
            ],
            'details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
                'null'       => true,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('actor_username');
        $this->forge->addKey('action');
        $this->forge->addKey('created_at');
        $this->forge->createTable('admin_audit_logs', true);

        // 3. Employees Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'full_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'department' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'default'    => 'General',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['ACTIVE', 'INACTIVE'],
                'default'    => 'ACTIVE',
            ],
            'pin_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('employee_id');
        $this->forge->addKey('status');
        $this->forge->addKey('department');
        $this->forge->createTable('employees', true);

        // 4. Employee Face Templates Table (Biometric placeholder schema)
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'template_version' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'default'    => 'human-v3-arcface',
            ],
            'vector_dimensions' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 512,
            ],
            'embedding_data' => [
                'type' => 'LONGTEXT',
            ],
            'model_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'default'    => 'ArcFace',
            ],
            'quality_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'default'    => 1.0000,
            ],
            'registered_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('employee_id');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('employee_face_templates', true);

        // 5. Kiosk Devices Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'device_identifier' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['PENDING_PAIRING', 'ACTIVE', 'REVOKED'],
                'default'    => 'PENDING_PAIRING',
            ],
            'device_token_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
                'null'       => true,
            ],
            'paired_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revoked_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'revocation_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('device_identifier');
        $this->forge->addKey('status');
        $this->forge->addKey('device_token_hash');
        $this->forge->createTable('kiosk_devices', true);

        // 6. Kiosk Pairing Codes Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'device_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'pairing_code_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'created_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'expires_at' => [
                'type' => 'DATETIME',
            ],
            'used_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'cancelled_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'failed_attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'max_attempts' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 5,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['PENDING', 'USED', 'EXPIRED', 'CANCELLED'],
                'default'    => 'PENDING',
            ],
            'paired_device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('pairing_code_hash');
        $this->forge->addKey(['status', 'expires_at']);
        $this->forge->addForeignKey('paired_device_id', 'kiosk_devices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('kiosk_pairing_codes', true);

        // 7. Attendance Records Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'work_date' => [
                'type' => 'DATE',
            ],
            'check_in_time' => [
                'type' => 'DATETIME',
            ],
            'check_out_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['CHECKED_IN', 'CHECKED_OUT', 'AUTO_CLOSED'],
            ],
            'confidence_score' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,4',
                'null'       => true,
            ],
            'device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'is_corrected' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'version' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['employee_id', 'work_date']); // Invariant: exactly 1 shift record per employee per day
        $this->forge->addKey('work_date');
        $this->forge->addKey('status');
        $this->forge->addKey('check_in_time');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'RESTRICT', 'CASCADE');
        $this->forge->addForeignKey('device_id', 'kiosk_devices', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('attendance_records', true);

        // 8. Attendance Corrections Table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'attendance_record_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'action_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'previous_check_in_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'corrected_check_in_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'previous_check_out_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'corrected_check_out_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'previous_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
            ],
            'corrected_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
            ],
            'reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'performed_by' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('attendance_record_id');
        $this->forge->addKey('employee_id');
        $this->forge->addKey('performed_by');
        $this->forge->addForeignKey('attendance_record_id', 'attendance_records', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('attendance_corrections', true);

        // 9. Idempotency Keys Table
        $this->forge->addField([
            'key_value' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'endpoint' => [
                'type'       => 'VARCHAR',
                'constraint' => 128,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
            ],
            'status_code' => [
                'type'       => 'INT',
                'constraint' => 11,
            ],
            'response_body' => [
                'type' => 'LONGTEXT',
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);
        $this->forge->addKey('key_value', true);
        $this->forge->addKey('created_at');
        $this->forge->createTable('idempotency_keys', true);
    }

    public function down()
    {
        $this->forge->dropTable('attendance_corrections', true);
        $this->forge->dropTable('attendance_records', true);
        $this->forge->dropTable('idempotency_keys', true);
        $this->forge->dropTable('kiosk_pairing_codes', true);
        $this->forge->dropTable('kiosk_devices', true);
        $this->forge->dropTable('employee_face_templates', true);
        $this->forge->dropTable('employees', true);
        $this->forge->dropTable('admin_audit_logs', true);
        $this->forge->dropTable('admin_users', true);
    }
}

