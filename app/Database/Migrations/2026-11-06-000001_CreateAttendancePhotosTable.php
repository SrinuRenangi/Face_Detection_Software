<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAttendancePhotosTable extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('attendance_photos')) {
            $this->forge->addField([
                'id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'event_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'attendance_record_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                    'null'       => true,
                ],
                'employee_id' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 36,
                ],
                'photo_type' => [
                    'type'       => 'ENUM',
                    'constraint' => ['CHECK_IN', 'CHECK_OUT'],
                ],
                'storage_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'file_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 128,
                ],
                'mime_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'default'    => 'image/jpeg',
                ],
                'file_size' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                ],
                'image_width' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                ],
                'image_height' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('event_id', 'uk_att_photos_event');
            $this->forge->addKey('attendance_record_id', false, false, 'idx_att_photos_record');
            $this->forge->addKey(['employee_id', 'created_at'], false, false, 'idx_att_photos_emp_created');
            $this->forge->addKey('photo_type', false, false, 'idx_att_photos_type');

            $this->forge->addForeignKey('event_id', 'attendance_events', 'id', 'CASCADE', 'CASCADE', 'fk_att_photos_event_id');
            $this->forge->addForeignKey('attendance_record_id', 'attendance_records', 'id', 'CASCADE', 'SET NULL', 'fk_att_photos_record_id');
            $this->forge->addForeignKey('employee_id', 'employees', 'id', 'CASCADE', 'CASCADE', 'fk_att_photos_employee_id');

            $this->forge->createTable('attendance_photos', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('attendance_photos')) {
            $this->forge->dropTable('attendance_photos', true);
        }
    }
}
