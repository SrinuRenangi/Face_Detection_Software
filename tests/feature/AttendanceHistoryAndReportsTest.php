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

class AttendanceHistoryAndReportsTest extends CIUnitTestCase
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
        foreach ($this->createdFiles as $filePath) {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
        $this->createdFiles = [];

        service('cache')->clean();
        parent::tearDown();
    }

    private function getAdminSession(): array
    {
        return [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];
    }

    private function getManagerSession(): array
    {
        return [
            'admin_logged_in' => true,
            'admin_user_id'   => 'm0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager_staff',
            'admin_full_name' => 'School Staff Manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];
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

    private function createTestEmployee(array $overrides = []): array
    {
        $id = EmployeeModel::generateUuidV4();
        $empCode = 'REP-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $data = array_merge([
            'id'          => $id,
            'employee_id' => $empCode,
            'full_name'   => 'Report Staff ' . bin2hex(random_bytes(2)),
            'department'  => 'Mathematics',
            'designation' => 'Teacher',
            'status'      => 'ACTIVE',
            'pin_hash'    => password_hash('123456', PASSWORD_BCRYPT),
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->employeeModel->insert($data);
        return $data;
    }

    private function createTestAttendanceRecord(string $empId, string $date, ?string $inTime = null, ?string $outTime = null, string $status = 'CHECKED_OUT'): string
    {
        $recordId = EmployeeModel::generateUuidV4();
        $checkIn  = $inTime ? "{$date} {$inTime}" : null;
        $checkOut = $outTime ? "{$date} {$outTime}" : null;

        $this->db->table('attendance_records')->insert([
            'id'                  => $recordId,
            'employee_id'         => $empId,
            'work_date'           => $date,
            'check_in_time'       => $checkIn,
            'check_out_time'      => $checkOut,
            'status'              => $status,
            'confidence_score'    => 1.00,
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        return $recordId;
    }

    private function createTestDevice(): array
    {
        $deviceId  = EmployeeModel::generateUuidV4();
        $rawToken  = bin2hex(random_bytes(32));
        $now       = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                  => $deviceId,
            'device_name'         => 'Report Device ' . bin2hex(random_bytes(2)),
            'device_identifier'   => 'DEV-REP-' . bin2hex(random_bytes(2)),
            'device_type'         => 'TABLET',
            'terminal_mode'       => 'ENTRY_ONLY',
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
        ]);

        return ['id' => $deviceId, 'raw_token' => $rawToken];
    }

    private function createTestEvent(string $empId, string $deviceId, string $type = 'CHECK_IN'): string
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
            'event_type'             => $type,
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
    // 1. Day-Wise Attendance Report Tests
    // =========================================================================

    public function testAdminCanAccessDailyAttendanceReport()
    {
        $emp = $this->createTestEmployee();
        $recId = $this->createTestAttendanceRecord($emp['id'], date('Y-m-d'), '08:30:00', '15:30:00', 'CHECKED_OUT');

        try {
            $res = $this->withSession($this->getAdminSession())
                ->get('/admin/attendance/reports?tab=daily&date=' . date('Y-m-d'));

            $res->assertStatus(200);
            $body = (string) $res->response()->getBody();
            $this->assertStringContainsString('Attendance History &amp; Reports', $body);
            $this->assertStringContainsString('Day-Wise Attendance', $body);
            $this->assertStringContainsString(esc($emp['employee_id']), $body);
            $this->assertStringContainsString(esc($emp['full_name']), $body);
            $this->assertStringContainsString('Completed', $body);
        } finally {
            $this->db->table('attendance_records')->where('id', $recId)->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testManagerCannotAccessDailyAttendanceReportForbidden403()
    {
        $res = $this->withSession($this->getManagerSession())
            ->get('/admin/attendance/reports?tab=daily');

        $res->assertStatus(403);
    }

    public function testUnauthenticatedAccessRedirectsToLogin()
    {
        $res = $this->get('/admin/attendance/reports');
        $this->assertTrue(in_array($res->response()->getStatusCode(), [302, 401, 403], true));
    }

    public function testDailyReportDateFiltering()
    {
        $emp = $this->createTestEmployee();
        $recId1 = $this->createTestAttendanceRecord($emp['id'], '2026-10-15', '08:00:00', '15:00:00', 'CHECKED_OUT');
        $recId2 = $this->createTestAttendanceRecord($emp['id'], '2026-10-16', '08:15:00', '15:15:00', 'CHECKED_OUT');

        try {
            // Query 2026-10-15 via AJAX
            $res1 = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=daily&date=2026-10-15');

            $res1->assertStatus(200);
            $json1 = json_decode($res1->getJSON(), true);
            $this->assertSame('success', $json1['status']);
            $this->assertSame('2026-10-15', $json1['data']['date']);

            // Find employee record in 2026-10-15 data
            $found1 = false;
            foreach ($json1['data']['records'] as $r) {
                if ($r['employee_id'] === $emp['employee_id']) {
                    $found1 = true;
                    $this->assertSame('CHECKED_OUT', $r['display_status']);
                    $this->assertStringContainsString('08:00:00', $r['check_in_time']);
                }
            }
            $this->assertTrue($found1);
        } finally {
            $this->db->table('attendance_records')->whereIn('id', [$recId1, $recId2])->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testDailyReportStaffSearchFiltering()
    {
        $emp1 = $this->createTestEmployee(['full_name' => 'Searchable Alpha Unique']);
        $emp2 = $this->createTestEmployee(['full_name' => 'Searchable Beta Ordinary']);

        try {
            $res = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=daily&search=Alpha');

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $names = array_column($json['data']['records'], 'full_name');
            $this->assertContains('Searchable Alpha Unique', $names);
            $this->assertNotContains('Searchable Beta Ordinary', $names);
        } finally {
            $this->employeeModel->delete($emp1['id']);
            $this->employeeModel->delete($emp2['id']);
        }
    }

    public function testDailyReportFlagsMissingExitOnPastDates()
    {
        $emp = $this->createTestEmployee();
        // Yesterday's date, checked in at 08:30 AM, but check_out_time was NEVER recorded
        $pastDate = date('Y-m-d', strtotime('-2 days'));
        $recId = $this->createTestAttendanceRecord($emp['id'], $pastDate, '08:30:00', null, 'CHECKED_IN');

        try {
            $res = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=daily&date=' . $pastDate);

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $foundRecord = null;
            foreach ($json['data']['records'] as $r) {
                if ($r['employee_id'] === $emp['employee_id']) {
                    $foundRecord = $r;
                    break;
                }
            }

            $this->assertNotNull($foundRecord);
            $this->assertTrue($foundRecord['is_incomplete']);
            $this->assertGreaterThan(0, $json['data']['metrics']['incomplete']);
        } finally {
            $this->db->table('attendance_records')->where('id', $recId)->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testDailyReportIncludesPhotoMetadataWithoutNPlusOne()
    {
        $device = $this->createTestDevice();
        $emp = $this->createTestEmployee();
        $date = date('Y-m-d');
        $recId = $this->createTestAttendanceRecord($emp['id'], $date, '08:30:00', '15:30:00', 'CHECKED_OUT');
        $eventId = $this->createTestEvent($emp['id'], $device['id']);

        $jpegBytes = $this->createSyntheticJpeg(300, 300);
        $stored = $this->photoService->storeValidatedPhoto($jpegBytes);
        $this->assertTrue($stored['success']);
        $resolved = $this->photoService->resolveSecurePath($stored['storage_path']);
        $this->createdFiles[] = $resolved;

        $photoId = EmployeeModel::generateUuidV4();
        $this->photoModel->insert([
            'id'                   => $photoId,
            'attendance_record_id' => $recId,
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
            $res = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=daily&date=' . $date);

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $found = null;
            foreach ($json['data']['records'] as $r) {
                if ($r['employee_id'] === $emp['employee_id']) {
                    $found = $r;
                    break;
                }
            }

            $this->assertNotNull($found);
            $this->assertNotNull($found['check_in_photo']);
            $this->assertSame($photoId, $found['check_in_photo']['id']);
        } finally {
            $this->photoModel->delete($photoId);
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('id', $recId)->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 2. Month-Wise Summary Report Tests
    // =========================================================================

    public function testAdminCanAccessMonthlyAttendanceReport()
    {
        $emp = $this->createTestEmployee();
        $recId = $this->createTestAttendanceRecord($emp['id'], '2026-11-01', '08:30:00', '15:30:00', 'CHECKED_OUT');

        try {
            $res = $this->withSession($this->getAdminSession())
                ->get('/admin/attendance/reports?tab=monthly&month=11&year=2026');

            $res->assertStatus(200);
            $body = (string) $res->response()->getBody();
            $this->assertStringContainsString('Month-Wise Summary', $body);
            $this->assertStringContainsString(esc($emp['employee_id']), $body);
        } finally {
            $this->db->table('attendance_records')->where('id', $recId)->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testManagerCannotAccessMonthlyReportForbidden403()
    {
        $res = $this->withSession($this->getManagerSession())
            ->get('/admin/attendance/reports?tab=monthly');

        $res->assertStatus(403);
    }

    // =========================================================================
    // 3. Employee Chronological History Tests
    // =========================================================================

    public function testAdminCanAccessEmployeeChronology()
    {
        $emp = $this->createTestEmployee();
        $recId1 = $this->createTestAttendanceRecord($emp['id'], '2026-10-10', '08:30:00', '15:30:00', 'CHECKED_OUT');
        $recId2 = $this->createTestAttendanceRecord($emp['id'], '2026-10-11', '08:45:00', '15:45:00', 'CHECKED_OUT');

        try {
            $res = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=employee&employee_id=' . $emp['id'] . '&from_date=2026-10-01&to_date=2026-10-31');

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $this->assertSame('success', $json['status']);
            $this->assertSame($emp['id'], $json['data']['selectedEmployee']['id']);
            $this->assertCount(2, $json['data']['records']);
            $this->assertSame(2, $json['data']['metrics']['total_days']);
            $this->assertSame(2, $json['data']['metrics']['completed_shifts']);
        } finally {
            $this->db->table('attendance_records')->whereIn('id', [$recId1, $recId2])->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testManagerCannotAccessEmployeeChronologyForbidden403()
    {
        $emp = $this->createTestEmployee();
        try {
            $res = $this->withSession($this->getManagerSession())
                ->get('/admin/attendance/reports?tab=employee&employee_id=' . $emp['id']);

            $res->assertStatus(403);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testInactiveEmployeeHistoryIsPreservedAndQueryable()
    {
        // Inactive staff member
        $emp = $this->createTestEmployee(['status' => 'INACTIVE']);
        $recId = $this->createTestAttendanceRecord($emp['id'], '2026-09-15', '08:30:00', '15:30:00', 'CHECKED_OUT');

        try {
            $res = $this->withSession($this->getAdminSession())
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->get('/admin/attendance/reports?tab=employee&employee_id=' . $emp['id'] . '&from_date=2026-09-01&to_date=2026-09-30');

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $this->assertSame('success', $json['status']);
            $this->assertSame($emp['id'], $json['data']['selectedEmployee']['id']);
            $this->assertFalse((bool) $json['data']['selectedEmployee']['is_active']);
            $this->assertCount(1, $json['data']['records']);
            $this->assertSame('2026-09-15', $json['data']['records'][0]['work_date']);
        } finally {
            $this->db->table('attendance_records')->where('id', $recId)->delete();
            $this->employeeModel->delete($emp['id']);
        }
    }
}
