<?php

namespace Tests\Feature;

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\AttendanceReviewDecisionModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class AdminAttendanceReviewQueueTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected AttendanceEventModel $eventModel;
    protected AttendanceRecordModel $recordModel;
    protected AttendanceReviewDecisionModel $decisionModel;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $kioskModel;

    protected string $deviceId;
    protected string $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db            = Database::connect();
        $this->eventModel    = new AttendanceEventModel();
        $this->recordModel   = new AttendanceRecordModel();
        $this->decisionModel = new AttendanceReviewDecisionModel();
        $this->employeeModel = new EmployeeModel();
        $this->kioskModel    = new KioskDeviceModel();

        $this->deviceId   = EmployeeModel::generateUuidV4();
        $this->employeeId = EmployeeModel::generateUuidV4();

        $this->kioskModel->insert([
            'id'                  => $this->deviceId,
            'device_name'         => 'Review Test Kiosk',
            'device_identifier'   => 'REV-KIOSK-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-rev-test',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'REV-EMP-01',
            'full_name'   => 'Review Subject Alpha',
            'department'  => 'Science',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Insert admin user for foreign key satisfaction
        $this->db->table('admin_users')->where('id', 'adm-test-01')->delete();
        $this->db->table('admin_users')->insert([
            'id'            => 'adm-test-01',
            'username'      => 'AdminTester',
            'password_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
            'full_name'     => 'Admin Tester',
            'role'          => 'ROLE_ADMIN',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $this->db->table('attendance_review_decisions')->where('reviewer_username', 'AdminTester')->delete();
        $this->db->table('attendance_events')->where('device_id', $this->deviceId)->delete();
        $this->db->table('attendance_records')->where('employee_id', $this->employeeId)->delete();
        $this->db->table('kiosk_devices')->where('id', $this->deviceId)->delete();
        $this->db->table('employees')->where('id', $this->employeeId)->delete();
        $this->db->table('admin_users')->where('id', 'adm-test-01')->delete();
        parent::tearDown();
    }

    public function testManagerRoleCannotAccessReviewQueue()
    {
        // Manager role (non-admin) should be rejected with 403 Forbidden
        $result = $this->withSession([
            'admin_user_id'   => 'mgr-001',
            'admin_user_name' => 'ManagerBob',
            'admin_role'      => 'ROLE_MANAGER',
            'admin_logged_in' => true,
        ])->get('/admin/attendance/review');

        $result->assertStatus(403);
    }

    public function testAdminRoleCanAccessReviewQueue()
    {
        $result = $this->withSession([
            'admin_user_id'   => 'adm-001',
            'admin_user_name' => 'AdminAlice',
            'admin_role'      => 'ROLE_ADMIN',
            'admin_logged_in' => true,
        ])->get('/admin/attendance/review');

        $result->assertStatus(200);
        $result->assertSee('Held Attendance Events Review Queue');
    }

    public function testAdminApprovalAppliesToRecordsAndAuditLog()
    {
        $eventId = EmployeeModel::generateUuidV4();
        $workDate = '2026-11-03';

        // Insert a held event
        $this->eventModel->insert([
            'id'                     => $eventId,
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $this->employeeId,
            'device_id'              => $this->deviceId,
            'device_epoch_id'        => 'epoch-rev-test',
            'device_event_sequence'  => 1,
            'event_type'             => 'CHECK_IN',
            'terminal_mode'          => 'ENTRY_ONLY',
            'client_recorded_at'     => '2026-11-03 08:30:00',
            'server_received_at'     => '2026-11-03 09:00:00',
            'client_timezone'        => 'Asia/Kolkata',
            'client_monotonic_ms'    => 120000,
            'server_anchor_id'       => null,
            'browser_session_id'     => 'test-session-12345678',
            'device_wall_clock_at'   => '2026-11-03 08:30:00',
            'anchor_elapsed_ms'      => 120000,
            'clock_trust_status'     => 'UNVERIFIED_OFFLINE_TIME',
            'synchronization_source' => 'OFFLINE_SYNC',
            'payload_hash'           => hash('sha256', 'payload-test-1'),
            'processing_status'      => 'REVIEW_REQUIRED',
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        $security = service('security');
        $csrfToken = $security->getTokenName();
        $csrfHash  = $security->getHash();

        // Attempt approve with too short reason (< 5 chars)
        $shortReasonRes = $this->withSession([
            'admin_user_id'   => 'adm-test-01',
            'admin_user_name' => 'AdminTester',
            'admin_role'      => 'ROLE_ADMIN',
            'admin_logged_in' => true,
        ])->post('/admin/attendance/review/' . $eventId . '/approve', [
            $csrfToken => $csrfHash,
            'reason'   => 'ok',
        ]);
        $shortReasonRes->assertRedirect();
        $this->assertEquals(0, $this->decisionModel->where('event_id', $eventId)->countAllResults());

        // Refresh CSRF hash for next request
        $csrfHash = $security->getHash();

        // Approve with valid reason
        $approveRes = $this->withSession([
            'admin_user_id'   => 'adm-test-01',
            'admin_user_name' => 'AdminTester',
            'admin_role'      => 'ROLE_ADMIN',
            'admin_logged_in' => true,
        ])->post('/admin/attendance/review/' . $eventId . '/approve', [
            $csrfToken => $csrfHash,
            'reason'   => 'Manually confirmed staff arrived at 08:30 via front desk log.',
        ]);

        $approveRes->assertRedirect('/admin/attendance/review/' . $eventId);

        // Verify decision row created
        $decision = $this->decisionModel->where('event_id', $eventId)->first();
        $this->assertNotNull($decision);
        $this->assertEquals('APPROVED', $decision['decision']);
        $this->assertEquals('AdminTester', $decision['reviewer_username']);
        $this->assertNotEmpty($decision['applied_record_id']);

        // Verify aggregate record updated
        $record = $this->recordModel->where('employee_id', $this->employeeId)->where('work_date', $workDate)->first();
        $this->assertNotNull($record);
        $this->assertStringContainsString('2026-11-03', $record['check_in_time']);

        // Verify audit log created
        $auditLog = $this->db->table('admin_audit_logs')
            ->where('target_id', $eventId)
            ->where('action', 'ATTENDANCE_EVENT_APPROVED')
            ->get()
            ->getRowArray();
        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('AdminTester', $auditLog['actor_username'] ?? $auditLog['details']);

        // Verify original event row preserved
        $eventAfter = $this->eventModel->find($eventId);
        $this->assertEquals('REVIEW_REQUIRED', $eventAfter['processing_status']); // Ledger is append-only

        // Verify duplicate decision is rejected
        $csrfHash = $security->getHash();
        $dupRes = $this->withSession([
            'admin_user_id'   => 'adm-test-01',
            'admin_user_name' => 'AdminTester',
            'admin_role'      => 'ROLE_ADMIN',
            'admin_logged_in' => true,
        ])->post('/admin/attendance/review/' . $eventId . '/approve', [
            $csrfToken => $csrfHash,
            'reason'   => 'Attempting duplicate approval.',
        ]);
        $dupRes->assertRedirect();
    }

    public function testAdminRejectionPreservesAggregateUntouched()
    {
        $eventId = EmployeeModel::generateUuidV4();
        $workDate = '2026-11-03';

        // Insert a held event
        $this->eventModel->insert([
            'id'                     => $eventId,
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $this->employeeId,
            'device_id'              => $this->deviceId,
            'device_epoch_id'        => 'epoch-rev-test',
            'device_event_sequence'  => 2,
            'event_type'             => 'CHECK_OUT',
            'terminal_mode'          => 'EXIT_ONLY',
            'client_recorded_at'     => '2026-11-03 16:30:00',
            'server_received_at'     => '2026-11-03 17:00:00',
            'client_timezone'        => 'Asia/Kolkata',
            'client_monotonic_ms'    => 999999,
            'server_anchor_id'       => null,
            'browser_session_id'     => 'test-session-corrupt',
            'device_wall_clock_at'   => '2026-11-03 16:30:00',
            'anchor_elapsed_ms'      => 999999,
            'clock_trust_status'     => 'UNVERIFIED_OFFLINE_TIME',
            'synchronization_source' => 'OFFLINE_SYNC',
            'payload_hash'           => hash('sha256', 'payload-test-2'),
            'processing_status'      => 'REVIEW_REQUIRED',
            'created_at'             => date('Y-m-d H:i:s'),
        ]);

        $security = service('security');
        $csrfToken = $security->getTokenName();
        $csrfHash  = $security->getHash();

        // Reject with valid reason
        $rejectRes = $this->withSession([
            'admin_user_id'   => 'adm-test-01',
            'admin_user_name' => 'AdminTester',
            'admin_role'      => 'ROLE_ADMIN',
            'admin_logged_in' => true,
        ])->post('/admin/attendance/review/' . $eventId . '/reject', [
            $csrfToken => $csrfHash,
            'reason'   => 'Discrepancy in elapsed time: teacher left campus early.',
        ]);

        $rejectRes->assertRedirect('/admin/attendance/review/' . $eventId);

        // Verify decision row created
        $decision = $this->decisionModel->where('event_id', $eventId)->first();
        $this->assertNotNull($decision);
        $this->assertEquals('REJECTED', $decision['decision']);
        $this->assertNull($decision['applied_record_id']);

        // Verify attendance_records has NO record for this employee/date
        $record = $this->recordModel->where('employee_id', $this->employeeId)->where('work_date', $workDate)->first();
        $this->assertNull($record);

        // Verify audit log created
        $auditLog = $this->db->table('admin_audit_logs')
            ->where('target_id', $eventId)
            ->where('action', 'ATTENDANCE_EVENT_REJECTED')
            ->get()
            ->getRowArray();
        $this->assertNotNull($auditLog);
    }
}
