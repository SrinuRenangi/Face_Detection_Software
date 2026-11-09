<?php

namespace Tests\Feature;

use App\Models\AdminUserModel;
use App\Models\SystemSettingModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class SuperAdminAndKillSwitchSettingsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected AdminUserModel $userModel;
    protected SystemSettingModel $settingModel;

    protected string $superAdminId = 'test-super-admin-001';
    protected string $scannerUserId = 'test-scanner-user-001';
    protected string $testManagerId = 'test-manager-user-002';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->userModel = new AdminUserModel();
        $this->settingModel = new SystemSettingModel();

        $_COOKIE = [];

        // Clean test users
        $this->userModel->whereIn('id', [$this->superAdminId, $this->scannerUserId, $this->testManagerId])->delete();

        // Seed test superadmin
        $this->userModel->insert([
            'id'            => $this->superAdminId,
            'username'      => 'unit_superadmin',
            'email'         => 'super@edifies.school',
            'password_hash' => password_hash('SuperPass@123', PASSWORD_BCRYPT),
            'full_name'     => 'Unit Test Super Administrator',
            'role'          => 'ROLE_SUPER_ADMIN',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Seed test scanner user
        $this->userModel->insert([
            'id'            => $this->scannerUserId,
            'username'      => 'unit_scanner',
            'email'         => 'scanner@edifies.school',
            'password_hash' => password_hash('ScannerPass@123', PASSWORD_BCRYPT),
            'full_name'     => 'Unit Test Scanner Account',
            'role'          => 'ROLE_SCANNER',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Seed test manager
        $this->userModel->insert([
            'id'            => $this->testManagerId,
            'username'      => 'unit_manager',
            'email'         => 'manager@edifies.school',
            'password_hash' => password_hash('ManagerPass@123', PASSWORD_BCRYPT),
            'full_name'     => 'Unit Test Manager Account',
            'role'          => 'ROLE_MANAGER',
            'is_active'     => 1,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Ensure killswitch is inactive by default
        $this->settingModel->setSetting('app_killswitch_active', '0', 'TEST_SETUP');
    }

    protected function tearDown(): void
    {
        $this->settingModel->setSetting('app_killswitch_active', '0', 'TEST_TEARDOWN');
        $this->userModel->whereIn('id', [$this->superAdminId, $this->scannerUserId, $this->testManagerId])->delete();
        $_COOKIE = [];
        parent::tearDown();
    }

    protected function postWithCsrf(string $uri, array $data = [], array $headers = [])
    {
        $token = csrf_hash();
        $name = csrf_token();
        $data[$name] = $token;
        $headers['X-CSRF-TOKEN'] = $token;

        return $this->withHeaders($headers)->post($uri, $data);
    }

    public function testSuperAdminLoginRedirectsToDeveloperControl()
    {
        $result = $this->postWithCsrf('/login', [
            'username' => 'unit_superadmin',
            'password' => 'SuperPass@123',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/developer/control');
        $this->assertTrue(session()->get('admin_logged_in'));
        $this->assertSame('ROLE_SUPER_ADMIN', session()->get('admin_role'));
    }

    public function testScannerLoginRedirectsToScannerEndpoint()
    {
        $result = $this->postWithCsrf('/login', [
            'username' => 'unit_scanner',
            'password' => 'ScannerPass@123',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/scanner');
        $this->assertTrue(session()->get('admin_logged_in'));
        $this->assertSame('ROLE_SCANNER', session()->get('admin_role'));
    }

    public function testKillswitchBlocksNonDeveloperRoutesWithHttp503()
    {
        // Engage killswitch
        $this->settingModel->setSetting('app_killswitch_active', '1', 'TEST');
        $this->settingModel->setSetting('killswitch_message', 'Emergency maintenance active for tests.', 'TEST');

        // Admin route should receive 503
        $adminResult = $this->get('/admin');
        $adminResult->assertStatus(503);
        $adminResult->assertSee('Access Suspended');
        $adminResult->assertSee('Emergency maintenance active for tests.');

        // Attendance kiosk should receive 503
        $kioskResult = $this->get('/attendance');
        $kioskResult->assertStatus(503);

        // API route receives 503 JSON
        $apiResult = $this->get('/api/kiosk/status');
        $apiResult->assertStatus(503);
    }

    public function testSuperAdminCanAccessDeveloperControlEvenWhenKillswitchActive()
    {
        $this->settingModel->setSetting('app_killswitch_active', '1', 'TEST');

        $superSession = [
            'admin_logged_in' => true,
            'admin_user_id'   => $this->superAdminId,
            'admin_username'  => 'unit_superadmin',
            'admin_full_name' => 'Unit Test Super Administrator',
            'admin_role'      => 'ROLE_SUPER_ADMIN',
        ];

        $devResult = $this->withSession($superSession)->get('/developer/control');
        $devResult->assertStatus(200);
        $devResult->assertSee('Developer System Override');
        $devResult->assertSee('EMERGENCY KILLSWITCH ENGAGED');
    }

    public function testSettingsPageRendersForAuthenticatedUsers()
    {
        $adminSession = [
            'admin_logged_in' => true,
            'admin_user_id'   => $this->superAdminId,
            'admin_username'  => 'unit_superadmin',
            'admin_full_name' => 'Unit Test Super Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $result = $this->withSession($adminSession)->get('/admin/settings');
        $result->assertStatus(200);
        $result->assertSee('Profile & Password');
        $result->assertSee('Organization & Tenant');
    }

    public function testPasswordChangeInvalidatesSessionAndRedirectsToLogin()
    {
        $managerSession = [
            'admin_logged_in' => true,
            'admin_user_id'   => $this->testManagerId,
            'admin_username'  => 'unit_manager',
            'admin_full_name' => 'Unit Test Manager Account',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        $result = $this->withSession($managerSession)->postWithCsrf('/admin/settings/password', [
            'current_password' => 'ManagerPass@123',
            'new_password'     => 'NewSecretPass@999',
            'confirm_password' => 'NewSecretPass@999',
        ]);

        $result->assertStatus(302);
        $result->assertRedirectTo('/login');

        // Verify password in DB was updated
        $updatedUser = $this->userModel->find($this->testManagerId);
        $this->assertTrue(password_verify('NewSecretPass@999', $updatedUser['password_hash']));
    }
}
