<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenReviewDecisionForeignKeys extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('attendance_review_decisions')) {
            // Check if fk_ard_reviewer exists
            $existingFks = $this->db->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.REFERENTIAL_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'attendance_review_decisions'
            ")->getResultArray();

            $fkNames = array_column($existingFks, 'CONSTRAINT_NAME');

            if (!in_array('fk_ard_reviewer', $fkNames, true)) {
                $this->db->query("
                    ALTER TABLE attendance_review_decisions 
                    ADD CONSTRAINT fk_ard_reviewer 
                    FOREIGN KEY (reviewer_id) REFERENCES admin_users(id) 
                    ON DELETE RESTRICT ON UPDATE CASCADE
                ");
            }

            if (!in_array('fk_ard_record', $fkNames, true)) {
                $this->db->query("
                    ALTER TABLE attendance_review_decisions 
                    ADD CONSTRAINT fk_ard_record 
                    FOREIGN KEY (applied_record_id) REFERENCES attendance_records(id) 
                    ON DELETE RESTRICT ON UPDATE CASCADE
                ");
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('attendance_review_decisions')) {
            $existingFks = $this->db->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.REFERENTIAL_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'attendance_review_decisions'
            ")->getResultArray();

            $fkNames = array_column($existingFks, 'CONSTRAINT_NAME');

            if (in_array('fk_ard_record', $fkNames, true)) {
                $this->db->query("ALTER TABLE attendance_review_decisions DROP FOREIGN KEY fk_ard_record");
            }

            if (in_array('fk_ard_reviewer', $fkNames, true)) {
                $this->db->query("ALTER TABLE attendance_review_decisions DROP FOREIGN KEY fk_ard_reviewer");
            }
        }
    }
}

