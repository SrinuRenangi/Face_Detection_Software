<?php

namespace Tests\Database;

use App\Models\AttendanceRecordModel;
use App\Models\AttendanceEventModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Models\AdminAuditLogModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class AttendanceSyncServiceTest extends CIUnitTestCase
{
    protected $db;
    protected AttendanceRecordModel $attendanceModel;
    protected AttendanceEventModel $eventModel;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $kioskModel;
    protected AttendanceSyncService $syncService;

    protected string $testPhoneId;
    protected string $testTabletId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->testPhoneId  = EmployeeModel::generateUuidV4();
        $this->testTabletId = EmployeeModel::generateUuidV4();

        // Register two test devices: an Android smartphone and an Android tablet
        $this->kioskModel->insert([
            'id'                => $this->testPhoneId,
            'device_name'       => 'Test Staff Phone',
            'device_identifier' => 'PHONE-SYNC-' . bin2hex(random_bytes(3)),
            'device_type'       => 'SMARTPHONE',
            'hardware_tier'     => 'ENTRY',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $this->kioskModel->insert([
            'id'                => $this->testTabletId,
            'device_name'       => 'Test Lobby Tablet',
            'device_identifier' => 'TAB-SYNC-' . bin2hex(random_bytes(3)),
            'device_type'       => 'TABLET',
            'hardware_tier'     => 'MIDRANGE',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->kioskModel->delete($this->testPhoneId);
        $this->kioskModel->delete($this->testTabletId);
        parent::tearDown();
    }

    public function testInitialCheckInCreatedSuccessfully()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Sync Subject Alpha',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $punchTime = date('Y-m-d H:i:s', time() - 3600);
        $result = $this->syncService->processPunch([
            'client_uuid'      => $clientUuid,
            'employee_id'      => $empUuid,
            'device_id'        => $this->testPhoneId,
            'timestamp'        => $punchTime,
            'confidence_score' => 0.92,
            'sync_source'      => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('CREATED', $result['status']);
        $this->assertSame($punchTime, $result['check_in_time']);
        $this->assertNull($result['check_out_time']);

        // Verify in DB
        $record = $this->attendanceModel->find($result['record_id']);
        $this->assertNotNull($record);
        $this->assertSame('CHECKED_IN', $record['status']);
        $this->assertSame($clientUuid, $record['client_uuid']);
        $this->assertSame('DIRECT_ONLINE', $record['sync_source']);

        // Cleanup
        $this->attendanceModel->delete($result['record_id']);
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testDuplicateClientUuidReturnsIdempotentConfirmation()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Idempotent Test',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Insert valid server time anchor for test tablet
        $anchorId = EmployeeModel::generateUuidV4();
        $punchTime = date('Y-m-d 08:05:00');
        $punchUtc = (new \DateTimeImmutable($punchTime, new \DateTimeZone('Asia/Kolkata')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->testTabletId,
            'device_epoch_id' => 'epoch-default-1',
            'server_utc_time' => $punchUtc,
            'expires_at'      => date('Y-m-d H:i:s', strtotime('+48 hours')),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $first = $this->syncService->processPunch([
            'client_uuid'       => $clientUuid,
            'employee_id'       => $empUuid,
            'device_id'         => $this->testTabletId,
            'timestamp'         => $punchTime,
            'confidence_score'  => 0.90,
            'sync_source'       => 'OFFLINE_SYNC',
            'server_anchor_id'  => $anchorId,
            'anchor_elapsed_ms' => 0,
        ]);
        $this->assertSame('CREATED', $first['status']);

        // Second submission with exact same client_uuid (simulating network retry)
        $second = $this->syncService->processPunch([
            'client_uuid'       => $clientUuid,
            'employee_id'       => $empUuid,
            'device_id'         => $this->testTabletId,
            'timestamp'         => $punchTime,
            'confidence_score'  => 0.90,
            'sync_source'       => 'OFFLINE_SYNC',
            'server_anchor_id'  => $anchorId,
            'anchor_elapsed_ms' => 0,
        ]);
        $this->assertSame('IDEMPOTENT_CONFIRMED', $second['status']);
        $this->assertSame($first['record_id'], $second['record_id']);

        // Cleanup
        $this->attendanceModel->delete($first['record_id']);
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testMultiDeviceReconciliationEarlierOfflineCheckInWins()
    {
        $empUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Dr. Alice Reconciliation',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Event 1: Alice arrives 30 mins ago and is captured on Lobby Tablet (Online)
        $tabletPunchTime = date('Y-m-d H:i:s', time() - 1800);
        $tabletPunch = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testTabletId,
            'timestamp'        => $tabletPunchTime,
            'confidence_score' => 0.88,
            'sync_source'      => 'DIRECT_ONLINE',
        ]);
        $this->assertSame('CREATED', $tabletPunch['status']);
        $this->assertSame($tabletPunchTime, $tabletPunch['check_in_time']);

        // Event 2: School Bus Phone captured Alice 60 mins ago (earlier, while offline with valid server time anchor)
        // Bus phone reconnects and submits its punch:
        $phoneAnchorId = EmployeeModel::generateUuidV4();
        $phonePunchTime = date('Y-m-d H:i:s', time() - 3600);
        $phonePunchUtc = (new \DateTimeImmutable($phonePunchTime, new \DateTimeZone('Asia/Kolkata')))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $phoneAnchorId,
            'device_id'       => $this->testPhoneId,
            'device_epoch_id' => 'epoch-default-1',
            'server_utc_time' => $phonePunchUtc,
            'expires_at'      => date('Y-m-d H:i:s', strtotime('+48 hours')),
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        $offlinePhonePunch = $this->syncService->processPunch([
            'client_uuid'       => EmployeeModel::generateUuidV4(),
            'employee_id'       => $empUuid,
            'device_id'         => $this->testPhoneId,
            'timestamp'         => $phonePunchTime, // Earlier time!
            'confidence_score'  => 0.91,
            'sync_source'       => 'OFFLINE_SYNC',
            'server_anchor_id'  => $phoneAnchorId,
            'anchor_elapsed_ms' => 0,
        ]);

        $this->assertSame('RECONCILED_EARLIER', $offlinePhonePunch['status']);
        $this->assertSame($phonePunchTime, $offlinePhonePunch['check_in_time']);

        // Verify that record in DB has been reconciled to earlier time
        $reconciled = $this->attendanceModel->find($tabletPunch['record_id']);
        $this->assertSame($phonePunchTime, $reconciled['check_in_time']);
        $this->assertSame($this->testPhoneId, $reconciled['device_id']);
        $this->assertSame('OFFLINE_SYNC', $reconciled['sync_source']);

        // Cleanup
        $this->attendanceModel->delete($tabletPunch['record_id']);
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testMultiDeviceDuplicatePunchWithin5MinuteCooldownIgnored()
    {
        $empUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Duplicate Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // First punch 10 minutes ago on Phone
        $punch1Time = date('Y-m-d H:i:s', time() - 600);
        $punch1 = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testPhoneId,
            'timestamp'        => $punch1Time,
            'confidence_score' => 0.95,
        ]);
        $this->assertSame('CREATED', $punch1['status']);

        // Second punch 8 minutes ago on Tablet (2 minutes later -> within 5 min cooldown)
        $punch2Time = date('Y-m-d H:i:s', time() - 480);
        $punch2 = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testTabletId,
            'timestamp'        => $punch2Time,
            'confidence_score' => 0.93,
        ]);

        $this->assertSame('DUPLICATE_IGNORED', $punch2['status']);
        $this->assertSame($punch1Time, $punch2['check_in_time']);

        // Cleanup
        $this->attendanceModel->delete($punch1['record_id']);
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testLaterPunchRecordedAsCheckOut()
    {
        $empUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Full Day Shift Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Configure smartphone terminal as EXIT_ONLY
        $this->kioskModel->update($this->testPhoneId, ['terminal_mode' => 'EXIT_ONLY']);

        // Morning Check-In 3 hours ago on Lobby Tablet (ENTRY_ONLY)
        $checkInTime = date('Y-m-d H:i:s', time() - 10800);
        $checkIn = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testTabletId,
            'timestamp'        => $checkInTime,
            'confidence_score' => 0.95,
        ]);
        $this->assertSame('CREATED', $checkIn['status']);

        // Check-Out 2 hours ago on Exit Gate Smartphone (EXIT_ONLY)
        $checkOutTime = date('Y-m-d H:i:s', time() - 7200);
        $checkOut = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testPhoneId,
            'timestamp'        => $checkOutTime,
            'confidence_score' => 0.91,
        ]);

        $this->assertSame('CHECK_OUT_RECORDED', $checkOut['status']);
        $this->assertSame($checkInTime, $checkOut['check_in_time']);
        $this->assertSame($checkOutTime, $checkOut['check_out_time']);

        // Extended check-out 1 hour ago
        $checkOutExtendedTime = date('Y-m-d H:i:s', time() - 3600);
        $checkOutExtended = $this->syncService->processPunch([
            'client_uuid'      => EmployeeModel::generateUuidV4(),
            'employee_id'      => $empUuid,
            'device_id'        => $this->testPhoneId,
            'timestamp'        => $checkOutExtendedTime,
            'confidence_score' => 0.89,
        ]);

        $this->assertSame('CHECK_OUT_RECORDED', $checkOutExtended['status']);
        $this->assertSame($checkOutExtendedTime, $checkOutExtended['check_out_time']);

        // Verify final record in DB
        $finalRecord = $this->attendanceModel->find($checkIn['record_id']);
        $this->assertSame('CHECKED_OUT', $finalRecord['status']);
        $this->assertSame($checkOutExtendedTime, $finalRecord['check_out_time']);

        // Cleanup
        $this->attendanceModel->delete($checkIn['record_id']);
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testFutureOnlineTimestampRejectedWithInvalidFutureTimestamp()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Future Time Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Timestamp 5 minutes in the future (> 60s)
        $futurePunchTime = date('Y-m-d H:i:s', time() + 300);
        $result = $this->syncService->processPunch([
            'client_uuid'      => $clientUuid,
            'employee_id'      => $empUuid,
            'device_id'        => $this->testPhoneId,
            'timestamp'        => $futurePunchTime,
            'confidence_score' => 0.95,
            'sync_source'      => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('INVALID_FUTURE_TIMESTAMP', $result['status']);
        $this->assertStringContainsString('Submitted timestamp is in the future', $result['error']);

        // Cleanup
        $this->employeeModel->delete($empUuid);
    }

    public function testFutureOfflineTimestampHeldWithSuspiciousDrift()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'SYNC-EMP-' . bin2hex(random_bytes(4)),
            'full_name'   => 'Future Offline Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $kolkataTz = new \DateTimeZone('Asia/Kolkata');
        $utcTz     = new \DateTimeZone('UTC');

        // Issue anchor with server time 10 seconds ago, but monotonic elapsed claims +60 seconds:
        // expectedUtcEpoch = (now - 10s) + 60s = now + 50s (> 5s in future relative to server!)
        $anchorId = EmployeeModel::generateUuidV4();
        $server10sAgoUtc = (new \DateTimeImmutable('-10 seconds', $utcTz))->format('Y-m-d H:i:s');
        $this->db->table('kiosk_time_anchors')->insert([
            'id'              => $anchorId,
            'device_id'       => $this->testPhoneId,
            'device_epoch_id' => 'epoch-default-1',
            'server_utc_time' => $server10sAgoUtc,
            'expires_at'      => (new \DateTimeImmutable('+48 hours', $utcTz))->format('Y-m-d H:i:s'),
            'created_at'      => $server10sAgoUtc,
        ]);

        $punchTimeKolkata = (new \DateTimeImmutable('now', $kolkataTz))->format('Y-m-d H:i:s');

        $result = $this->syncService->processPunch([
            'client_uuid'       => $clientUuid,
            'employee_id'       => $empUuid,
            'device_id'         => $this->testPhoneId,
            'timestamp'         => $punchTimeKolkata,
            'client_synced_at'  => $punchTimeKolkata,
            'sync_source'       => 'OFFLINE_SYNC',
            'server_anchor_id'  => $anchorId,
            'anchor_elapsed_ms' => 60000, // 60s ahead -> expectedUtcEpoch > serverEpochTime + 5s!
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);

        // Check event in DB: clock_trust_status must be SUSPICIOUS_DRIFT
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertNotNull($event);
        $this->assertSame('SUSPICIOUS_DRIFT', $event['clock_trust_status']);
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        // Daily attendance record must NOT exist
        $records = $this->attendanceModel->where('employee_id', $empUuid)->findAll();
        $this->assertCount(0, $records);

        // Cleanup
        $this->eventModel->where('employee_id', $empUuid)->delete();
        $this->employeeModel->delete($empUuid);
        $this->db->table('kiosk_time_anchors')->where('id', $anchorId)->delete();
    }

    public function testKioskDeviceModelSupportsPhonesAndTablets()
    {
        $phone = $this->kioskModel->find($this->testPhoneId);
        $this->assertSame('SMARTPHONE', $phone['device_type']);
        $this->assertSame('ENTRY', $phone['hardware_tier']);

        $tablet = $this->kioskModel->find($this->testTabletId);
        $this->assertSame('TABLET', $tablet['device_type']);
        $this->assertSame('MIDRANGE', $tablet['hardware_tier']);

        // Count phones vs tablets
        $phonesCount = $this->kioskModel->where('device_type', 'SMARTPHONE')->countAllResults();
        $tabletsCount = $this->kioskModel->where('device_type', 'TABLET')->countAllResults();
        $this->assertGreaterThanOrEqual(1, $phonesCount);
        $this->assertGreaterThanOrEqual(1, $tabletsCount);
    }
}
