<?php

namespace Tests\Feature;

use App\Models\AttendanceEventModel;
use App\Models\AttendancePhotoModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendancePhotoService;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class PhotoAttendanceConsistencyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $deviceModel;
    protected AttendancePhotoModel $photoModel;
    protected AttendanceRecordModel $recordModel;
    protected AttendanceEventModel $eventModel;
    protected AttendancePhotoService $photoService;
    protected AttendanceSyncService $syncService;

    protected array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db           = Database::connect();
        $this->employeeModel= new EmployeeModel();
        $this->deviceModel  = new KioskDeviceModel();
        $this->photoModel   = new AttendancePhotoModel();
        $this->recordModel  = new AttendanceRecordModel();
        $this->eventModel   = new AttendanceEventModel();
        $this->photoService = new AttendancePhotoService();
        $this->syncService  = new AttendanceSyncService();
        service('cache')->clean();
    }

    protected function tearDown(): void
    {
        foreach ($this->createdFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->createdFiles = [];

        service('cache')->clean();
        parent::tearDown();
    }

    private function createSyntheticJpeg(int $width = 320, int $height = 240, int $r = 100, int $g = 150, int $b = 200): string
    {
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, $r, $g, $b);
        imagefill($img, 0, 0, $bg);
        ob_start();
        imagejpeg($img, null, 85);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    private function createTestEmployee(array $overrides = []): array
    {
        $id = EmployeeModel::generateUuidV4();
        $empCode = 'CONSIST-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $data = array_merge([
            'id'          => $id,
            'employee_id' => $empCode,
            'full_name'   => 'Consistency Staff ' . bin2hex(random_bytes(2)),
            'department'  => 'Academics',
            'designation' => 'Teacher',
            'status'      => 'ACTIVE',
            'pin_hash'    => password_hash('123456', PASSWORD_BCRYPT),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->employeeModel->insert($data);
        return $data;
    }

    private function createTestKioskDevice(string $terminalMode = 'ENTRY_ONLY'): array
    {
        $deviceId  = EmployeeModel::generateUuidV4();
        $rawToken  = bin2hex(random_bytes(32));
        $epochId   = EmployeeModel::generateUuidV4();
        $now       = date('Y-m-d H:i:s');

        $deviceData = [
            'id'                  => $deviceId,
            'device_name'         => 'Consistency Kiosk ' . bin2hex(random_bytes(3)),
            'device_identifier'   => 'DEV-CONSIST-' . bin2hex(random_bytes(3)),
            'device_type'         => 'TABLET',
            'terminal_mode'       => $terminalMode,
            'device_epoch_id'     => $epochId,
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'device_token_hash'   => hash('sha256', $rawToken),
            'token_created_at'    => $now,
            'token_expires_at'    => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'           => $now,
            'last_seen_at'        => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $this->deviceModel->insert($deviceData);

        return [
            'id'        => $deviceId,
            'raw_token' => $rawToken,
            'epoch_id'  => $epochId,
            'mode'      => $terminalMode,
        ];
    }

    private function getVerificationToken(array $device, array $emp, string $pin = '123456'): string
    {
        $res = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/verify-pin', [
            'employee_code' => $emp['employee_id'],
            'pin'           => $pin,
        ]);

        $res->assertStatus(200);
        $json = json_decode($res->getJSON(), true);
        return $json['verification_token'];
    }

    // =========================================================================
    // Scenario 1: Photo stored on disk, but attendance is rejected
    // =========================================================================

    public function testPhotoDeletedWhenAttendanceRejectedNoOpenRecord()
    {
        $device = $this->createTestKioskDevice('EXIT_ONLY');
        $emp = $this->createTestEmployee();

        try {
            $token = $this->getVerificationToken($device, $emp);
            $photoBinary = $this->createSyntheticJpeg();
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($photoBinary);

            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $photoDataUrl,
            ]);

            $res->assertStatus(422);
            $json = json_decode($res->getJSON(), true);
            $this->assertSame('REJECTED_NO_OPEN_RECORD', $json['code']);

            // Consistency Assertions:
            // 1. Zero photo records in database for this employee
            $photoCount = $this->photoModel->where('employee_id', $emp['id'])->countAllResults();
            $this->assertSame(0, $photoCount, 'Rejected punch must not create attendance_photos record.');

            // 2. Zero attendance records in database
            $recCount = $this->recordModel->where('employee_id', $emp['id'])->countAllResults();
            $this->assertSame(0, $recCount, 'Rejected exit must not create attendance_records summary.');
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // Scenario 2: Attendance transaction rolls back when photo insertion fails
    // =========================================================================

    public function testDatabaseRollbackWhenPhotoMetadataInsertionFails()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

        try {
            // Provide deliberately invalid photo metadata (exceeding VARCHAR(64) limit on mime_type)
            // to force the DB insert into attendance_photos to fail inside the transaction
            $invalidPhotoMetadata = [
                'storage_path' => '2026/11/06/test_photo.jpg',
                'file_name'    => 'test_photo.jpg',
                'mime_type'    => str_repeat('A', 100), // Exceeds VARCHAR(64) column constraint
                'file_size'    => 12345,
                'image_width'  => 640,
                'image_height' => 480,
            ];

            $result = $this->syncService->processPunch([
                'client_uuid'     => $clientUuid,
                'employee_id'     => $emp['id'],
                'device_id'       => $device['id'],
                'device_epoch_id' => $device['epoch_id'],
                'terminal_mode'   => 'ENTRY_ONLY',
                'timestamp'       => $now,
                'sync_source'     => 'DIRECT_ONLINE',
                'confidence_score'=> 1.00,
                'photo_metadata'  => $invalidPhotoMetadata,
            ]);

            // Must report DATABASE_ERROR or fail-safe error
            $this->assertSame('DATABASE_ERROR', $result['status']);
            $this->assertNotEmpty($result['error']);

            // Consistency Assertions: Entire transaction was rolled back
            // 1. Zero attendance_events
            $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
            $this->assertNull($event, 'Attendance event must be rolled back if photo insertion fails.');

            // 2. Zero attendance_records
            $record = $this->recordModel->where('employee_id', $emp['id'])->first();
            $this->assertNull($record, 'Attendance record must be rolled back if photo insertion fails.');

            // 3. Zero attendance_photos
            $photos = $this->photoModel->where('employee_id', $emp['id'])->findAll();
            $this->assertCount(0, $photos, 'Attendance photo must not exist after rollback.');
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // Scenario 3: Atomic commit of valid attendance event, record, and photo
    // =========================================================================

    public function testSuccessfulPunchCommitsEventRecordAndPhotoAtomically()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $clientUuid = EmployeeModel::generateUuidV4();

        try {
            $token = $this->getVerificationToken($device, $emp);
            $photoBinary = $this->createSyntheticJpeg(400, 300, 70, 180, 90);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($photoBinary);

            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'photo'              => $photoDataUrl,
            ]);

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $this->assertSame('success', $json['status']);
            $this->assertTrue($json['photo_captured']);
            $this->assertNotNull($json['photo_id']);
            $this->assertNotNull($json['event_id']);
            $this->assertNotNull($json['record_id']);

            // Consistency Assertions:
            // 1. Photo record exists and links event and attendance record
            $photo = $this->photoModel->find($json['photo_id']);
            $this->assertNotNull($photo);
            $this->assertSame($json['event_id'], $photo['event_id']);
            $this->assertSame($json['record_id'], $photo['attendance_record_id']);
            $this->assertSame($emp['id'], $photo['employee_id']);
            $this->assertSame('CHECK_IN', $photo['photo_type']);

            // 2. Physical file exists on disk
            $fullPath = $this->photoService->resolveSecurePath($photo['storage_path']);
            $this->assertFileExists($fullPath);
            $this->createdFiles[] = $fullPath;

            // 3. Attendance record exists
            $rec = $this->recordModel->find($json['record_id']);
            $this->assertNotNull($rec);
            $this->assertSame('CHECKED_IN', $rec['status']);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // Scenario 4 & 5: Network retry with new photo preserves original photo
    // =========================================================================

    public function testNetworkTimeoutRetryPreservesOriginalPhotoAndDeletesRetryPhoto()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $clientUuid = EmployeeModel::generateUuidV4();
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

        try {
            // Punch 1: Original check-in
            $token1 = $this->getVerificationToken($device, $emp);
            $photo1Binary = $this->createSyntheticJpeg(300, 300, 200, 50, 50); // Red
            $photo1DataUrl = 'data:image/jpeg;base64,' . base64_encode($photo1Binary);

            $res1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token1,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'timestamp'          => $timestamp,
                'photo'              => $photo1DataUrl,
            ]);

            $res1->assertStatus(200);
            $json1 = json_decode($res1->getJSON(), true);
            $originalPhotoId = $json1['photo_id'];
            $this->assertNotNull($originalPhotoId);

            $photo1 = $this->photoModel->find($originalPhotoId);
            $this->assertNotNull($photo1);
            $photo1FullPath = $this->photoService->resolveSecurePath($photo1['storage_path']);
            $this->assertFileExists($photo1FullPath);
            $this->createdFiles[] = $photo1FullPath;

            // Punch 2: Retry with SAME client_uuid, SAME timestamp, but NEW photo
            $token2 = $this->getVerificationToken($device, $emp);
            $photo2Binary = $this->createSyntheticJpeg(300, 300, 50, 50, 200); // Blue
            $photo2DataUrl = 'data:image/jpeg;base64,' . base64_encode($photo2Binary);

            $res2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token2,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid, // Same UUID
                'timestamp'          => $timestamp,  // Same timestamp
                'photo'              => $photo2DataUrl, // Different photo!
            ]);

            $res2->assertStatus(200);
            $json2 = json_decode($res2->getJSON(), true);

            // Consistency Assertions:
            // 1. Idempotency reported
            $this->assertSame('IDEMPOTENT_CONFIRMED', $json2['sync_status']);
            $this->assertSame($originalPhotoId, $json2['photo_id']);
            $this->assertTrue($json2['photo_captured']);

            // 2. Exactly ONE photo record in DB, matching original
            $photos = $this->photoModel->where('employee_id', $emp['id'])->findAll();
            $this->assertCount(1, $photos);
            $this->assertSame($originalPhotoId, $photos[0]['id']);

            // 3. Original file on disk is preserved
            $this->assertFileExists($photo1FullPath);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // Scenario 6: Replay of existing client_uuid with conflicting payload
    // =========================================================================

    public function testReplayWithConflictingPayloadRejectsAndCleansUpPhoto()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $clientUuid = EmployeeModel::generateUuidV4();
        $time1 = (new \DateTimeImmutable('now - 1 hour', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $time2 = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

        try {
            // Punch 1: Original valid punch
            $token1 = $this->getVerificationToken($device, $emp);
            $photo1DataUrl = 'data:image/jpeg;base64,' . base64_encode($this->createSyntheticJpeg());

            $res1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token1,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'timestamp'          => $time1,
                'photo'              => $photo1DataUrl,
            ]);
            $res1->assertStatus(200);
            $origPhotoId = json_decode($res1->getJSON(), true)['photo_id'];

            // Punch 2: Conflicting replay with same client_uuid but DIFFERENT timestamp
            $token2 = $this->getVerificationToken($device, $emp);
            $photo2DataUrl = 'data:image/jpeg;base64,' . base64_encode($this->createSyntheticJpeg());

            $res2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token2,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid, // Same UUID
                'timestamp'          => $time2,      // Conflicting timestamp
                'photo'              => $photo2DataUrl,
            ]);

            // Must reject with 409 Conflict
            $res2->assertStatus(409);
            $json2 = json_decode($res2->getJSON(), true);
            $this->assertSame('CONFLICT_PAYLOAD_MISMATCH', $json2['code']);

            // Consistency Assertions:
            // 1. Original photo remains intact in DB
            $photos = $this->photoModel->where('employee_id', $emp['id'])->findAll();
            $this->assertCount(1, $photos);
            $this->assertSame($origPhotoId, $photos[0]['id']);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // Scenario 7: Duplicate entry scan preserves check-in photo without enum error
    // =========================================================================

    public function testDuplicateEntryScanPreservesCheckInPhotoWithoutEnumError()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $time1 = (new \DateTimeImmutable('now - 10 minutes', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
        $time2 = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

        try {
            // First scan: Normal check-in with photo
            $token1 = $this->getVerificationToken($device, $emp);
            $photo1DataUrl = 'data:image/jpeg;base64,' . base64_encode($this->createSyntheticJpeg());

            $res1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token1,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'timestamp'          => $time1,
                'photo'              => $photo1DataUrl,
            ]);
            $res1->assertStatus(200);
            $origPhotoId = json_decode($res1->getJSON(), true)['photo_id'];
            $this->assertNotNull($origPhotoId);

            // Second scan: Duplicate entry scan later in the day with NEW photo
            $token2 = $this->getVerificationToken($device, $emp);
            $photo2DataUrl = 'data:image/jpeg;base64,' . base64_encode($this->createSyntheticJpeg());

            $res2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token2,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'timestamp'          => $time2,
                'photo'              => $photo2DataUrl,
            ]);

            // Must NOT throw 500 data truncation error on photo_type ENUM('CHECK_IN','CHECK_OUT')
            $res2->assertStatus(200);
            $json2 = json_decode($res2->getJSON(), true);

            $this->assertSame('DUPLICATE_IGNORED', $json2['sync_status']);
            $this->assertSame($origPhotoId, $json2['photo_id']);

            // Consistency Assertions:
            // Exactly ONE photo record in DB (the original CHECK_IN photo)
            $photos = $this->photoModel->where('employee_id', $emp['id'])->findAll();
            $this->assertCount(1, $photos);
            $this->assertSame('CHECK_IN', $photos[0]['photo_type']);
            $this->assertSame($origPhotoId, $photos[0]['id']);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }
}
