<?php

namespace Tests\Database;

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class AttendanceConcurrencySafetyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected AttendanceRecordModel $attendanceModel;
    protected AttendanceEventModel $eventModel;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $kioskModel;
    protected AttendanceSyncService $syncService;

    protected string $kioskIdA;
    protected string $kioskIdB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->kioskIdA = EmployeeModel::generateUuidV4();
        $this->kioskIdB = EmployeeModel::generateUuidV4();

        $this->kioskModel->insert([
            'id'                => $this->kioskIdA,
            'device_name'       => 'Concurrency Kiosk A',
            'device_identifier' => 'CONCUR-A-' . bin2hex(random_bytes(3)),
            'terminal_mode'     => 'ENTRY_ONLY',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->kioskModel->insert([
            'id'                => $this->kioskIdB,
            'device_name'       => 'Concurrency Kiosk B',
            'device_identifier' => 'CONCUR-B-' . bin2hex(random_bytes(3)),
            'terminal_mode'     => 'EXIT_ONLY',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->kioskModel->delete($this->kioskIdA);
        $this->kioskModel->delete($this->kioskIdB);
        parent::tearDown();
    }

    public function testFiveSimultaneousEncountersNeverCreateDuplicateDailyRecords()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'CONCUR-EMP-01',
            'full_name'   => 'Concurrency Subject Alpha',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            $punchTime = date('Y-m-d H:i:s', time() - 3600);
            // Simulate 5 independent encounters arriving rapidly
            $results = [];
            for ($i = 1; $i <= 5; $i++) {
                $results[] = $this->syncService->processPunch([
                    'client_uuid'           => EmployeeModel::generateUuidV4(),
                    'employee_id'           => $empUuid,
                    'device_id'             => $this->kioskIdA,
                    'device_epoch_id'       => 'epoch-concur-1',
                    'device_event_sequence' => $i,
                    'timestamp'             => $punchTime,
                ]);
            }

            // Exactly 1 summary record must exist for this employee today
            $dailyRecords = $this->attendanceModel
                ->where('employee_id', $empUuid)
                ->where('work_date', date('Y-m-d'))
                ->findAll();

            $this->assertCount(1, $dailyRecords);
            $this->assertSame('CREATED', $results[0]['status']);

            // Subsequent punches are classified as DUPLICATE_IGNORED and PRESENCE_CONFIRMED
            for ($i = 1; $i < 5; $i++) {
                $this->assertSame('DUPLICATE_IGNORED', $results[$i]['status']);
                $this->assertSame('PRESENCE_CONFIRMED', $results[$i]['event_type']);
            }

            // 5 events exist in the immutable attendance_events ledger
            $events = $this->eventModel->where('employee_id', $empUuid)->findAll();
            $this->assertCount(5, $events);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testFiveIdenticalClientUuidsReturnIdempotentConfirmedWithoutDuplicateEvents()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'CONCUR-EMP-02',
            'full_name'   => 'Retry Subject Beta',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $sameClientUuid = EmployeeModel::generateUuidV4();
        $punchTime = date('Y-m-d H:i:s', time() - 3600);

        try {
            $first = $this->syncService->processPunch([
                'client_uuid'           => $sameClientUuid,
                'employee_id'           => $empUuid,
                'device_id'             => $this->kioskIdA,
                'device_epoch_id'       => 'epoch-concur-2',
                'device_event_sequence' => 1,
                'timestamp'             => $punchTime,
            ]);
            $this->assertSame('CREATED', $first['status']);

            // 4 exact retries
            for ($i = 1; $i <= 4; $i++) {
                $retry = $this->syncService->processPunch([
                    'client_uuid'           => $sameClientUuid,
                    'employee_id'           => $empUuid,
                    'device_id'             => $this->kioskIdA,
                    'device_epoch_id'       => 'epoch-concur-2',
                    'device_event_sequence' => 1,
                    'timestamp'             => $punchTime,
                ]);
                $this->assertSame('IDEMPOTENT_CONFIRMED', $retry['status']);
                $this->assertSame($first['event_id'], $retry['event_id']);
            }

            // Exactly 1 event exists in ledger
            $events = $this->eventModel->where('client_uuid', $sameClientUuid)->findAll();
            $this->assertCount(1, $events);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testSameClientUuidWithConflictingPayloadIsRejectedAndAudited()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'CONCUR-EMP-03',
            'full_name'   => 'Conflict Subject Gamma',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $collidingUuid = EmployeeModel::generateUuidV4();

        try {
            $punchTime1 = date('Y-m-d H:i:s', time() - 3600);
            $punchTime2 = date('Y-m-d H:i:s', time() - 3300);

            // First valid punch
            $first = $this->syncService->processPunch([
                'client_uuid' => $collidingUuid,
                'employee_id' => $empUuid,
                'device_id'   => $this->kioskIdA,
                'timestamp'   => $punchTime1,
            ]);
            $this->assertSame('CREATED', $first['status']);

            // Second punch submits SAME client_uuid but DIFFERENT timestamp
            $conflict = $this->syncService->processPunch([
                'client_uuid' => $collidingUuid,
                'employee_id' => $empUuid,
                'device_id'   => $this->kioskIdA,
                'timestamp'   => $punchTime2,
            ]);

            $this->assertSame('CONFLICT_PAYLOAD_MISMATCH', $conflict['status']);
            $this->assertNotEmpty($conflict['error']);

            // Verify audit log entry was written
            $auditLog = $this->db->table('admin_audit_logs')
                ->where('action', 'CLIENT_UUID_PAYLOAD_MISMATCH')
                ->where('target_id', $first['event_id'])
                ->get()
                ->getRowArray();

            $this->assertNotNull($auditLog);
        } finally {
            $this->db->table('admin_audit_logs')->where('action', 'CLIENT_UUID_PAYLOAD_MISMATCH')->delete();
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testTwoDevicesSubmittingEventsForSameEmployeeReconcileCleanly()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'CONCUR-EMP-04',
            'full_name'   => 'Cross Device Subject Delta',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            $punchTimeA = date('Y-m-d H:i:s', time() - 7200);
            $punchTimeB = date('Y-m-d H:i:s', time() - 3600);

            // Device A (Entry Lobby): records check-in 2 hours ago
            $punchA = $this->syncService->processPunch([
                'client_uuid' => EmployeeModel::generateUuidV4(),
                'employee_id' => $empUuid,
                'device_id'   => $this->kioskIdA,
                'timestamp'   => $punchTimeA,
            ]);
            $this->assertSame('CREATED', $punchA['status']);

            // Device B (Exit Gate): records check-out 1 hour ago
            $punchB = $this->syncService->processPunch([
                'client_uuid' => EmployeeModel::generateUuidV4(),
                'employee_id' => $empUuid,
                'device_id'   => $this->kioskIdB,
                'timestamp'   => $punchTimeB,
            ]);
            $this->assertSame('CHECK_OUT_RECORDED', $punchB['status']);

            // Check summary row
            $summary = $this->attendanceModel->find($punchA['record_id']);
            $this->assertSame($punchTimeA, $summary['check_in_time']);
            $this->assertSame($punchTimeB, $summary['check_out_time']);
            $this->assertSame('CHECKED_OUT', $summary['status']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }
}

