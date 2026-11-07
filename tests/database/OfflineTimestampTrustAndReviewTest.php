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

class OfflineTimestampTrustAndReviewTest extends CIUnitTestCase
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

    protected string $kioskId;
    protected string $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db              = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->kioskId    = EmployeeModel::generateUuidV4();
        $this->employeeId = EmployeeModel::generateUuidV4();

        $this->kioskModel->insert([
            'id'                  => $this->kioskId,
            'device_name'         => 'Trust Test Terminal',
            'device_identifier'   => 'TRUST-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-trust-test',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'TRUST-EMP-01',
            'full_name'   => 'Trust Subject Gamma',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('attendance_events')->where('employee_id', $this->employeeId)->delete();
        $this->db->table('attendance_records')->where('employee_id', $this->employeeId)->delete();
        $this->employeeModel->delete($this->employeeId);
        $this->kioskModel->delete($this->kioskId);
        parent::tearDown();
    }

    /**
     * Test Case 1: Time Manipulated (clock rolled backwards in offline queue).
     * client_synced_at is earlier than client_recorded_at.
     * Must be held for review, and must NOT modify attendance_records.
     */
    public function testNegativeQueueDurationFlagsTimeManipulatedAndDoesNotModifyRecords()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $punchTime  = date('Y-m-d H:i:s', time() - 3600);
        // Device sync time is 1 hour BEFORE the recorded punch time (impossible without manual clock manipulation)
        $syncedTime = date('Y-m-d H:i:s', time() - 7200);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $punchTime,
            'client_synced_at'      => $syncedTime,
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        // Daily attendance record must NOT exist
        $records = $this->attendanceModel
            ->where('employee_id', $this->employeeId)
            ->where('work_date', date('Y-m-d'))
            ->findAll();
        $this->assertCount(0, $records);

        // Raw event is stored in attendance_events with status REVIEW_REQUIRED and trust TIME_MANIPULATED
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('TIME_MANIPULATED', $event['clock_trust_status']);
    }

    /**
     * Test Case 2: Suspicious Drift (device clock at sync moment is heavily skewed > 5 min).
     */
    public function testSkewedSyncClockFlagsSuspiciousDriftAndDoesNotModifyRecords()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $punchTime  = date('Y-m-d H:i:s', time() - 3600);
        // Synced time is skewed by 30 minutes relative to real time
        $syncedTime = date('Y-m-d H:i:s', time() + 1800);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $punchTime,
            'client_synced_at'      => $syncedTime,
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 2,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('SUSPICIOUS_DRIFT', $event['clock_trust_status']);
    }

    /**
     * Test Case 3: Closed Period Modification Protection.
     * Offline punch from 5 days ago arrives.
     * Must be stored in ledger as REVIEW_REQUIRED, and must NOT insert or modify attendance_records.
     */
    public function testClosedAttendancePeriodHeldForReviewWithoutModifyingDailySummary()
    {
        $clientUuid   = EmployeeModel::generateUuidV4();
        $fiveDaysAgo  = date('Y-m-d 08:30:00', strtotime('-5 days'));
        $fiveDaysDate = date('Y-m-d', strtotime('-5 days'));

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $fiveDaysAgo,
            'client_synced_at'      => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 3,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        // Daily summary for that closed date must NOT be created
        $records = $this->attendanceModel
            ->where('employee_id', $this->employeeId)
            ->where('work_date', $fiveDaysDate)
            ->findAll();
        $this->assertCount(0, $records);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
    }

    /**
     * Test Case 4: Legitimate Offline Queue Delay (e.g. 2 hours in queue with valid server time anchor).
     * Must be accepted and processed cleanly as NORMAL_SYNC without false review holding.
     */
    public function testLegitimateOfflineQueueDelayProcessedNormally()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $twoHoursAgo = date('Y-m-d H:i:s', time() - 7200);
        $syncTimeNow = date('Y-m-d H:i:s'); // Device clock is accurate at sync moment

        // Issue defensible server-time anchor for the 2-hour offline duration
        $anchorId = EmployeeModel::generateUuidV4();
        $twoHoursAgoUtc = (new \DateTimeImmutable($twoHoursAgo, new \DateTimeZone('Asia/Kolkata')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $deviceRow = $this->db->table('kiosk_devices')->where('id', $this->kioskId)->get()->getRowArray();
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->kioskId,
            'device_epoch_id' => $deviceRow['device_epoch_id'] ?? 'epoch-default-1',
            'server_utc_time' => $twoHoursAgoUtc,
            'expires_at'      => date('Y-m-d H:i:s', strtotime('+48 hours')),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $twoHoursAgo,
            'client_synced_at'      => $syncTimeNow,
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 0,
            'browser_session_id'    => 'session-offline-valid-1',
            'device_wall_clock_at'  => $twoHoursAgo,
            'device_event_sequence' => 4,
        ]);

        $this->assertSame('CREATED', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('PROCESSED', $event['processing_status']);
        $this->assertSame('NORMAL_SYNC', $event['clock_trust_status']);

        // Daily attendance record is successfully created
        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(1, $records);
    }

    /**
     * Test Case 5: Future Monotonic Anchor Drift (> 5s ahead of server time).
     * Must be flagged as SUSPICIOUS_DRIFT and HELD_FOR_REVIEW without modifying daily summary.
     */
    public function testFutureMonotonicAnchorDriftHeldForReviewWithSuspiciousDrift()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $utcTz      = new \DateTimeZone('UTC');
        $kolkataTz  = new \DateTimeZone('Asia/Kolkata');

        // Server anchor created 10s ago
        $anchorId = EmployeeModel::generateUuidV4();
        $server10sAgoUtc = (new \DateTimeImmutable('-10 seconds', $utcTz))->format('Y-m-d H:i:s');
        $deviceRow = $this->db->table('kiosk_devices')->where('id', $this->kioskId)->get()->getRowArray();
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->kioskId,
            'device_epoch_id' => $deviceRow['device_epoch_id'] ?? 'epoch-default-1',
            'server_utc_time' => $server10sAgoUtc,
            'expires_at'      => (new \DateTimeImmutable('+48 hours', $utcTz))->format('Y-m-d H:i:s'),
            'created_at'      => $server10sAgoUtc,
        ]);

        $punchTimeKolkata = (new \DateTimeImmutable('now', $kolkataTz))->format('Y-m-d H:i:s');

        // Monotonic elapsed reports 60s ahead -> expectedUtcEpoch > serverEpochTime + 5s!
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $punchTimeKolkata,
            'client_synced_at'      => $punchTimeKolkata,
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 60000,
            'device_event_sequence' => 5,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('SUSPICIOUS_DRIFT', $event['clock_trust_status']);

        // Daily summary must NOT exist
        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);

        // Cleanup anchor
        $this->db->table('kiosk_time_anchors')->where('id', $anchorId)->delete();
    }
}

