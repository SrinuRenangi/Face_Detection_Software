<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BindAnchorsToBrowserSessionAndAddReviewDecisions extends Migration
{
    public function up()
    {
        // 1. Add browser_session_hash to kiosk_time_anchors
        if ($this->db->tableExists('kiosk_time_anchors') && !$this->db->fieldExists('browser_session_hash', 'kiosk_time_anchors')) {
            $fields = [
                'browser_session_hash' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => false,
                    'default'    => '',
                    'after'      => 'device_epoch_id',
                ],
            ];
            $this->forge->addColumn('kiosk_time_anchors', $fields);
            $this->db->query("CREATE INDEX idx_kta_dev_session ON kiosk_time_anchors (device_id, browser_session_hash)");
        }

        // 2. Create attendance_review_decisions table
        if (!$this->db->tableExists('attendance_review_decisions')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'event_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'decision' => [
                    'type'       => 'ENUM',
                    'constraint' => ['APPROVED', 'REJECTED'],
                ],
                'reviewer_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'reviewer_username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                ],
                'review_reason' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'applied_record_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'decided_at' => [
                    'type' => 'DATETIME',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('event_id');
            $this->forge->addKey(['decision', 'decided_at']);
            $this->forge->addKey('reviewer_id');
            $this->forge->addForeignKey('event_id', 'attendance_events', 'id', 'RESTRICT', 'RESTRICT');

            $this->forge->createTable('attendance_review_decisions', true, [
                'ENGINE'          => 'InnoDB',
                'DEFAULT CHARSET' => 'utf8mb4',
                'COLLATE'         => 'utf8mb4_unicode_ci',
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('attendance_review_decisions')) {
            $this->forge->dropTable('attendance_review_decisions', true);
        }

        if ($this->db->tableExists('kiosk_time_anchors') && $this->db->fieldExists('browser_session_hash', 'kiosk_time_anchors')) {
            $this->forge->dropColumn('kiosk_time_anchors', 'browser_session_hash');
        }
    }
}
