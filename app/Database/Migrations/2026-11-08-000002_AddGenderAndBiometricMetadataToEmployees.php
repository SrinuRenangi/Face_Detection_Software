<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddGenderAndBiometricMetadataToEmployees extends Migration
{
    public function up()
    {
        $db = $this->db;

        // 1. Add gender column to employees table
        if ($db->tableExists('employees')) {
            $fields = $db->getFieldData('employees');
            $existing = array_map(static fn($f) => $f->name, $fields);

            if (!in_array('gender', $existing, true)) {
                $this->forge->addColumn('employees', [
                    'gender' => [
                        'type'       => 'ENUM',
                        'constraint' => ['MALE', 'FEMALE', 'OTHER'],
                        'default'    => 'MALE',
                        'after'      => 'full_name',
                    ],
                ]);
            }
        }

        // 2. Add biometric_gender column to employee_face_templates
        if ($db->tableExists('employee_face_templates')) {
            $fields = $db->getFieldData('employee_face_templates');
            $existing = array_map(static fn($f) => $f->name, $fields);

            if (!in_array('biometric_gender', $existing, true)) {
                $this->forge->addColumn('employee_face_templates', [
                    'biometric_gender' => [
                        'type'       => 'ENUM',
                        'constraint' => ['MALE', 'FEMALE', 'OTHER'],
                        'default'    => 'MALE',
                        'after'      => 'vector_dimensions',
                    ],
                ]);
            }
        }

        // 3. Add biometric validation columns to attendance_events
        if ($db->tableExists('attendance_events')) {
            $fields = $db->getFieldData('attendance_events');
            $existing = array_map(static fn($f) => $f->name, $fields);

            $newCols = [];
            if (!in_array('confidence_score', $existing, true)) {
                $newCols['confidence_score'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                    'after'      => 'processing_status',
                ];
            }
            if (!in_array('matching_distance', $existing, true)) {
                $newCols['matching_distance'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                    'after'      => 'confidence_score',
                ];
            }
            if (!in_array('runner_up_distance', $existing, true)) {
                $newCols['runner_up_distance'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                    'after'      => 'matching_distance',
                ];
            }
            if (!in_array('ambiguity_delta', $existing, true)) {
                $newCols['ambiguity_delta'] = [
                    'type'       => 'DECIMAL',
                    'constraint' => '5,4',
                    'null'       => true,
                    'after'      => 'runner_up_distance',
                ];
            }
            if (!in_array('biometric_flag', $existing, true)) {
                $newCols['biometric_flag'] = [
                    'type'       => 'ENUM',
                    'constraint' => ['NORMAL', 'LOW_CONFIDENCE', 'AMBIGUOUS_DELTA', 'GENDER_MISMATCH', 'VERIFIED_STRICT'],
                    'default'    => 'NORMAL',
                    'after'      => 'ambiguity_delta',
                ];
            }

            if (!empty($newCols)) {
                $this->forge->addColumn('attendance_events', $newCols);
            }
        }

        // 4. Update core staff records to standard EMP-001, EMP-002, EMP-003, EMP-004 and proper gender
        if ($db->tableExists('employees')) {
            // EMP-001 Srinu Renangi (MALE)
            $db->table('employees')
                ->where('full_name LIKE', '%Srinu%')
                ->update(['employee_id' => 'EMP-001', 'gender' => 'MALE']);

            // EMP-002 Ramanaiah (MALE)
            $db->table('employees')
                ->where('full_name LIKE', '%RAMANAIAH%')
                ->update(['employee_id' => 'EMP-002', 'gender' => 'MALE']);

            // EMP-003 Vishnu (MALE)
            $db->table('employees')
                ->where('full_name LIKE', '%VISHNU%')
                ->update(['employee_id' => 'EMP-003', 'gender' => 'MALE']);

            // EMP-004 Madhavi (FEMALE)
            $db->table('employees')
                ->where('full_name LIKE', '%MADHAVI%')
                ->update(['employee_id' => 'EMP-004', 'gender' => 'FEMALE']);
        }

        // Sync biometric_gender in employee_face_templates
        if ($db->tableExists('employee_face_templates') && $db->tableExists('employees')) {
            $db->query("
                UPDATE employee_face_templates eft
                JOIN employees e ON e.id = eft.employee_id
                SET eft.biometric_gender = e.gender
            ");
        }
    }

    public function down()
    {
        if ($this->db->tableExists('employees')) {
            $fields = $this->db->getFieldData('employees');
            $existing = array_map(static fn($f) => $f->name, $fields);
            if (in_array('gender', $existing, true)) {
                $this->forge->dropColumn('employees', 'gender');
            }
        }

        if ($this->db->tableExists('employee_face_templates')) {
            $fields = $this->db->getFieldData('employee_face_templates');
            $existing = array_map(static fn($f) => $f->name, $fields);
            if (in_array('biometric_gender', $existing, true)) {
                $this->forge->dropColumn('employee_face_templates', 'biometric_gender');
            }
        }

        if ($this->db->tableExists('attendance_events')) {
            $fields = $this->db->getFieldData('attendance_events');
            $existing = array_map(static fn($f) => $f->name, $fields);
            $drop = array_intersect(['matching_distance', 'runner_up_distance', 'ambiguity_delta', 'biometric_flag'], $existing);
            if (!empty($drop)) {
                $this->forge->dropColumn('attendance_events', array_values($drop));
            }
        }
    }
}
