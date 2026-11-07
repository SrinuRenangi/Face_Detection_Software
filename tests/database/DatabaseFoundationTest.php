<?php

namespace Tests\Database;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class DatabaseFoundationTest extends CIUnitTestCase
{
    protected $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
    }

    public function testDatabaseConnectionIsHealthy()
    {
        $this->assertNotNull($this->db);
        $this->db->initialize();
        $this->assertNotFalse($this->db->connID);
        $platform = $this->db->getPlatform();
        $this->assertNotEmpty($platform);
        $version = $this->db->getVersion();
        $this->assertNotEmpty($version);
    }

    public function testRequiredDatabaseTablesExist()
    {
        $expectedTables = [
            'admin_users',
            'admin_audit_logs',
            'employees',
            'employee_face_templates',
            'kiosk_devices',
            'kiosk_pairing_codes',
            'attendance_records',
            'attendance_corrections',
            'idempotency_keys',
        ];

        $tables = $this->db->listTables();

        foreach ($expectedTables as $table) {
            $this->assertContains(
                $table,
                $tables,
                "Expected table '{$table}' is missing from the database."
            );
        }
    }

    public function testIdempotencyKeyUniqueConstraint()
    {
        $builder = $this->db->table('idempotency_keys');

        $testKey = 'test-idem-' . bin2hex(random_bytes(8));
        $now = date('Y-m-d H:i:s');

        // First insert must succeed
        $success1 = $builder->insert([
            'key_value'     => $testKey,
            'endpoint'      => '/api/kiosk/attendance/punch',
            'status_code'   => 200,
            'response_body' => '{"status":"ok"}',
            'created_at'    => $now,
        ]);
        $this->assertTrue($success1);

        // Second insert with duplicate key must fail / throw exception
        $duplicateFailed = false;
        try {
            $builder->insert([
                'key_value'     => $testKey,
                'endpoint'      => '/api/kiosk/attendance/punch',
                'status_code'   => 200,
                'response_body' => '{"status":"duplicate"}',
                'created_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            $duplicateFailed = true;
        }

        $this->assertTrue($duplicateFailed, 'Inserting duplicate idempotency key must violate unique primary constraint.');

        // Cleanup
        $builder->where('key_value', $testKey)->delete();
    }

    public function testSingleDailyAttendanceRecordUniqueConstraint()
    {
        $empBuilder = $this->db->table('employees');
        $attBuilder = $this->db->table('attendance_records');

        $empId = 'test-emp-' . bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');

        $empBuilder->insert([
            'id'          => $empId,
            'employee_id' => 'EMP-TEST-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Test Constraint Employee',
            'department'  => 'Testing',
            'status'      => 'ACTIVE',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $workDate = '2026-11-03';
        $attId1 = 'att-1-' . bin2hex(random_bytes(6));
        $attId2 = 'att-2-' . bin2hex(random_bytes(6));

        // First shift on workDate
        $ok1 = $attBuilder->insert([
            'id'            => $attId1,
            'employee_id'   => $empId,
            'work_date'     => $workDate,
            'check_in_time' => $now,
            'status'        => 'CHECKED_IN',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $this->assertTrue($ok1);

        // Attempting second record on same workDate for same employee must violate unique constraint
        $uniqueViolated = false;
        try {
            $attBuilder->insert([
                'id'            => $attId2,
                'employee_id'   => $empId,
                'work_date'     => $workDate,
                'check_in_time' => $now,
                'status'        => 'CHECKED_IN',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } catch (\Throwable $e) {
            $uniqueViolated = true;
        }

        $this->assertTrue($uniqueViolated, 'Database must enforce single daily attendance record per employee.');

        // Cleanup
        $attBuilder->where('id', $attId1)->delete();
        $empBuilder->where('id', $empId)->delete();
    }
}
