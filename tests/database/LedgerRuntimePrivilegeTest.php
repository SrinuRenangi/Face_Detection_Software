<?php

namespace Tests\Database;

use App\Models\EmployeeModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;

class LedgerRuntimePrivilegeTest extends CIUnitTestCase
{
    protected ?\mysqli $appConn = null;

    protected function setUp(): void
    {
        parent::setUp();

        // Connect to MariaDB as the restricted application user `edifies_app`
        $mysqli = mysqli_init();
        $mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);

        $targetDb = (ENVIRONMENT === 'testing') ? 'attendance_test' : 'attendance_dev';

        $connected = @$mysqli->real_connect(
            '127.0.0.1',
            'edifies_app',
            'EdifiesKioskApp2026!',
            $targetDb,
            3307
        );

        if (!$connected) {
            $this->markTestSkipped('Restricted user edifies_app connection failed: ' . mysqli_connect_error());
        }

        $this->appConn = $mysqli;

        // Ensure baseline employee, kiosk_device, and admin_user exist for foreign key references
        $empRes = $this->appConn->query("SELECT id FROM employees LIMIT 1");
        if (!$empRes || $empRes->num_rows === 0) {
            $empId = EmployeeModel::generateUuidV4();
            $now = date('Y-m-d H:i:s');
            $this->appConn->query("INSERT INTO employees (id, employee_id, full_name, department, status, created_at, updated_at) VALUES ('{$empId}', 'EMP-PRIV-01', 'Privilege Test Staff', 'Testing', 'ACTIVE', '{$now}', '{$now}')");
        }

        $devRes = $this->appConn->query("SELECT id FROM kiosk_devices LIMIT 1");
        if (!$devRes || $devRes->num_rows === 0) {
            $devId = EmployeeModel::generateUuidV4();
            $now = date('Y-m-d H:i:s');
            $this->appConn->query("INSERT INTO kiosk_devices (id, device_name, device_identifier, terminal_mode, device_epoch_id, last_event_sequence, status, created_at, updated_at) VALUES ('{$devId}', 'Priv Test Device', 'PRIV-DEV-01', 'ENTRY_ONLY', 'epoch-priv-test', 0, 'ACTIVE', '{$now}', '{$now}')");
        }

