<?php

namespace Tests\Feature;

use App\Models\AttendanceEventModel;
use App\Models\AttendancePhotoModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendancePhotoService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class AndroidKioskPilotIntegrationTest extends CIUnitTestCase
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

    protected array $createdFiles = [];

    protected array $adminSession = [
        'admin_logged_in' => true,
        'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
        'admin_username'  => 'pilot_admin',
        'admin_full_name' => 'Pilot System Administrator',
        'admin_role'      => 'ROLE_ADMIN',
    ];

    protected array $managerSession = [
        'admin_logged_in' => true,
        'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
        'admin_username'  => 'pilot_manager',
        'admin_full_name' => 'Pilot Duty Manager',
        'admin_role'      => 'ROLE_MANAGER',
    ];

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

    private function createSyntheticJpeg(int $width = 320, int $height = 240, int $r = 30, int $g = 90, int $b = 180): string
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

    private function createPilotDevice(string $terminalMode = 'ENTRY_ONLY'): array
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now      = date('Y-m-d H:i:s');

        $deviceData = [
            'id'                  => $deviceId,
            'device_name'         => 'Pilot Android Device ' . bin2hex(random_bytes(2)),
            'device_identifier'   => 'PILOT-DEV-' . bin2hex(random_bytes(2)),
            'device_type'         => 'TABLET',
            'terminal_mode'       => $terminalMode,
            'device_epoch_id'     => EmployeeModel::generateUuidV4(),
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
            'mode'      => $terminalMode,
            'ident'     => $deviceData['device_identifier'],
        ];
    }

    private function createPilotEmployee(string $pin = '654321', string $status = 'ACTIVE'): array
    {
        $id = EmployeeModel::generateUuidV4();
        $code = 'PILOT-STAFF-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));

        $data = [
            'id'          => $id,
            'employee_id' => $code,
            'full_name'   => 'Pilot Staff ' . bin2hex(random_bytes(2)),
            'department'  => 'Science',
            'designation' => 'Teacher',
            'status'      => $status,
            'pin_hash'    => password_hash($pin, PASSWORD_BCRYPT),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ];

        $this->employeeModel->insert($data);
        return $data;
    }

    // =========================================================================
    // 1. Kiosk Page Initial Load & PWA Meta Verification
    // =========================================================================

    public function testKioskPageInitialRenderAndPwaMeta()
    {
        $res = $this->get('/attendance');
        $res->assertStatus(200);
        $body = $res->response()->getBody();

        // Responsive Mobile Viewport Meta
        $this->assertStringContainsString('name="viewport" content="width=device-width, initial-scale=1.0', $body);

        // PWA Manifest link
        $this->assertStringContainsString('rel="manifest" href="/manifest.json"', $body);

        // Kiosk layout components (Automated touchless face recognition)
        $this->assertStringContainsString('scanner-video', $body);
        $this->assertStringContainsString('scanner-canvas', $body);
        $this->assertStringContainsString('face-scan-reticle', $body);
        $this->assertStringContainsString('scanner-status-pill', $body);
        $this->assertStringContainsString('punch-success-overlay', $body);
        $this->assertStringContainsString('scanner-sleep-screen', $body);

        // Security headers & view checks
        $csp = $res->response()->getHeaderLine('Content-Security-Policy');
        if ($csp !== '') {
            $this->assertStringContainsString("default-src 'self'", $csp);
        }
        $this->assertNotEmpty($body);
    }

    // =========================================================================
    // 2. PWA Manifest & Service Worker Endpoints
    // =========================================================================

    public function testPwaManifestAndServiceWorkerAssets()
    {
        $manifestPath = FCPATH . 'manifest.json';
        $this->assertFileExists($manifestPath);
        $manifestJson = json_decode(file_get_contents($manifestPath), true);
        $this->assertSame('Edifies School Attendance Kiosk', $manifestJson['name']);
        $this->assertSame('standalone', $manifestJson['display']);
        $this->assertSame('/attendance', $manifestJson['start_url']);

        $swPath = FCPATH . 'sw.js';
        $this->assertFileExists($swPath);
        $swContent = file_get_contents($swPath);
        $this->assertStringContainsString('edifies-kiosk-shell-v1.4.0', $swContent);
        $this->assertStringContainsString('/js/kiosk-pin-attendance.js', $swContent);
        $this->assertStringContainsString("url.pathname.startsWith('/admin')", $swContent);
        $this->assertStringContainsString("url.pathname.startsWith('/api')", $swContent);
    }

    // =========================================================================
    // 3. Complete ENTRY Workflow (PIN -> Photo -> DB Event -> Record -> Photo)
    // =========================================================================

    public function testCompleteEntryWorkflowWithPhotoAndPin()
    {
        $device = $this->createPilotDevice('ENTRY_ONLY');
        $emp = $this->createPilotEmployee('654321', 'ACTIVE');
        $clientUuid = EmployeeModel::generateUuidV4();

        try {
            // A. Invalid PIN -> Rejection
            $wrongPinRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '000000',
            ]);
            $wrongPinRes->assertStatus(401);
            $wrongJson = json_decode($wrongPinRes->getJSON(), true);
            $this->assertSame('INVALID_CREDENTIALS', $wrongJson['code']);

            // B. Correct PIN -> Success Token
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ]);
            $verifRes->assertStatus(200);
            $verifJson = json_decode($verifRes->getJSON(), true);
            $token = $verifJson['verification_token'];
            $this->assertNotEmpty($token);
            $this->assertSame('ENTRY', $verifJson['action']);
            $this->assertSame($emp['full_name'], $verifJson['employee']['full_name']);

            // C. Confirm Punch with Photo
            $photoBinary = $this->createSyntheticJpeg(320, 240, 25, 110, 210);
            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($photoBinary);

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
            $confJson = json_decode($confRes->getJSON(), true);
            $this->assertSame('success', $confJson['status']);
            $this->assertSame('CHECK_IN', $confJson['action']);
            $this->assertTrue($confJson['photo_captured']);
            $this->assertNotEmpty($confJson['photo_id']);
            $this->assertNotEmpty($confJson['event_id']);
            $this->assertNotEmpty($confJson['record_id']);

            // D. Database Consistency Checks
            // 1. Attendance Event
            $event = $this->eventModel->find($confJson['event_id']);
            $this->assertNotNull($event);
            $this->assertSame('CHECK_IN', $event['event_type']);
            $this->assertSame('PROCESSED', $event['processing_status']);
            $this->assertSame($clientUuid, $event['client_uuid']);

            // 2. Attendance Daily Summary Record
            $record = $this->recordModel->find($confJson['record_id']);
            $this->assertNotNull($record);
            $this->assertSame('CHECKED_IN', $record['status']);
            $this->assertNotEmpty($record['check_in_time']);
            $this->assertNull($record['check_out_time']);

            // 3. Attendance Photo
            $photo = $this->photoModel->find($confJson['photo_id']);
            $this->assertNotNull($photo);
            $this->assertSame('CHECK_IN', $photo['photo_type']);
            $this->assertSame($event['id'], $photo['event_id']);
            $this->assertSame($record['id'], $photo['attendance_record_id']);

            // 4. Physical Storage File
            $filePath = $this->photoService->resolveSecurePath($photo['storage_path']);
            $this->assertNotNull($filePath);
            $this->assertFileExists($filePath);
            $this->createdFiles[] = $filePath;
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 4. Complete EXIT Workflow & Dual-Punch Association
    // =========================================================================

    public function testCompleteExitWorkflowAndPhotoAssociation()
    {
        $entryDevice = $this->createPilotDevice('ENTRY_ONLY');
        $exitDevice  = $this->createPilotDevice('EXIT_ONLY');
        $emp = $this->createPilotEmployee('654321', 'ACTIVE');

        try {
            // 1. Check-In via Entry Terminal
            $tokenIn = json_decode($this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $entryDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ])->getJSON(), true)['verification_token'];

            $timeIn = (new \DateTimeImmutable('now - 1 hour', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');
            $timeOut = (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('Y-m-d H:i:s');

            $photoInBinary = $this->createSyntheticJpeg(320, 240, 20, 150, 40); // Green
            $confInRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $entryDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $tokenIn,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'timestamp'          => $timeIn,
                'photo'              => 'data:image/jpeg;base64,' . base64_encode($photoInBinary),
            ]);
            $confInRes->assertStatus(200);
            $inJson = json_decode($confInRes->getJSON(), true);

            // 2. Check-Out via Exit Terminal
            $tokenOutRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $exitDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ]);
            $tokenOutRes->assertStatus(200);
            $tokenOutJson = json_decode($tokenOutRes->getJSON(), true);
            $this->assertSame('EXIT', $tokenOutJson['action']);
            $tokenOut = $tokenOutJson['verification_token'];

            $photoOutBinary = $this->createSyntheticJpeg(320, 240, 180, 40, 40); // Red
            $confOutRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $exitDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $tokenOut,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'timestamp'          => $timeOut,
                'photo'              => 'data:image/jpeg;base64,' . base64_encode($photoOutBinary),
            ]);
            $confOutRes->assertStatus(200);
            $outJson = json_decode($confOutRes->getJSON(), true);

            $this->assertSame('success', $outJson['status']);
            $this->assertSame('CHECK_OUT', $outJson['action']);
            $this->assertTrue($outJson['photo_captured']);

            // 3. Database Consistency Verifications
            $record = $this->recordModel->find($inJson['record_id']);
            $this->assertNotNull($record);
            $this->assertSame('CHECKED_OUT', $record['status']);
            $this->assertNotEmpty($record['check_in_time']);
            $this->assertNotEmpty($record['check_out_time']);

            // Both Check-In and Check-Out photos exist
            $photos = $this->photoModel->where('employee_id', $emp['id'])->orderBy('photo_type', 'ASC')->findAll();
            $this->assertCount(2, $photos);
            $this->assertSame('CHECK_IN', $photos[0]['photo_type']);
            $this->assertSame('CHECK_OUT', $photos[1]['photo_type']);

            foreach ($photos as $p) {
                $f = $this->photoService->resolveSecurePath($p['storage_path']);
                $this->assertFileExists($f);
                $this->createdFiles[] = $f;
            }
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $entryDevice['id'])->orWhere('device_id', $exitDevice['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($entryDevice['id']);
            $this->deviceModel->delete($exitDevice['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 5. Exit Without Open Record Rejection & Photo Cleanup
    // =========================================================================

    public function testExitWithoutOpenRecordRejectionAndPhotoCleanup()
    {
        $exitDevice = $this->createPilotDevice('EXIT_ONLY');
        $emp = $this->createPilotEmployee('654321', 'ACTIVE');

        try {
            $tokenRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $exitDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ]);
            $token = json_decode($tokenRes->getJSON(), true)['verification_token'];

            $photoDataUrl = 'data:image/jpeg;base64,' . base64_encode($this->createSyntheticJpeg());
            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $exitDevice['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => $photoDataUrl,
            ]);

            // Must reject with 422 REJECTED_NO_OPEN_RECORD
            $confRes->assertStatus(422);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('REJECTED_NO_OPEN_RECORD', $json['code']);

            // Zero photos in DB
            $photos = $this->photoModel->where('employee_id', $emp['id'])->countAllResults();
            $this->assertSame(0, $photos);

            // Zero attendance records in DB
            $records = $this->recordModel->where('employee_id', $emp['id'])->countAllResults();
            $this->assertSame(0, $records);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $exitDevice['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($exitDevice['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 6. Admin Reports, Photo Modal Inspection & Manager Permissions
    // =========================================================================

    public function testAdminReportsInspectionAndManagerRoleSeparation()
    {
        $device = $this->createPilotDevice('ENTRY_ONLY');
        $emp = $this->createPilotEmployee('654321', 'ACTIVE');

        try {
            // Create an entry punch with photo for the pilot employee
            $token = json_decode($this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ])->getJSON(), true)['verification_token'];

            $photoBinary = $this->createSyntheticJpeg();
            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'photo'              => 'data:image/jpeg;base64,' . base64_encode($photoBinary),
            ]);
            $photoId = json_decode($confRes->getJSON(), true)['photo_id'];

            // 1. Admin accesses Attendance Reports -> 200 OK (clear headers so isAJAX() is false)
            $adminReportsRes = $this->withHeaders([])->withSession($this->adminSession)->get('/admin/attendance/reports');
            $adminReportsRes->assertStatus(200);
            $reportsBody = (string) $adminReportsRes->response()->getBody();
            $this->assertStringContainsString($emp['full_name'], $reportsBody);
            $this->assertStringContainsString($emp['employee_id'], $reportsBody);
            $this->assertStringContainsString('Audit Photos', $reportsBody);
            $this->assertStringContainsString('photoModalBackdrop', $reportsBody);

            // 2. Admin retrieves Photo Binary -> 200 OK with security headers
            $adminPhotoRes = $this->withSession($this->adminSession)->get('/admin/attendance/photos/' . $photoId);
            $adminPhotoRes->assertStatus(200);
            $this->assertSame('image/jpeg', $adminPhotoRes->response()->getHeaderLine('Content-Type'));
            $this->assertSame('nosniff', $adminPhotoRes->response()->getHeaderLine('X-Content-Type-Options'));
            $cacheControl = $adminPhotoRes->response()->getHeaderLine('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl);
            $this->assertStringContainsString('private', $cacheControl);

            // 3. Manager accesses Staff Directory -> 200 OK
            $managerEmpRes = $this->withSession($this->managerSession)->get('/admin/employees');
            $managerEmpRes->assertStatus(200);

            // 4. Manager accesses Kiosk -> 200 OK
            $managerKioskRes = $this->withSession($this->managerSession)->get('/attendance');
            $managerKioskRes->assertStatus(200);

            // 5. Manager accesses Reports -> 403 Forbidden
            $managerReportsRes = $this->withSession($this->managerSession)->get('/admin/attendance/reports');
            $managerReportsRes->assertStatus(403);

            // 6. Manager accesses Photo endpoint -> 403 Forbidden
            $managerPhotoRes = $this->withSession($this->managerSession)->get('/admin/attendance/photos/' . $photoId);
            $managerPhotoRes->assertStatus(403);
        } finally {
            $this->db->table('attendance_photos')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }
}
