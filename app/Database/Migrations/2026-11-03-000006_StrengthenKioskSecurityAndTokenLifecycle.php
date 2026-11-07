<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migration 000006: Strengthen Kiosk Security, Token Lifecycle & Defensible Offline Anchors
 *
 * 1. Adds token lifecycle columns (token_created_at, token_expires_at, token_rotated_at) to kiosk_devices.
 * 2. Extends kiosk_pairing_codes status enum to explicitly support 'EXHAUSTED' state.
 * 3. Creates kiosk_time_anchors table for cryptographic and monotonic server-time anchoring.
 * 4. Adds server anchor tracking columns (server_anchor_id, browser_session_id, device_wall_clock_at, anchor_elapsed_ms)
 *    to the append-only attendance_events ledger.
 */
class StrengthenKioskSecurityAndTokenLifecycle extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1. Extend kiosk_devices with explicit token lifecycle columns
        $deviceFields = $db->getFieldNames('kiosk_devices');

        $colsToAdd = [];
        if (!in_array('token_created_at', $deviceFields, true)) {
            $colsToAdd['token_created_at'] = [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'device_token_hash',
            ];
        }
        if (!in_array('token_expires_at', $deviceFields, true)) {
            $colsToAdd['token_expires_at'] = [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'token_created_at',
            ];
        }
        if (!in_array('token_rotated_at', $deviceFields, true)) {
            $colsToAdd['token_rotated_at'] = [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'token_expires_at',
            ];
        }

        if (!empty($colsToAdd)) {
            $this->forge->addColumn('kiosk_devices', $colsToAdd);
        }

        // Add index on token_expires_at if missing
        $deviceIndexes = $db->query("SHOW INDEX FROM kiosk_devices")->getResultArray();
        $idxNames = array_column($deviceIndexes, 'Key_name');
        if (!in_array('idx_kiosk_token_expires', $idxNames, true)) {
            $db->query("ALTER TABLE kiosk_devices ADD INDEX `idx_kiosk_token_expires` (`token_expires_at`)");
        }

        // Populate existing paired devices with default token expiration (30 days from paired_at or now)
        $db->query("
            UPDATE kiosk_devices 
            SET token_created_at = COALESCE(paired_at, created_at),
                token_expires_at = DATE_ADD(COALESCE(paired_at, created_at), INTERVAL 30 DAY)
            WHERE status = 'ACTIVE' AND token_expires_at IS NULL
        ");

        // 2. Extend kiosk_pairing_codes status enum to include EXHAUSTED
        $db->query("
            ALTER TABLE kiosk_pairing_codes 
            MODIFY COLUMN `status` ENUM('PENDING','USED','EXPIRED','CANCELLED','EXHAUSTED') NOT NULL DEFAULT 'PENDING'
        ");

        // 3. Create kiosk_time_anchors table if it does not exist
        if (!$db->tableExists('kiosk_time_anchors')) {
            $this->forge->addField([
                'id' => [
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
                'server_utc_time' => [
                    'type' => 'DATETIME',
                ],
                'expires_at' => [
                    'type' => 'DATETIME',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['device_id', 'expires_at'], false, false, 'idx_anchors_device_expires');
            $this->forge->addForeignKey('device_id', 'kiosk_devices', 'id', 'CASCADE', 'CASCADE', 'fk_anchors_device_id');
            $this->forge->createTable('kiosk_time_anchors', true);
        }

        // 4. Extend attendance_events with server anchor telemetry fields
        $eventFields = $db->getFieldNames('attendance_events');
        $eventColsToAdd = [];

        if (!in_array('server_anchor_id', $eventFields, true)) {
            $eventColsToAdd['server_anchor_id'] = [
                'type'       => 'VARCHAR',
                'constraint' => 36,
                'null'       => true,
                'after'      => 'client_monotonic_ms',
            ];
        }
        if (!in_array('browser_session_id', $eventFields, true)) {
            $eventColsToAdd['browser_session_id'] = [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => true,
                'after'      => 'server_anchor_id',
            ];
        }
        if (!in_array('device_wall_clock_at', $eventFields, true)) {
            $eventColsToAdd['device_wall_clock_at'] = [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'browser_session_id',
            ];
        }
        if (!in_array('anchor_elapsed_ms', $eventFields, true)) {
            $eventColsToAdd['anchor_elapsed_ms'] = [
                'type'  => 'BIGINT',
                'null'  => true,
                'after' => 'device_wall_clock_at',
            ];
        }

        if (!empty($eventColsToAdd)) {
            $this->forge->addColumn('attendance_events', $eventColsToAdd);
        }
    }

    public function down()
    {
        // Safe rollback
        $db = $this->db;

        if ($db->tableExists('kiosk_time_anchors')) {
            $this->forge->dropTable('kiosk_time_anchors', true);
        }

        $deviceFields = $db->getFieldNames('kiosk_devices');
        $toDrop = array_intersect(['token_created_at', 'token_expires_at', 'token_rotated_at'], $deviceFields);
        foreach ($toDrop as $col) {
            $this->forge->dropColumn('kiosk_devices', $col);
        }

        $eventFields = $db->getFieldNames('attendance_events');
        $eventDrop = array_intersect(['server_anchor_id', 'browser_session_id', 'device_wall_clock_at', 'anchor_elapsed_ms'], $eventFields);
        foreach ($eventDrop as $col) {
            $this->forge->dropColumn('attendance_events', $col);
        }
    }
}

