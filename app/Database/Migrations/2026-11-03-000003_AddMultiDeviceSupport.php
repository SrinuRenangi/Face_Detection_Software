<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMultiDeviceSupport extends Migration
{
    public function up()
    {
        // 1. Extend kiosk_devices with device_type, device_model, and hardware_tier
        $deviceFields = [
            'device_type' => [
                'type'       => 'ENUM',
                'constraint' => ['SMARTPHONE', 'TABLET', 'DESKTOP'],
                'default'    => 'SMARTPHONE',
                'after'      => 'device_identifier',
            ],
            'device_model' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'after'      => 'device_type',
            ],
            'hardware_tier' => [
                'type'       => 'ENUM',
                'constraint' => ['ENTRY', 'MIDRANGE', 'PREMIUM', 'UNKNOWN'],
                'default'    => 'UNKNOWN',
                'after'      => 'device_model',
            ],
        ];
        $this->forge->addColumn('kiosk_devices', $deviceFields);
        $this->db->query('ALTER TABLE kiosk_devices ADD INDEX idx_kiosk_devices_type (device_type)');

        // 2. Extend attendance_records with client_uuid and sync_source
        $attendanceFields = [
            'client_uuid' => [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'device_id',
            ],
            'sync_source' => [
                'type'       => 'ENUM',
                'constraint' => ['DIRECT_ONLINE', 'OFFLINE_SYNC'],
                'default'    => 'DIRECT_ONLINE',
                'after'      => 'client_uuid',
            ],
        ];
        $this->forge->addColumn('attendance_records', $attendanceFields);
        $this->db->query('ALTER TABLE attendance_records ADD INDEX idx_attendance_records_client_uuid (client_uuid)');
    }

    public function down()
    {
        $this->forge->dropColumn('attendance_records', ['client_uuid', 'sync_source']);
        $this->forge->dropColumn('kiosk_devices', ['device_type', 'device_model', 'hardware_tier']);
    }
}

