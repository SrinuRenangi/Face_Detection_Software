<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000005: Fix Attendance Event Indexes and Ledger Foreign Key Constraints
 *
 * 1. Corrects defective unique indexes on (employee_id, client_recorded_at), processing_status,
 *    and server_received_at created by migration 000004 in attendance_dev.
 * 2. Establishes ordinary non-unique indexes for performant query lookups while preserving
 *    the two intended unique constraints (uk_att_events_client_uuid and uk_att_events_device_seq).
 * 3. Adds client_synced_at telemetry column for accurate offline queue delay tracking.
 * 4. Changes foreign key delete behavior to RESTRICT so employees and kiosk devices with
 *    immutable historical ledger events cannot be hard-deleted.
 */
class FixAttendanceEventIndexes extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1. Add client_synced_at column if not already present
        $fields = $db->getFieldNames('attendance_events');
        if (!in_array('client_synced_at', $fields, true)) {
            $this->forge->addColumn('attendance_events', [
                'client_synced_at' => [
                    'type'  => 'DATETIME',
                    'null'  => true,
                    'after' => 'client_recorded_at',
                ],
            ]);
        }

        // 2. Drop existing foreign keys first so they do not block dropping or altering supporting indexes
        $databaseName = $db->getDatabase();
        $fkRows = $db->query("
            SELECT CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = 'attendance_events'
        ", [$databaseName])->getResultArray();

        foreach ($fkRows as $fk) {
            $cName = $fk['CONSTRAINT_NAME'];
            $db->query("ALTER TABLE attendance_events DROP FOREIGN KEY `{$cName}`");
        }

        // 3. Inspect existing indexes on attendance_events
        $indexRows = $db->query("SHOW INDEX FROM attendance_events")->getResultArray();
        $indexes = [];
        foreach ($indexRows as $row) {
            $keyName = $row['Key_name'];
            if (!isset($indexes[$keyName])) {
                $indexes[$keyName] = [
                    'non_unique' => (int) $row['Non_unique'],
                    'columns'    => [],
                ];
            }
            $indexes[$keyName]['columns'][] = $row['Column_name'];
        }

        // A. Create the replacement non-unique index idx_att_events_emp_time if missing
        if (!isset($indexes['idx_att_events_emp_time'])) {
            $db->query("ALTER TABLE attendance_events ADD INDEX `idx_att_events_emp_time` (`employee_id`, `client_recorded_at`)");
        }

        // B. Drop defective UNIQUE KEY on employee_id_client_recorded_at if it exists
        if (isset($indexes['employee_id_client_recorded_at'])) {
            $db->query("ALTER TABLE attendance_events DROP INDEX `employee_id_client_recorded_at`");
        }

        // C. Create replacement idx_att_events_status if missing
        if (!isset($indexes['idx_att_events_status'])) {
            $db->query("ALTER TABLE attendance_events ADD INDEX `idx_att_events_status` (`processing_status`)");
        }

        // D. Drop defective UNIQUE KEY on processing_status if unique
        if (isset($indexes['processing_status']) && $indexes['processing_status']['non_unique'] === 0) {
            $db->query("ALTER TABLE attendance_events DROP INDEX `processing_status`");
        }

        // E. Create replacement idx_att_events_received if missing
        if (!isset($indexes['idx_att_events_received'])) {
            $db->query("ALTER TABLE attendance_events ADD INDEX `idx_att_events_received` (`server_received_at`)");
        }

        // F. Drop defective UNIQUE KEY on server_received_at if unique
        if (isset($indexes['server_received_at']) && $indexes['server_received_at']['non_unique'] === 0) {
            $db->query("ALTER TABLE attendance_events DROP INDEX `server_received_at`");
        }

        // 4. Recreate Foreign Keys with ON DELETE RESTRICT ON UPDATE CASCADE
        $db->query("ALTER TABLE attendance_events ADD CONSTRAINT `fk_att_events_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT");
        $db->query("ALTER TABLE attendance_events ADD CONSTRAINT `fk_att_events_device_id` FOREIGN KEY (`device_id`) REFERENCES `kiosk_devices` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT");
    }

    public function down()
    {
        // Safe rollback: Never reintroduce defective unique indexes.
        $db = $this->db;
        $fields = $db->getFieldNames('attendance_events');
        if (in_array('client_synced_at', $fields, true)) {
            $this->forge->dropColumn('attendance_events', 'client_synced_at');
        }
    }
}
