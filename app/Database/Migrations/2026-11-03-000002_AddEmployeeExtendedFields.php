<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddEmployeeExtendedFields extends Migration
{
    public function up()
    {
        $fields = [
            'designation' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'default'    => 'Staff',
                'after'      => 'department',
            ],
            'joining_date' => [
                'type'  => 'DATE',
                'null'  => true,
                'after' => 'designation',
            ],
            'contact_phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
                'after'      => 'joining_date',
            ],
        ];

        $this->forge->addColumn('employees', $fields);
        $this->forge->addKey('designation');
    }

    public function down()
    {
        $this->forge->dropColumn('employees', ['contact_phone', 'joining_date', 'designation']);
    }
}

