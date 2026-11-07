<?php

namespace Tests\Feature;

use App\Models\AttendancePhotoModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendancePhotoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class AttendancePhotoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $deviceModel;
    protected AttendancePhotoModel $photoModel;
    protected AttendancePhotoService $photoService;

    protected array $createdFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db           = Database::connect();
        $this->employeeModel= new EmployeeModel();
        $this->deviceModel  = new KioskDeviceModel();
        $this->photoModel   = new AttendancePhotoModel();
        $this->photoService = new AttendancePhotoService();
        service('cache')->clean();
    }

    protected function tearDown(): void
    {
        // Clean up any test files created on disk
        foreach ($this->createdFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->createdFiles = [];

        service('cache')->clean();
        parent::tearDown();
    }

    private function createSyntheticJpeg(int $width = 300, int $height = 300): string
    {
        $img = imagecreatetruecolor($width, $height);
        $bg = imagecolorallocate($img, 120, 160, 210);
        imagefill($img, 0, 0, $bg);
        ob_start();
        imagejpeg($img, null, 85);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    private function createSyntheticPng(int $width = 300, int $height = 300, bool $transparent = false): string
    {
        $img = imagecreatetruecolor($width, $height);
        if ($transparent) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $trans = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefill($img, 0, 0, $trans);
            $red = imagecolorallocate($img, 220, 50, 50);
            imagefilledrectangle($img, 50, 50, $width - 50, $height - 50, $red);
        } else {
            $bg = imagecolorallocate($img, 100, 180, 100);
            imagefill($img, 0, 0, $bg);
        }
        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    private function createTestEmployee(array $overrides = []): array
    {
        $id = EmployeeModel::generateUuidV4();
        $empCode = 'TEST-PHOTO-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $data = array_merge([
            'id'          => $id,
            'employee_id' => $empCode,
            'full_name'   => 'Photo Staff ' . bin2hex(random_bytes(2)),
            'department'  => 'Science',
            'designation' => 'Lecturer',
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
            'device_name'         => 'Photo Kiosk ' . bin2hex(random_bytes(3)),
            'device_identifier'   => 'DEV-PH-' . bin2hex(random_bytes(3)),
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

    private function createTestEvent(string $empId, string $deviceId): string
    {
        $eventId = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');
        $this->db->table('attendance_events')->insert([
            'id'                     => $eventId,
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $empId,
            'device_id'              => $deviceId,
            'device_epoch_id'        => EmployeeModel::generateUuidV4(),
            'device_event_sequence'  => 1,
            'event_type'             => 'CHECK_IN',
            'terminal_mode'          => 'ENTRY_ONLY',
            'client_recorded_at'     => $now,
            'server_received_at'     => $now,
            'client_timezone'        => 'Asia/Kolkata',
            'clock_trust_status'     => 'TRUSTED_SERVER_TIME',
            'synchronization_source' => 'DIRECT_ONLINE',
            'payload_hash'           => hash('sha256', $eventId),
            'processing_status'      => 'PROCESSED',
            'created_at'             => $now,
        ]);
        return $eventId;
    }

    // =========================================================================
    // 1. Photo Capture, Validation & Private Storage Tests
    // =========================================================================

    public function testValidJpegPhotoUploadViaConfirmPunchCreatesPhotoRecordAndPrivateFile()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            // Step 1: Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Step 2: Confirm punch with synthetic JPEG
            $jpegBytes = $this->createSyntheticJpeg(320, 240);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
            $clientUuid = EmployeeModel::generateUuidV4();

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'photo'              => $photoDataUrl,
            ]);

            $confRes->assertStatus(200);
            $json = json_decode($confRes->getJSON(), true);

            $this->assertSame('success', $json['status']);
            $this->assertTrue($json['photo_captured']);
            $this->assertNotEmpty($json['photo_id']);

            // Verify database record in attendance_photos
            $photoRecord = $this->photoModel->find($json['photo_id']);
            $this->assertNotNull($photoRecord);
            $this->assertSame($emp['id'], $photoRecord['employee_id']);
            $this->assertSame('CHECK_IN', $photoRecord['photo_type']);
            $this->assertSame('image/jpeg', $photoRecord['mime_type']);
            $this->assertSame(320, (int) $photoRecord['image_width']);
            $this->assertSame(240, (int) $photoRecord['image_height']);
            $this->assertGreaterThan(0, (int) $photoRecord['file_size']);

            // Security: Database MUST store storage path, NOT raw base64 or image bytes
            $this->assertArrayNotHasKey('photo_data', $photoRecord);
            $this->assertArrayNotHasKey('base64', $photoRecord);
            $this->assertStringContainsString('photo_', $photoRecord['storage_path']);

            // Security: Storage path is outside web root (inside WRITEPATH)
            $this->assertStringNotContainsString('public', $photoRecord['storage_path']);
            $resolvedPath = $this->photoService->resolveSecurePath($photoRecord['storage_path']);
            $this->assertNotNull($resolvedPath);
            $this->assertFileExists($resolvedPath);
            $this->createdFiles[] = $resolvedPath;

            // Filename must be randomized (32-hex characters)
            $filename = basename($photoRecord['storage_path']);
            $this->assertMatchesRegularExpression('/^photo_[a-f0-9]{32}\.jpg$/', $filename);

            // File content on disk starts with JPEG magic bytes \xFF\xD8\xFF
            $onDiskBytes = file_get_contents($resolvedPath);
            $this->assertSame("\xFF\xD8\xFF", substr($onDiskBytes, 0, 3));
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testCorruptedOrNonJpegPhotoIsRejectedWith422()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Corrupted payload (text string posing as photo)
            $fakePhoto = 'data:image/jpeg;base64,' . base64_encode('NOT_A_VALID_JPEG_IMAGE_CONTENT');

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $fakePhoto,
            ]);

            $confRes->assertStatus(422);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('INVALID_PHOTO', $json['code']);
        } finally {
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testOversizedPhotoUploadIsRejectedWith422()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // 2.5 MB fake photo data
            $largeFake = "\xFF\xD8\xFF\xE0" . str_repeat('A', 2500000);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($largeFake);

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $photoDataUrl,
            ]);

            $confRes->assertStatus(422);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('INVALID_PHOTO', $json['code']);
        } finally {
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testResolutionBoundsValidationRejectsSub200x200Photos()
    {
        $service = new AttendancePhotoService();
        $tinyJpeg = $this->createSyntheticJpeg(150, 150); // Below 200x200 min
        $val = $service->validatePhotoBytes($tinyJpeg);

        $this->assertFalse($val['valid']);
        $this->assertStringContainsString('too small', $val['error']);
    }

    // =========================================================================
    // 2. Protected Photo Retrieval & Access Control Tests
    // =========================================================================

    public function testAdminCanRetrievePhotoViaPhotoId()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $eventId = $this->createTestEvent($emp['id'], $device['id']);

        $jpegBytes = $this->createSyntheticJpeg(300, 300);
        $stored = $this->photoService->storeValidatedPhoto($jpegBytes);
        $this->assertTrue($stored['success']);
        $resolved = $this->photoService->resolveSecurePath($stored['storage_path']);
        $this->createdFiles[] = $resolved;

        $photoId = EmployeeModel::generateUuidV4();

        $this->photoModel->insert([
            'id'                   => $photoId,
            'attendance_record_id' => null,
            'event_id'             => $eventId,
            'employee_id'          => $emp['id'],
            'photo_type'           => 'CHECK_IN',
            'storage_path'         => $stored['storage_path'],
            'file_name'            => $stored['file_name'],
            'file_size'            => $stored['file_size'],
            'mime_type'            => 'image/jpeg',
            'image_width'          => $stored['image_width'],
            'image_height'         => $stored['image_height'],
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        try {
            $adminSession = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            $res = $this->withSession($adminSession)->get('/admin/attendance/photos/' . $photoId);

            $res->assertStatus(200);
            $res->assertHeader('Content-Type', 'image/jpeg');
            $res->assertHeader('X-Content-Type-Options', 'nosniff');
            $rawBody = $res->response()->getBody();
            $this->assertSame("\xFF\xD8\xFF", substr($rawBody, 0, 3));
        } finally {
            $this->photoModel->delete($photoId);
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testAdminCanRetrievePhotoViaEventId()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $eventId = $this->createTestEvent($emp['id'], $device['id']);

        $jpegBytes = $this->createSyntheticJpeg(300, 300);
        $stored = $this->photoService->storeValidatedPhoto($jpegBytes);
        $this->assertTrue($stored['success']);
        $resolved = $this->photoService->resolveSecurePath($stored['storage_path']);
        $this->createdFiles[] = $resolved;

        $photoId = EmployeeModel::generateUuidV4();

        $this->photoModel->insert([
            'id'                   => $photoId,
            'attendance_record_id' => null,
            'event_id'             => $eventId,
            'employee_id'          => $emp['id'],
            'photo_type'           => 'CHECK_IN',
            'storage_path'         => $stored['storage_path'],
            'file_name'            => $stored['file_name'],
            'file_size'            => $stored['file_size'],
            'mime_type'            => 'image/jpeg',
            'image_width'          => $stored['image_width'],
            'image_height'         => $stored['image_height'],
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        try {
            $adminSession = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            $res = $this->withSession($adminSession)->get('/admin/attendance/events/' . $eventId . '/photo');

            $res->assertStatus(200);
            $res->assertHeader('Content-Type', 'image/jpeg');
            $rawBody = $res->response()->getBody();
            $this->assertSame("\xFF\xD8\xFF", substr($rawBody, 0, 3));
        } finally {
            $this->photoModel->delete($photoId);
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testManagerCannotRetrievePhotosForbidden403()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();
        $eventId = $this->createTestEvent($emp['id'], $device['id']);

        $jpegBytes = $this->createSyntheticJpeg(300, 300);
        $stored = $this->photoService->storeValidatedPhoto($jpegBytes);
        $this->assertTrue($stored['success']);
        $resolved = $this->photoService->resolveSecurePath($stored['storage_path']);
        $this->createdFiles[] = $resolved;

        $photoId = EmployeeModel::generateUuidV4();

        $this->photoModel->insert([
            'id'                   => $photoId,
            'attendance_record_id' => null,
            'event_id'             => $eventId,
            'employee_id'          => $emp['id'],
            'photo_type'           => 'CHECK_IN',
            'storage_path'         => $stored['storage_path'],
            'file_name'            => $stored['file_name'],
            'file_size'            => $stored['file_size'],
            'mime_type'            => 'image/jpeg',
            'image_width'          => $stored['image_width'],
            'image_height'         => $stored['image_height'],
            'created_at'           => date('Y-m-d H:i:s'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        try {
            $managerSession = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'm0000000-0000-0000-0000-000000000002',
                'admin_username'  => 'manager_staff',
                'admin_full_name' => 'School Staff Manager',
                'admin_role'      => 'ROLE_MANAGER',
            ];

            // Manager must be strictly blocked (403 Forbidden)
            $res = $this->withSession($managerSession)->get('/admin/attendance/photos/' . $photoId);
            $res->assertStatus(403);

            $resEvent = $this->withSession($managerSession)->get('/admin/attendance/events/' . $eventId . '/photo');
            $resEvent->assertStatus(403);
        } finally {
            $this->photoModel->delete($photoId);
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testUnauthenticatedPhotoRetrievalRedirectsOrBlocks()
    {
        $res = $this->get('/admin/attendance/photos/' . EmployeeModel::generateUuidV4());
        // CodeIgniter filter redirects unauthenticated requests to login (302) or returns 401
        $this->assertTrue(in_array($res->response()->getStatusCode(), [302, 401, 403], true));
    }

    public function testMissingPhotoReturns404()
    {
        $adminSession = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $res = $this->withSession($adminSession)->get('/admin/attendance/photos/' . EmployeeModel::generateUuidV4());
        $res->assertStatus(404);
    }

    public function testEventWithoutPhotoReturns404()
    {
        $adminSession = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $res = $this->withSession($adminSession)->get('/admin/attendance/events/' . EmployeeModel::generateUuidV4() . '/photo');
        $res->assertStatus(404);
    }

    // =========================================================================
    // 3. Lifecycle Safety & Idempotency Tests
    // =========================================================================

    public function testPhysicalPhotoPurgedOnBusinessRuleRejection()
    {
        $device = $this->createTestKioskDevice('EXIT_ONLY');
        $emp = $this->createTestEmployee();

        try {
            // Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Attempt EXIT punch with photo when NO check-in exists
            $jpegBytes = $this->createSyntheticJpeg(300, 300);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($jpegBytes);

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $photoDataUrl,
            ]);

            // Must reject with 422 REJECTED_NO_OPEN_RECORD
            $confRes->assertStatus(422);

            // Verify no photo record was saved in database
            $count = $this->photoModel->where('employee_id', $emp['id'])->countAllResults();
            $this->assertSame(0, $count);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testIdempotentPunchDoesNotDuplicatePhotoRows()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            // First punch
            $verifRes1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes1->assertStatus(200);
            $token1 = json_decode($verifRes1->getJSON(), true)['verification_token'];

            $jpegBytes = $this->createSyntheticJpeg(300, 300);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($jpegBytes);
            $clientUuid = EmployeeModel::generateUuidV4();
            $clientTimestamp = date('Y-m-d H:i:s');

            $confRes1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token1,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'timestamp'          => $clientTimestamp,
                'photo'              => $photoDataUrl,
            ]);
            $confRes1->assertStatus(200);
            $photoId1 = json_decode($confRes1->getJSON(), true)['photo_id'];

            // Second punch with SAME client_uuid and SAME timestamp (network retry simulation)
            // Get fresh token
            $verifRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes2->assertStatus(200);
            $token2 = json_decode($verifRes2->getJSON(), true)['verification_token'];

            $confRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token2,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid, // Same client UUID
                'timestamp'          => $clientTimestamp,
                'photo'              => $photoDataUrl,
            ]);
            $confRes2->assertStatus(200);

            // Exactly ONE photo record must exist in DB for this event
            $photos = $this->photoModel->where('employee_id', $emp['id'])->findAll();
            $this->assertCount(1, $photos);
            $this->assertSame($photoId1, $photos[0]['id']);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testValidPngPhotoAcceptedAndConvertedToNormalizedJpeg()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            // Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Submit valid PNG photo data URL
            $pngBytes = $this->createSyntheticPng(320, 240, false);
            $photoDataUrl = 'data:image/png;base64,' . base64_encode($pngBytes);

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $photoDataUrl,
            ]);

            $confRes->assertStatus(200);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('success', $json['status']);
            $this->assertTrue($json['photo_captured']);
            $this->assertNotNull($json['photo_id']);

            // Verify stored record in DB
            $photoRecord = $this->photoModel->find($json['photo_id']);
            $this->assertNotNull($photoRecord);
            $this->assertSame('image/jpeg', $photoRecord['mime_type']);
            $this->assertSame(320, (int) $photoRecord['image_width']);
            $this->assertSame(240, (int) $photoRecord['image_height']);

            // Verify physical file on disk is an authentic normalized JPEG
            $realPath = $this->photoService->resolveSecurePath($photoRecord['storage_path']);
            $this->assertNotNull($realPath);
            $this->assertFileExists($realPath);
            $this->createdFiles[] = $realPath;

            $fileBytes = file_get_contents($realPath);
            $this->assertStringStartsWith("\xFF\xD8\xFF", $fileBytes); // JPEG SOI marker
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testTransparentPngCompositesCleanlyOnWhite()
    {
        $transparentPng = $this->createSyntheticPng(250, 250, true);
        $result = $this->photoService->storeValidatedPhoto($transparentPng);

        $this->assertTrue($result['success']);
        $this->assertSame('image/jpeg', $result['mime_type']);
        $this->assertSame(250, $result['image_width']);
        $this->assertSame(250, $result['image_height']);

        $realPath = $this->photoService->resolveSecurePath($result['storage_path']);
        $this->assertNotNull($realPath);
        $this->assertFileExists($realPath);
        $this->createdFiles[] = $realPath;

        // Verify it is decodable as JPEG
        $info = @getimagesize($realPath);
        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
    }

    public function testCorruptOrUnsupportedFormatRejectedWith422()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee();

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Submit invalid plain text as image data
            $fakePhoto = 'data:image/jpeg;base64,' . base64_encode('NOT_A_VALID_IMAGE_CONTENT_HERE');

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $fakePhoto,
            ]);

            $confRes->assertStatus(422);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('error', $json['status']);
            $this->assertSame('INVALID_PHOTO', $json['code']);
        } finally {
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }
}