        $admRes = $this->appConn->query("SELECT id FROM admin_users LIMIT 1");
        if (!$admRes || $admRes->num_rows === 0) {
            $admId = EmployeeModel::generateUuidV4();
            $now = date('Y-m-d H:i:s');
            $this->appConn->query("INSERT INTO admin_users (id, username, password_hash, full_name, role, is_active, created_at, updated_at) VALUES ('{$admId}', 'admin_priv_tester', 'hash', 'Priv Admin', 'ROLE_ADMIN', 1, '{$now}', '{$now}')");
        }
    }

    protected function tearDown(): void
    {
        if ($this->appConn) {
            $this->appConn->close();
            $this->appConn = null;
        }
        parent::tearDown();
    }

    // 1. Read attendance_events (Allowed)
    public function testEdifiesAppCanSelectFromAttendanceEvents()
    {
        $res = $this->appConn->query("SELECT COUNT(*) as cnt FROM attendance_events");
        $this->assertNotFalse($res, 'Restricted user should be permitted to SELECT from attendance_events');
        $row = $res->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('cnt', $row);
    }

    // 2. Insert attendance_events (Allowed)
    public function testEdifiesAppCanInsertIntoAttendanceEvents()
    {
        $eventId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $empRes = $this->appConn->query("SELECT id FROM employees LIMIT 1");
        $devRes = $this->appConn->query("SELECT id FROM kiosk_devices LIMIT 1");
        $emp = $empRes ? $empRes->fetch_assoc() : null;
        $dev = $devRes ? $devRes->fetch_assoc() : null;

        if (!$emp || !$dev) {
            $this->markTestSkipped('Requires at least one employee and device in attendance_dev to test foreign-keyed insert.');
        }

        $epochId = 'epoch-priv-' . bin2hex(random_bytes(4));
        $seq = rand(1000, 999999);

        $sql = "INSERT INTO attendance_events (
            id, client_uuid, employee_id, device_id, device_epoch_id, device_event_sequence,
            event_type, terminal_mode, client_recorded_at, server_received_at, client_timezone,
            payload_hash, processing_status, created_at
        ) VALUES (
            '{$eventId}', '{$clientUuid}', '{$emp['id']}', '{$dev['id']}', '{$epochId}', {$seq},
            'CHECK_IN', 'ENTRY_ONLY', '{$now}', '{$now}', 'Asia/Kolkata',
            '" . hash('sha256', $clientUuid) . "', 'REVIEW_REQUIRED', '{$now}'
        )";

        $res = $this->appConn->query($sql);
        $this->assertTrue($res, 'Restricted user edifies_app should be permitted to INSERT into attendance_events: ' . $this->appConn->error);

        return $eventId;
    }

    // 3. Update attendance_events (Denied - Error 1142)
    public function testDatabaseEngineRejectsUpdateOnAttendanceEvents()
    {
        try {
            $this->appConn->query("UPDATE attendance_events SET notes = 'tampered' WHERE 1=1");
            $this->fail('Database engine should have rejected UPDATE query on attendance_events for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: UPDATE command denied to user edifies_app');
            $this->assertStringContainsString('UPDATE command denied to user', $e->getMessage());
        }
    }

    // 4. Delete attendance_events (Denied - Error 1142)
    public function testDatabaseEngineRejectsDeleteOnAttendanceEvents()
    {
        try {
            $this->appConn->query("DELETE FROM attendance_events WHERE 1=1");
            $this->fail('Database engine should have rejected DELETE query on attendance_events for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: DELETE command denied to user edifies_app');
            $this->assertStringContainsString('DELETE command denied to user', $e->getMessage());
        }
    }

    // 5. Read attendance_review_decisions (Allowed)
    public function testEdifiesAppCanSelectFromAttendanceReviewDecisions()
    {
        $res = $this->appConn->query("SELECT COUNT(*) as cnt FROM attendance_review_decisions");
        $this->assertNotFalse($res, 'Restricted user should be permitted to SELECT from attendance_review_decisions');
        $row = $res->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertArrayHasKey('cnt', $row);
    }

    // 6. Insert attendance_review_decisions (Allowed)
    public function testEdifiesAppCanInsertIntoAttendanceReviewDecisions()
    {
        // First create a prerequisite event using edifies_app
        $eventId = $this->testEdifiesAppCanInsertIntoAttendanceEvents();
        $adminRes = $this->appConn->query("SELECT id, username FROM admin_users LIMIT 1");
        $admin = $adminRes ? $adminRes->fetch_assoc() : null;

        if (!$admin) {
            $this->markTestSkipped('Requires at least one admin_user to test review decisions.');
        }

        $decisionId = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO attendance_review_decisions (
            id, event_id, decision, reviewer_id, reviewer_username, review_reason, decided_at, created_at
        ) VALUES (
            '{$decisionId}', '{$eventId}', 'APPROVED', '{$admin['id']}', '{$admin['username']}', 'Verified by supervisor', '{$now}', '{$now}'
        )";

        $res = $this->appConn->query($sql);
        $this->assertTrue($res, 'Restricted user edifies_app should be permitted to INSERT into attendance_review_decisions: ' . $this->appConn->error);

        return $decisionId;
    }

    // 7. Update attendance_review_decisions (Denied - Error 1142)
    public function testDatabaseEngineRejectsUpdateOnAttendanceReviewDecisions()
    {
        try {
            $this->appConn->query("UPDATE attendance_review_decisions SET review_reason = 'tampered' WHERE 1=1");
            $this->fail('Database engine should have rejected UPDATE query on attendance_review_decisions for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: UPDATE command denied on attendance_review_decisions');
            $this->assertStringContainsString('UPDATE command denied to user', $e->getMessage());
        }
    }

    // 8. Delete attendance_review_decisions (Denied - Error 1142)
    public function testDatabaseEngineRejectsDeleteOnAttendanceReviewDecisions()
    {
        try {
            $this->appConn->query("DELETE FROM attendance_review_decisions WHERE 1=1");
            $this->fail('Database engine should have rejected DELETE query on attendance_review_decisions for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: DELETE command denied on attendance_review_decisions');
            $this->assertStringContainsString('DELETE command denied to user', $e->getMessage());
        }
    }

    // 9. Update attendance_records (Allowed when required)
    public function testEdifiesAppPermittedToUpdateMutableAttendanceRecords()
    {
        $recId = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $empRes = $this->appConn->query("SELECT id FROM employees LIMIT 1");
        $devRes = $this->appConn->query("SELECT id FROM kiosk_devices LIMIT 1");
        $emp = $empRes ? $empRes->fetch_assoc() : null;
        $dev = $devRes ? $devRes->fetch_assoc() : null;

        if (!$emp) {
            $this->markTestSkipped('Requires at least one employee to test attendance_records update.');
        }

        $devIdVal = $dev ? "'{$dev['id']}'" : "NULL";
        $uniqueWorkDate = '2099-01-' . sprintf('%02d', rand(1, 28));
        $insertSql = "INSERT INTO attendance_records (
            id, employee_id, work_date, check_in_time, status, device_id, sync_source, is_corrected, version, created_at, updated_at
        ) VALUES (
            '{$recId}', '{$emp['id']}', '{$uniqueWorkDate}', '{$now}', 'CHECKED_IN', {$devIdVal}, 'OFFLINE_SYNC', 0, 1, '{$now}', '{$now}'
        )";

        $insRes = $this->appConn->query($insertSql);
        $this->assertTrue($insRes, 'edifies_app should be able to insert into attendance_records');

        $updRes = $this->appConn->query("UPDATE attendance_records SET version = 2, is_corrected = 1 WHERE id = '{$recId}'");
        $this->assertTrue($updRes, 'edifies_app MUST be permitted to UPDATE mutable operational table attendance_records');

        // Clean up mutable record
        $this->appConn->query("DELETE FROM attendance_records WHERE id = '{$recId}'");
    }

    // 10. Insert admin_audit_logs (Allowed)
    public function testEdifiesAppCanInsertIntoAdminAuditLogs()
    {
        $logId = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $sql = "INSERT INTO admin_audit_logs (
            id, actor_username, action, details, target_type, target_id, ip_address, created_at
        ) VALUES (
            '{$logId}', 'edifies_app_test', 'TEST_ACTION', 'Privilege audit test entry', 'TEST', '123', '127.0.0.1', '{$now}'
        )";

        $res = $this->appConn->query($sql);
        $this->assertTrue($res, 'Restricted user edifies_app should be permitted to INSERT into admin_audit_logs: ' . $this->appConn->error);
    }

    // 11. Update admin_audit_logs (Denied - Error 1142)
    public function testDatabaseEngineRejectsUpdateOnAdminAuditLogs()
    {
        try {
            $this->appConn->query("UPDATE admin_audit_logs SET details = 'tampered' WHERE 1=1");
            $this->fail('Database engine should have rejected UPDATE query on admin_audit_logs for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: UPDATE command denied on admin_audit_logs');
            $this->assertStringContainsString('UPDATE command denied to user', $e->getMessage());
        }
    }

    // 12. Delete admin_audit_logs (Denied - Error 1142)
    public function testDatabaseEngineRejectsDeleteOnAdminAuditLogs()
    {
        try {
            $this->appConn->query("DELETE FROM admin_audit_logs WHERE 1=1");
            $this->fail('Database engine should have rejected DELETE query on admin_audit_logs for edifies_app');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(1142, $e->getCode(), 'Expected MariaDB Error 1142: DELETE command denied on admin_audit_logs');
            $this->assertStringContainsString('DELETE command denied to user', $e->getMessage());
        }
    }

    // 13. Application Workflow: Prove Approval Succeeds with edifies_app Without Modifying Original Event
    public function testApplicationApprovalSucceedsUnderRestrictedAccountWithoutUpdatingEvent()
    {
        // 1. Create a held event using edifies_app
        $eventId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $empRes = $this->appConn->query("SELECT id FROM employees LIMIT 1");
        $devRes = $this->appConn->query("SELECT id FROM kiosk_devices LIMIT 1");
        $adminRes = $this->appConn->query("SELECT id, username FROM admin_users LIMIT 1");

        $emp = $empRes->fetch_assoc();
        $dev = $devRes->fetch_assoc();
        $admin = $adminRes->fetch_assoc();

        $epochId = 'epoch-appr-' . bin2hex(random_bytes(4));
        $seq = rand(10000, 999999);

        $sql = "INSERT INTO attendance_events (
            id, client_uuid, employee_id, device_id, device_epoch_id, device_event_sequence,
            event_type, terminal_mode, client_recorded_at, server_received_at, client_timezone,
            payload_hash, processing_status, notes, created_at
        ) VALUES (
            '{$eventId}', '{$clientUuid}', '{$emp['id']}', '{$dev['id']}', '{$epochId}', {$seq},
            'CHECK_IN', 'ENTRY_ONLY', '{$now}', '{$now}', 'Asia/Kolkata',
            '" . hash('sha256', $clientUuid) . "', 'REVIEW_REQUIRED', 'Initial held reason: clock unverified', '{$now}'
        )";
        $this->appConn->query($sql);

        // Fetch original event state before approval
        $origEventRes = $this->appConn->query("SELECT * FROM attendance_events WHERE id = '{$eventId}'");
        $origEvent = $origEventRes->fetch_assoc();
        $this->assertSame('REVIEW_REQUIRED', $origEvent['processing_status']);
        $this->assertSame('Initial held reason: clock unverified', $origEvent['notes']);

        // 2. Execute approval workflow via AttendanceSyncService
        $syncService = new AttendanceSyncService();
        $result = $syncService->approveHeldEvent($eventId, $admin['id'], $admin['username'], 'Supervisor confirmed staff present in classroom.');

        $this->assertSame('APPROVED', $result['status'], 'Approval must succeed without database errors: ' . ($result['error'] ?? ''));
        $this->assertNotEmpty($result['decision_id']);
        $this->assertNotEmpty($result['applied_record_id']);

        // 3. Verify ORIGINAL event row is 100% UNCHANGED (Immutable Original Ledger)
        $afterEventRes = $this->appConn->query("SELECT * FROM attendance_events WHERE id = '{$eventId}'");
        $afterEvent = $afterEventRes->fetch_assoc();

        $this->assertSame('REVIEW_REQUIRED', $afterEvent['processing_status'], 'Original processing_status must NOT be altered!');
        $this->assertSame('Initial held reason: clock unverified', $afterEvent['notes'], 'Original notes must NOT be altered!');
        $this->assertSame($origEvent['client_recorded_at'], $afterEvent['client_recorded_at']);
        $this->assertSame($origEvent['server_received_at'], $afterEvent['server_received_at']);

        // 4. Verify decision is recorded in attendance_review_decisions
        $decRes = $this->appConn->query("SELECT * FROM attendance_review_decisions WHERE event_id = '{$eventId}'");
        $decision = $decRes->fetch_assoc();
        $this->assertNotNull($decision);
        $this->assertSame('APPROVED', $decision['decision']);
        $this->assertSame($admin['id'], $decision['reviewer_id']);
        $this->assertSame('Supervisor confirmed staff present in classroom.', $decision['review_reason']);
        $this->assertSame($result['applied_record_id'], $decision['applied_record_id']);

        // 5. Verify daily summary was updated
        $summaryRes = $this->appConn->query("SELECT * FROM attendance_records WHERE id = '{$result['applied_record_id']}'");
        $summary = $summaryRes->fetch_assoc();
        $this->assertNotNull($summary);
        $this->assertSame('CHECKED_IN', $summary['status']);
    }

    // 14. Application Workflow: Prove Rejection Succeeds with edifies_app Without Modifying Original Event
    public function testApplicationRejectionSucceedsUnderRestrictedAccountWithoutUpdatingEvent()
    {
        $eventId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $empRes = $this->appConn->query("SELECT id FROM employees LIMIT 1");
        $devRes = $this->appConn->query("SELECT id FROM kiosk_devices LIMIT 1");
        $adminRes = $this->appConn->query("SELECT id, username FROM admin_users LIMIT 1");

        $emp = $empRes->fetch_assoc();
        $dev = $devRes->fetch_assoc();
        $admin = $adminRes->fetch_assoc();

        $epochId = 'epoch-rej-' . bin2hex(random_bytes(4));
        $seq = rand(10000, 999999);

        $sql = "INSERT INTO attendance_events (
            id, client_uuid, employee_id, device_id, device_epoch_id, device_event_sequence,
            event_type, terminal_mode, client_recorded_at, server_received_at, client_timezone,
            payload_hash, processing_status, notes, created_at
        ) VALUES (
            '{$eventId}', '{$clientUuid}', '{$emp['id']}', '{$dev['id']}', '{$epochId}', {$seq},
            'CHECK_IN', 'ENTRY_ONLY', '{$now}', '{$now}', 'Asia/Kolkata',
            '" . hash('sha256', $clientUuid) . "', 'REVIEW_REQUIRED', 'Initial held reason: clock unverified', '{$now}'
        )";
        $this->appConn->query($sql);

        // Fetch original count of attendance_records
        $recCountBefore = (int) $this->appConn->query("SELECT COUNT(*) FROM attendance_records")->fetch_row()[0];

        // Execute rejection workflow
        $syncService = new AttendanceSyncService();
        $result = $syncService->rejectHeldEvent($eventId, $admin['id'], $admin['username'], 'Tampered offline punch detected and confirmed.');

        $this->assertSame('REJECTED', $result['status'], 'Rejection must succeed: ' . ($result['error'] ?? ''));

        // Verify ORIGINAL event row is 100% UNCHANGED
        $afterEventRes = $this->appConn->query("SELECT * FROM attendance_events WHERE id = '{$eventId}'");
        $afterEvent = $afterEventRes->fetch_assoc();
        $this->assertSame('REVIEW_REQUIRED', $afterEvent['processing_status'], 'Original processing_status must NOT be altered!');
        $this->assertSame('Initial held reason: clock unverified', $afterEvent['notes'], 'Original notes must NOT be altered!');

        // Verify decision in attendance_review_decisions
        $decRes = $this->appConn->query("SELECT * FROM attendance_review_decisions WHERE event_id = '{$eventId}'");
        $decision = $decRes->fetch_assoc();
        $this->assertNotNull($decision);
        $this->assertSame('REJECTED', $decision['decision']);
        $this->assertNull($decision['applied_record_id']);

        // Verify attendance_records count completely UNCHANGED
        $recCountAfter = (int) $this->appConn->query("SELECT COUNT(*) FROM attendance_records")->fetch_row()[0];
        $this->assertSame($recCountBefore, $recCountAfter, 'Rejection must leave attendance_records completely untouched!');
    }
}
