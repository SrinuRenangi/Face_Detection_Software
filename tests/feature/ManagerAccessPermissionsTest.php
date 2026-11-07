<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class ManagerAccessPermissionsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected array $adminSession = [
        'admin_logged_in' => true,
        'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
        'admin_username'  => 'test_admin',
        'admin_full_name' => 'System Administrator',
        'admin_role'      => 'ROLE_ADMIN',
    ];

    protected array $managerSession = [
        'admin_logged_in' => true,
        'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
        'admin_username'  => 'test_manager',
        'admin_full_name' => 'Duty Manager',
        'admin_role'      => 'ROLE_MANAGER',
    ];

    // 1. Admin accessing /admin -> 200 OK
    public function testAdminCanAccessDashboard()
    {
        $result = $this->withSession($this->adminSession)->get('/admin');
        $result->assertStatus(200);
        $result->assertSee('Administrator Dashboard');
        $result->assertSee('ROLE_ADMIN');
    }

    // 2. Manager accessing /admin -> 403 Forbidden
    public function testManagerAccessingAdminReturns403()
    {
        $result = $this->withSession($this->managerSession)->get('/admin');
        $result->assertStatus(403);
        $result->assertSee('403');
        $result->assertSee('Access denied');
        $result->assertDontSee('Administrator Dashboard');
    }

    // 3. Admin accessing /admin/dashboard -> 200 OK
    public function testAdminCanAccessDashboardExplicit()
    {
        $result = $this->withSession($this->adminSession)->get('/admin/dashboard');
        $result->assertStatus(200);
        $result->assertSee('Administrator Dashboard');
    }

    // 4. Manager accessing /admin/dashboard -> 403 Forbidden
    public function testManagerAccessingDashboardExplicitReturns403()
    {
        $result = $this->withSession($this->managerSession)->get('/admin/dashboard');
        $result->assertStatus(403);
        $result->assertSee('403');
        $result->assertSee('Access denied');
    }

    // 5. Admin accessing /admin/employees -> 200 OK
    public function testAdminAccessingEmployeesReturns200WithDashboardLink()
    {
        $result = $this->withSession($this->adminSession)->get('/admin/employees');
        $result->assertStatus(200);
        $result->assertSee('Staff Directory');
        $result->assertSee('Dashboard');
        $result->assertSee('Add New Employee');
    }

    // 6. Manager accessing /admin/employees -> 200 OK, Dashboard link hidden
    public function testManagerAccessingEmployeesReturns200WithDashboardHidden()
    {
        $result = $this->withSession($this->managerSession)->get('/admin/employees');
        $result->assertStatus(200);
        $result->assertSee('Staff Directory');
        $result->assertSee('Manager Access (Read-Only)');
        $result->assertDontSee('Dashboard</span>');
        $result->assertDontSee('Add New Employee');
        // Brand link should lead to /admin/employees instead of /admin
        $result->assertSee('href="/admin/employees" class="brand-link"');
    }

    // 7. Manager accessing /attendance -> 200 OK
    public function testManagerAccessingAttendanceKioskReturns200()
    {
        $result = $this->withSession($this->managerSession)->get('/attendance');
        $result->assertStatus(200);
        $result->assertSee('Attendance Kiosk');
    }

    // 8. Admin accessing /admin/face-lab and /face-lab -> 200 OK
    public function testAdminAccessingFaceLabReturns200()
    {
        $result1 = $this->withSession($this->adminSession)->get('/admin/face-lab');
        $result1->assertStatus(200);
        $result1->assertSee('Face Recognition Feasibility Lab');

        $result2 = $this->withSession($this->adminSession)->get('/face-lab');
        $result2->assertStatus(200);
        $result2->assertSee('Face Recognition Feasibility Lab');
    }

    // 9. Manager accessing /admin/face-lab and /face-lab -> 403 Forbidden
    public function testManagerAccessingFaceLabReturns403()
    {
        $result1 = $this->withSession($this->managerSession)->get('/admin/face-lab');
        $result1->assertStatus(403);
        $result1->assertSee('403');
        $result1->assertSee('Access denied');

        $result2 = $this->withSession($this->managerSession)->get('/face-lab');
        $result2->assertStatus(403);
        $result2->assertSee('403');
        $result2->assertSee('Access denied');
    }

    // 10. Unauthenticated accessing /admin -> 302 Redirect to /admin/login
    public function testUnauthenticatedAccessingAdminRedirectsToLogin()
    {
        $result1 = $this->get('/admin');
        $result1->assertStatus(302);
        $result1->assertRedirectTo('/admin/login');

        $result2 = $this->get('/admin/dashboard');
        $result2->assertStatus(302);
        $result2->assertRedirectTo('/admin/login');

        $result3 = $this->get('/admin/face-lab');
        $result3->assertStatus(302);
        $result3->assertRedirectTo('/admin/login');

        $result4 = $this->get('/face-lab');
        $result4->assertStatus(302);
        $result4->assertRedirectTo('/admin/login');
    }

    // 11. Manager accessing Kiosk Admin or Attendance Review -> 403 Forbidden
    public function testManagerAccessingKioskAdminAndReviewReturns403()
    {
        $result1 = $this->withSession($this->managerSession)->get('/admin/kiosks');
        $result1->assertStatus(403);
        $result1->assertSee('403');

        $result2 = $this->withSession($this->managerSession)->get('/admin/attendance/review');
        $result2->assertStatus(403);
        $result2->assertSee('403');
    }

    // 12. Manager accessing Admin API stats -> 403 Forbidden (JSON)
    public function testManagerAccessingAdminStatsApiReturns403Json()
    {
        $result = $this->withSession($this->managerSession)->get('/api/admin/stats');
        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'FORBIDDEN',
        ]);
    }

    // 13. Manager calling employee mutations -> 403 Forbidden
    public function testManagerAttemptingEmployeeMutationsReturns403()
    {
        $tokenName = csrf_token();
        $tokenHash = csrf_hash();

        $result = $this->withSession($this->managerSession)->withHeaders([
            'X-CSRF-TOKEN' => $tokenHash,
        ])->post('/admin/employees', [
            $tokenName    => $tokenHash,
            'employee_id' => 'FORBIDDEN-01',
            'full_name'   => 'Forbidden Employee',
        ]);
        $result->assertStatus(403);

        $apiResult = $this->withSession($this->managerSession)->withHeaders([
            'X-CSRF-TOKEN'     => $tokenHash,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/admin/employees', [
            $tokenName    => $tokenHash,
            'employee_id' => 'FORBIDDEN-02',
            'full_name'   => 'Forbidden Employee 2',
        ]);
        $apiResult->assertStatus(403);
    }

    // 14. Login Redirection: Manager -> /admin/employees; Admin -> /admin
    public function testLoginRedirectsAppropriatelyPerRole()
    {
        $managerResult = $this->withSession($this->managerSession)->get('/admin/login');
        $managerResult->assertStatus(302);
        $managerResult->assertRedirectTo('/admin/employees');

        $adminResult = $this->withSession($this->adminSession)->get('/admin/login');
        $adminResult->assertStatus(302);
        $adminResult->assertRedirectTo('/admin');
    }
}
