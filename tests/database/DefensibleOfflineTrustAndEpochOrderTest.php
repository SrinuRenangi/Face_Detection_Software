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

class DefensibleOfflineTrustAndEpochOrderTest extends CIUnitTestCase
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

    protected string $deviceId;
    protected string $employeeId;
    protected string $deviceEpochId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->deviceId      = EmployeeModel::generateUuidV4();
        $this->employeeId    = EmployeeModel::generateUuidV4();
        $this->deviceEpochId = 'epoch-defensible-' . bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');

        // Create test device
        $this->kioskModel->insert([
            'id'                  => $this->deviceId,
            'device_name'         => 'Defensible Trust Terminal',
            'device_identifier'   => 'DEV-TRUST-' . bin2hex(random_bytes(3)),
            'device_type'         => 'TABLET',
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => $this->deviceEpochId,
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'paired_at'           => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        // Create test employee
        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'EMP-TRUST-' . bin2hex(random_bytes(3)),
            'full_name'   => 'Trust Evaluation Subject',
            'status'      => 'ACTIVE',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('kiosk_time_anchors')->where('device_id', $this->deviceId)->delete();
        $this->db->table('attendance_records')->where('employee_id', $this->employeeId)->delete();
        $this->db->table('attendance_events')->where('device_id', $this->deviceId)->delete();
        $this->kioskModel->delete($this->deviceId);
        $this->employeeModel->delete($this->employeeId);
        parent::tearDown();
    }

    /**
     * Scenario 1: Normal Online Event
     */
    public function testScenario1_NormalOnlineEventProcessedNormally()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $now,
            'sync_source'           => 'DIRECT_ONLINE',
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('CREATED', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('TRUSTED_SERVER_TIME', $event['clock_trust_status']);
        $this->assertSame('PROCESSED', $event['processing_status']);

        $record = $this->attendanceModel->where('employee_id', $this->employeeId)->first();
        $this->assertNotNull($record);
    }

    /**
     * Scenario 2: Two-Hour Offline Event in the Same Valid Browsing Session
     */
    public function testScenario2_TwoHourOfflineEventWithValidServerAnchorProcessedNormally()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $twoHoursAgoSec = time() - 7200;
        $twoHoursAgo = date('Y-m-d H:i:s', $twoHoursAgoSec);
        $syncTimeNow = date('Y-m-d H:i:s');

        // Server anchor captured 2 hours ago when device was online
        $anchorId = EmployeeModel::generateUuidV4();
        $anchorUtc = gmdate('Y-m-d H:i:s', $twoHoursAgoSec);
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->deviceId,
            'device_epoch_id' => $this->deviceEpochId,
            'server_utc_time' => $anchorUtc,
            'expires_at'      => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $twoHoursAgo,
            'client_synced_at'      => $syncTimeNow,
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 0, // Event contemporaneous with anchor
            'browser_session_id'    => 'session-valid-abc',
            'device_wall_clock_at'  => $twoHoursAgo,
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('CREATED', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('NORMAL_SYNC', $event['clock_trust_status']);
        $this->assertSame('PROCESSED', $event['processing_status']);
    }

    /**
     * Scenario 3: Offline Event After Browser Reload
     */
    public function testScenario3_OfflineEventAfterBrowserReloadFlaggedForReview()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $now,
            'sync_source'           => 'OFFLINE_SYNC',
            'browser_restarted'     => true, // Reloaded while offline!
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        // Daily aggregate MUST NOT be modified
        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);
    }

    /**
     * Scenario 4: Offline Event After Simulated Device Restart
     */
    public function testScenario4_OfflineEventAfterDeviceRestartFlaggedForReview()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $now,
            'sync_source'           => 'OFFLINE_SYNC',
            'anchor_elapsed_ms'     => null, // Reset/broken monotonic elapsed
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);
    }

    /**
     * Scenario 5: Device Wall Clock Moved Backwards
     */
    public function testScenario5_DeviceWallClockMovedBackwardsFlaggedAsManipulated()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        // Event claimed at time - 3600, but sync time reported at time - 5400 (rolled back by 30 mins)
        $punchTime = date('Y-m-d H:i:s', time() - 3600);
        $syncTime  = date('Y-m-d H:i:s', time() - 5400);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $punchTime,
            'client_synced_at'      => $syncTime,
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('TIME_MANIPULATED', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);
    }

    /**
     * Scenario 6: Device Wall Clock Moved Forwards (> 5 mins drift)
     */
    public function testScenario6_DeviceWallClockMovedForwardsFlaggedAsSuspiciousDrift()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        // Sync time is 15 minutes ahead of server receipt time
        $skewedSyncTime = date('Y-m-d H:i:s', time() + 900);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => date('Y-m-d H:i:s'),
            'client_synced_at'      => $skewedSyncTime,
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('SUSPICIOUS_DRIFT', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    /**
     * Scenario 7: Accurate Sync Time Paired with Manipulated Historical Event Time
     */
    public function testScenario7_AccurateSyncTimeWithManipulatedHistoricalTimeDetected()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $anchorId = EmployeeModel::generateUuidV4();
        $anchorUtc = gmdate('Y-m-d H:i:s', time() - 3600); // 1 hour ago

        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->deviceId,
            'device_epoch_id' => $this->deviceEpochId,
            'server_utc_time' => $anchorUtc,
            'expires_at'      => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // Device submits sync time matching server time now,
        // but claims event was 3 hours ago (falsifying elapsed monotonic duration vs anchor)
        $forgedTimestamp = date('Y-m-d H:i:s', time() - 10800); // 3 hours ago (impossible vs anchor 1 hr ago)

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $forgedTimestamp,
            'client_synced_at'      => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 1800000, // Claimed 30 mins after anchor, but timestamp forged to 3 hours ago
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('TIME_MANIPULATED', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        $records = $this->attendanceModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(0, $records);
    }

    /**
     * Scenario 8: Missing Server Anchor
     */
    public function testScenario8_MissingServerAnchorFlaggedForReview()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => null, // Missing!
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    /**
     * Scenario 9: Expired Server Anchor
     */
    public function testScenario9_ExpiredServerAnchorFlaggedForReview()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $anchorId = EmployeeModel::generateUuidV4();

        // Expired anchor
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->deviceId,
            'device_epoch_id' => $this->deviceEpochId,
            'server_utc_time' => gmdate('Y-m-d H:i:s', time() - 200000),
            'expires_at'      => gmdate('Y-m-d H:i:s', time() - 10000), // Expired!
            'created_at'      => date('Y-m-d H:i:s', time() - 200000),
        ]);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 1000,
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    /**
     * Scenario 10: Mismatched Device Epoch on Anchor
     */
    public function testScenario10_MismatchedDeviceEpochFlaggedForReview()
    {
        $clientUuid = EmployeeModel::generateUuidV4();
        $anchorId = EmployeeModel::generateUuidV4();

        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->deviceId,
            'device_epoch_id' => 'different-epoch-xyz', // Mismatched!
            'server_utc_time' => gmdate('Y-m-d H:i:s'),
            'expires_at'      => gmdate('Y-m-d H:i:s', time() + 86400),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'anchor_elapsed_ms'     => 1000,
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    /**
     * Scenario 11: Out-of-Order Sequence (750 arrives before 740)
     * Sequence 740 MUST NOT be labeled DUPLICATE_IGNORED; it must be REVIEW_REQUIRED and HELD_FOR_REVIEW!
     */
    public function testScenario11_OutOfOrderSequenceArrivalHeldForReviewWithoutOverwritingAggregate()
    {
        // First, sequence 750 arrives (DIRECT_ONLINE) 30 mins ago
        $time750 = date('Y-m-d H:i:s', time() - 1800);
        $uuid750 = EmployeeModel::generateUuidV4();
        $res750 = $this->syncService->processPunch([
            'client_uuid'           => $uuid750,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $time750,
            'sync_source'           => 'DIRECT_ONLINE',
            'device_event_sequence' => 750,
        ]);
        $this->assertSame('CREATED', $res750['status']);

        // Now, sequence 740 arrives later (out of order, earlier timestamp 40 mins ago!)
        $time740 = date('Y-m-d H:i:s', time() - 2400);
        $uuid740 = EmployeeModel::generateUuidV4();
        $res740 = $this->syncService->processPunch([
            'client_uuid'           => $uuid740,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'timestamp'             => $time740,
            'sync_source'           => 'DIRECT_ONLINE',
            'device_event_sequence' => 740, // < 750!
        ]);

        // MUST be HELD_FOR_REVIEW, NEVER DUPLICATE_IGNORED!
        $this->assertSame('HELD_FOR_REVIEW', $res740['status']);

        $event740 = $this->eventModel->where('client_uuid', $uuid740)->first();
        $this->assertNotNull($event740);
        $this->assertSame('REVIEW_REQUIRED', $event740['processing_status']);
        $this->assertNotSame('DUPLICATE_IGNORED', $event740['processing_status']);

        // Daily summary was NOT overwritten by the out-of-order event
        $summary = $this->attendanceModel->where('employee_id', $this->employeeId)->first();
        $this->assertSame($time750, $summary['check_in_time']);
    }

    /**
     * Scenario 12: Offline Event Queued Under Retired Epoch Preserved in Ledger and Held for Review
     */
    public function testScenario12_RetiredEpochQueuePreservedInLedgerAndHeldForReview()
    {
        $oldEpoch = 'retired-epoch-111';
        $clientUuid = EmployeeModel::generateUuidV4();

        // Device was re-paired, active epoch changed, but offline queue held punch with oldEpoch
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'submitted_epoch_id'    => $oldEpoch,
            'timestamp'             => date('Y-m-d H:i:s'),
            'sync_source'           => 'OFFLINE_SYNC',
            'device_event_sequence' => 1,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        // Ledger record exists and retains old epoch identifier!
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame($oldEpoch, $event['device_epoch_id']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
    }
}

