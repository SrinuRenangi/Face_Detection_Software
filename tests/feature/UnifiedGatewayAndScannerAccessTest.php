<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use App\Models\AdminUserModel;
use App\Models\KioskDeviceModel;
use App\Models\EmployeeModel;
use Config\Database;

class UnifiedGatewayAndScannerAccessTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected AdminUserModel $userModel;
    protected KioskDeviceModel $deviceModel;

    protected string $adminId = 'a1111111-1111-1111-1111-111111111111';
    protected string $managerId = 'a2222222-2222-2222-2222-222222222222';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->userModel = new AdminUserModel();
        $this->deviceModel = new KioskDeviceModel();

        // Clear superglobals & cookies for isolation between tests
        $_COOKIE = [];

        // Ensure clean test state for test admin & manager and pairing codes
        $this->db->table('admin_users')->whereIn('id', [$this->adminId, $this->managerId])->delete();
        $this->db->table('kiosk_pairing_codes')->where('created_by', 'gate_admin')->delete();
        $this->db->table('kiosk_devices')->where('device_name', 'Unified Test Scanner')->delete();
        $this->db->table('kiosk_devices')->where('device_name', 'Isolation Scanner')->delete();

        // Seed test admin
        $this->userModel->insert([
            'id'            => $this->adminId,
            'username'      => 'gate_admin',
            'email'         => 'gate_admin@edifies.edu',
            'password_hash' => password_hash('AdminPass@123', PASSWORD_BCRYPT),
            'full_name'     => 'Gateway Administrator',
            'role'          => 'ROLE_ADMIN',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Seed test manager
        $this->userModel->insert([
            'id'            => $this->managerId,
            'username'      => 'gate_manager',
            'email'         => 'gate_manager@edifies.edu',
            'password_hash' => password_hash('ManagerPass@123', PASSWORD_BCRYPT),
            'full_name'     => 'Gateway Manager',
            'role'          => 'ROLE_MANAGER',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('admin_users')->whereIn('id', [$this->adminId, $this->managerId])->delete();
        $_COOKIE = [];
        parent::tearDown();
    }

    /**
     * Helper to perform POST requests with valid CSRF tokens
     */
    protected function postWithCsrf(string $uri, array $data = [], array $headers = [])
    {
        $token = csrf_hash();
        $name = csrf_token();
        $data[$name] = $token;
        $headers['X-CSRF-TOKEN'] = $token;

        return $this->withHeaders($headers)->post($uri, $data);
    }

    // 1. Central Gateway (/) renders unified access page
    public function testCentralGatewayRendersUnifiedInterface()
    {
        $result = $this->get('/');
        $result->assertStatus(200);
        $result->assertSee('Staff Attendance Management System');
        $result->assertSee('Staff Sign In');
        $result->assertSee('Launch Tablet Kiosk');
        $result->assertSee('Pair Scanner with 1-Time Code');
        $result->assertSee('Backend Foundation Status');
    }

    // 2. Gateway auto-redirects active Admin session to /admin
    public function testGatewayRedirectsActiveAdminSession()
    {
        $session = [
            'admin_logged_in' => true,
            'admin_user_id'   => $this->adminId,
            'admin_username'  => 'gate_admin',
            'admin_full_name' => 'Gateway Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $result = $this->withSession($session)->get('/');
        $result->assertStatus(302);
        $result->assertRedirectTo('/admin');
    }

    // 3. Gateway auto-redirects active Manager session to /admin/employees
    public function testGatewayRedirectsActiveManagerSession()
    {
        $session = [
            'admin_logged_in' => true,
            'admin_user_id'   => $this->managerId,
            'admin_username'  => 'gate_manager',
            'admin_full_name' => 'Gateway Manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        $result = $this->withSession($session)->get('/');
        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/employees');
    }

    // 4. GET /login renders login interface
    public function testHumanLoginRouteRendersForm()
    {
        $result = $this->get('/login');
        $result->assertStatus(200);
        $result->assertSee('Administrator Sign In');
        $result->assertSee('Username');
        $result->assertSee('Password');
    }

    // 5. POST /login with Admin credentials redirects to /admin
    public function testAdminLoginViaUnifiedLoginRouteRedirectsToAdmin()
    {
        $result = $this->postWithCsrf('/login', [
            'username' => 'gate_admin',
            'password' => 'AdminPass@123',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/admin');
        $this->assertTrue(session()->get('admin_logged_in'));
        $this->assertSame('ROLE_ADMIN', session()->get('admin_role'));
    }

    // 6. POST /login with Manager credentials redirects to /admin/employees
    public function testManagerLoginViaUnifiedLoginRouteRedirectsToManagerLanding()
    {
        $result = $this->postWithCsrf('/login', [
            'username' => 'gate_manager',
            'password' => 'ManagerPass@123',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/employees');
        $this->assertTrue(session()->get('admin_logged_in'));
        $this->assertSame('ROLE_MANAGER', session()->get('admin_role'));
    }

    // 7. POST /login with invalid credentials returns 302 back with error
    public function testInvalidCredentialsOnUnifiedLoginReturnsError()
    {
        $result = $this->postWithCsrf('/login', [
            'username' => 'gate_admin',
            'password' => 'WrongPassword!999',
        ]);

        $result->assertStatus(302);
        $result->assertSessionHas('error');
    }

    // 8. Dedicated scanner pairing GET /kiosk/pair renders pairing view
    public function testScannerPairingRouteRendersForm()
    {
        $result = $this->get('/kiosk/pair');
        $result->assertStatus(200);
        $result->assertSee('Scanner Device Pairing');
        $result->assertSee('Single-Use Pairing Code');
        $result->assertSee('Authorize &amp; Pair Scanner');
    }

    // 9. POST /kiosk/pair with valid code pairs device, sets cookie, redirects to /attendance
    public function testScannerPairingWithValidCodeSetsCookieAndRedirects()
    {
        $rawCode = 'DEADBEEFCAFE0123456789ABCDEF0123';
        $codeHash = hash('sha256', $rawCode);
        $codeId = EmployeeModel::generateUuidV4();

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Unified Test Scanner',
            'pairing_code_hash' => $codeHash,
            'paired_device_id'  => null,
            'status'            => 'PENDING',
            'failed_attempts'   => 0,
            'max_attempts'      => 5,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+1 hour')),
            'created_by'        => 'gate_admin',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $deviceId = null;
        try {
            $result = $this->postWithCsrf('/kiosk/pair', [
                'pairing_code' => $rawCode,
                'device_name'  => 'Unified Test Scanner',
                'device_type'  => 'TABLET',
            ]);

            $result->assertStatus(302);
            $result->assertRedirectTo('/attendance');

            // Verify cookie is set
            $cookie = $result->response()->getCookie('edifies_kiosk_token');
            $this->assertNotNull($cookie);
            $this->assertTrue($cookie->isHTTPOnly());
            $this->assertSame('Strict', $cookie->getSameSite());
            $this->assertSame('/api/kiosk', $cookie->getPath());

            // Verify DB state
            $codeRecord = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertSame('USED', $codeRecord['status']);
            $this->assertNotEmpty($codeRecord['paired_device_id']);
            $deviceId = $codeRecord['paired_device_id'];

            $device = $this->deviceModel->find($deviceId);
            $this->assertNotNull($device);
            $this->assertSame('ACTIVE', $device['status']);
            $this->assertSame('Unified Test Scanner', $device['device_name']);
        } finally {
            if ($deviceId) {
                $this->db->table('kiosk_time_anchors')->where('device_id', $deviceId)->delete();
                $this->deviceModel->delete($deviceId);
            }
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    // 10. POST /kiosk/pair with invalid code fails gracefully
    public function testScannerPairingWithInvalidCodeFails()
    {
        $result = $this->postWithCsrf('/kiosk/pair', [
            'pairing_code' => 'INVALID_SHORT_CODE',
        ]);

        $result->assertStatus(302);
        $result->assertSessionHas('error');
    }

    // 11. POST /kiosk/pair with expired code fails
    public function testScannerPairingWithExpiredCodeFails()
    {
        $rawCode = 'AABBCCDDEEFF00112233445566778899';
        $codeHash = hash('sha256', $rawCode);
        $codeId = EmployeeModel::generateUuidV4();

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Expired Test Scanner',
            'pairing_code_hash' => $codeHash,
            'status'            => 'EXPIRED',
            'failed_attempts'   => 0,
            'max_attempts'      => 5,
            'expires_at'        => date('Y-m-d H:i:s', strtotime('-1 hour')),
            'created_by'        => 'gate_admin',
            'created_at'        => date('Y-m-d H:i:s', strtotime('-2 hours')),
        ]);

        try {
            $result = $this->postWithCsrf('/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);

            $result->assertStatus(302);
            $result->assertSessionHas('error');
        } finally {
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    // 12. Device credential isolation from human sessions
    public function testDeviceCredentialIsolation()
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $deviceId = EmployeeModel::generateUuidV4();

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Isolation Scanner',
            'device_identifier' => 'DEV-ISOLATE',
            'terminal_mode'     => 'ENTRY_ONLY',
            'status'            => 'ACTIVE',
            'device_token_hash' => $tokenHash,
            'device_epoch_id'   => EmployeeModel::generateUuidV4(),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        try {
            // A request with ONLY device cookie cannot access human /admin panel
            $adminResult = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/admin');

            $adminResult->assertStatus(302);
            $adminResult->assertRedirectTo('/admin/login');

            // A human session without device cookie cannot call device-only API /api/kiosk/status
            $_COOKIE = [];
            $apiResult = $this->withSession([
                'admin_logged_in' => true,
                'admin_user_id'   => $this->adminId,
                'admin_username'  => 'gate_admin',
                'admin_role'      => 'ROLE_ADMIN',
            ])->withHeaders([
                'Cookie' => '',
            ])->get('/api/kiosk/status');

            $apiResult->assertStatus(401);
            $json = json_decode($apiResult->getJSON(), true);
            $this->assertSame('DEVICE_TOKEN_MISSING', $json['code']);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    // 13. Dedicated scanner interface renders correctly
    public function testAttendanceScannerInterfaceRenders()
    {
        $result = $this->get('/attendance');
        $result->assertStatus(200);
        $result->assertSee('Staff Attendance Kiosk');
        $result->assertSee('Terminal:');
        $result->assertSee('Touchless Presence Verification System');
        $result->assertSee('PIN Attendance');
        $result->assertSee('Biometric Engine Staged');
    }
}
