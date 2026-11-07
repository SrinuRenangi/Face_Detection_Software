<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendanceEventsLedger extends Migration
{
    public function up()
    {
        // 1. Extend kiosk_devices with terminal_mode, device_epoch_id, last_event_sequence
        $deviceFields = [
            'terminal_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['ENTRY_ONLY', 'EXIT_ONLY', 'DIRECTIONAL_AUTO'],
                'default'    => 'ENTRY_ONLY',
                'after'      => 'hardware_tier',
            ],
            'device_epoch_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'terminal_mode',
            ],
            'last_event_sequence' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'default'    => 0,
                'after'      => 'device_epoch_id',
            ],
        ];
        $this->forge->addColumn('kiosk_devices', $deviceFields);

        // 2. Create append-only attendance_events table
        $this->forge->addField([
            'id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'client_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'employee_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'device_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'device_epoch_id' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
            ],
            'device_event_sequence' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'event_type' => [
                'type'       => 'ENUM',
                'constraint' => ['CHECK_IN', 'CHECK_OUT', 'PRESENCE_CONFIRMED'],
            ],
            'terminal_mode' => [
                'type'       => 'ENUM',
                'constraint' => ['ENTRY_ONLY', 'EXIT_ONLY', 'DIRECTIONAL_AUTO'],
                'default'    => 'ENTRY_ONLY',
            ],
            'client_recorded_at' => [
                'type' => 'DATETIME',
            ],
            'client_synced_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'server_received_at' => [
                'type' => 'DATETIME',
            ],
            'client_timezone' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'default'    => 'Asia/Kolkata',
            ],
            'client_monotonic_ms' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'null'       => true,
            ],
            'clock_trust_status' => [
                'type'       => 'ENUM',
                'constraint' => ['TRUSTED_SERVER_TIME', 'NORMAL_SYNC', 'UNVERIFIED_OFFLINE_TIME', 'SUSPICIOUS_DRIFT', 'TIME_MANIPULATED'],
                'default'    => 'NORMAL_SYNC',
            ],
            'synchronization_source' => [
                'type'       => 'ENUM',
                'constraint' => ['DIRECT_ONLINE', 'OFFLINE_SYNC'],
                'default'    => 'DIRECT_ONLINE',
            ],
            'payload_hash' => [
                'type'       => 'CHAR',
                'constraint' => 64,
            ],
            'processing_status' => [
                'type'       => 'ENUM',
                'constraint' => ['PROCESSED', 'DUPLICATE_IGNORED', 'RECONCILED_EARLIER', 'REJECTED_NO_OPEN_RECORD', 'QUARANTINED', 'REVIEW_REQUIRED'],
                'default'    => 'PROCESSED',
            ],
            'notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('client_uuid', 'uk_att_events_client_uuid');
        $this->forge->addUniqueKey(['device_id', 'device_epoch_id', 'device_event_sequence'], 'uk_att_events_device_seq');
        $this->forge->addKey(['employee_id', 'client_recorded_at'], false, false, 'idx_att_events_emp_time');
        $this->forge->addKey('processing_status', false, false, 'idx_att_events_status');
        $this->forge->addKey('server_received_at', false, false, 'idx_att_events_received');

        $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'RESTRICT', 'fk_att_events_employee_id');
        $this->forge->addForeignKey('device_id', 'kiosk_devices', 'id', 'CASCADE', 'RESTRICT', 'fk_att_events_device_id');

        $this->forge->createTable('attendance_events', true);
    }

    public function down()
    {
        $this->forge->dropTable('attendance_events', true);
        $this->forge->dropColumn('kiosk_devices', ['terminal_mode', 'device_epoch_id', 'last_event_sequence']);
    }
}

