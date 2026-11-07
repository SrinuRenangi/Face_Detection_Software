<?php

namespace Tests\Feature;

use App\Models\EmployeeModel;
use App\Models\AdminAuditLogModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class EmployeeManagementFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected EmployeeModel $employeeModel;
    protected AdminAuditLogModel $auditModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->employeeModel = new EmployeeModel();
        $this->auditModel    = new AdminAuditLogModel();
    }

    public function testDirectoryRequiresAdminAuthentication()
    {
        $result = $this->get('/admin/employees');
        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/login');
    }

    public function testAdminRoleCanViewEmployeeDirectory()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $result = $this->withSession($sessionData)->get('/admin/employees');
        $result->assertStatus(200);
        $result->assertSee('Staff Directory');
        $result->assertSee('Add New Employee');
        $result->assertSee('ROLE_ADMIN');
    }

    public function testManagerRoleCanViewDirectoryInReadOnlyMode()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_full_name' => 'Duty Manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        $result = $this->withSession($sessionData)->get('/admin/employees');
        $result->assertStatus(200);
        $result->assertSee('Staff Directory');
        $result->assertSee('Manager Access (Read-Only)');
        $result->assertDontSee('Add New Employee');
    }

    public function testAdminApiShowEmployeeDetails()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $empId = 'SHOW-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Dr. Alice Martin',
            'department'  => 'Chemistry',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_full_name' => 'System Administrator',
            'admin_role'      => 'ROLE_ADMIN',
        ];

        $result = $this->withSession($sessionData)->get('/api/admin/employees/' . $empUuid);
        $result->assertStatus(200);
        $result->assertJSONFragment([
            'status' => 'success',
            'employee' => [
                'id'          => $empUuid,
                'employee_id' => $empId,
                'full_name'   => 'Dr. Alice Martin',
                'department'  => 'Chemistry',
                'face_status' => 'NOT_REGISTERED',
            ],
        ]);

        $this->employeeModel->delete($empUuid);
    }

    public function testManagerRoleCannotCreateEmployeeRedirect()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        session()->set($sessionData);

        $request = service('request');
        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->store();
        $this->assertSame(302, $response->getStatusCode());
        $this->assertStringContainsString('/admin/employees', $response->getHeaderLine('Location'));
    }

    public function testManagerRoleCannotCreateEmployeeAjaxReturns403()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        session()->set($sessionData);

        $request = service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->store();
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('FORBIDDEN', $body['code']);
    }

    public function testManagerRoleCannotUpdateEmployee()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        session()->set($sessionData);

        $request = service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->update('any-id');
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('FORBIDDEN', $body['code']);
    }

    public function testManagerRoleCannotToggleEmployeeStatus()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        session()->set($sessionData);

        $request = service('request')->setHeader('X-Requested-With', 'XMLHttpRequest');
        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController($request, service('response'), service('logger'));

        $response = $controller->toggleStatus('any-id');
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('FORBIDDEN', $body['code']);
    }

    public function testManagerRoleCannotDeleteEmployee()
    {
        $sessionData = [
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
            'admin_username'  => 'manager',
            'admin_role'      => 'ROLE_MANAGER',
        ];

        session()->set($sessionData);

        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController(service('request'), service('response'), service('logger'));

        $response = $controller->delete('any-id');
        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('FORBIDDEN', $body['code']);
    }

    public function testEmployeeDeletionSafeguardBlockedByAttendanceHistory()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $attUuid = EmployeeModel::generateUuidV4();
        $empId = 'DEL-ATT-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Cannot Delete History Exists',
            'department'  => 'History',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->db->table('attendance_records')->insert([
            'id'             => $attUuid,
            'employee_id'    => $empUuid,
            'work_date'      => date('Y-m-d'),
            'status'         => 'CHECKED_IN',
            'check_in_time'  => date('Y-m-d 08:00:00'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController(service('request'), service('response'), service('logger'));
        session()->set([
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_role'      => 'ROLE_ADMIN',
        ]);

        // Attempt deletion
        $response = $controller->delete($empUuid);
        $this->assertSame(409, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('ATTENDANCE_HISTORY_EXISTS', $body['code']);

        // Verify record still exists
        $stillExists = $this->employeeModel->find($empUuid);
        $this->assertNotNull($stillExists);

        // Cleanup
        $this->db->table('attendance_records')->where('id', $attUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testEmployeeDeletionSafeguardBlockedByFaceTemplate()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $tmplUuid = EmployeeModel::generateUuidV4();
        $empId = 'DEL-BIO-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Cannot Delete Template Exists',
            'department'  => 'Biometrics',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $dummyBlob = base64_encode(str_repeat("\x00\x00\x80\x3f", 512));
        $this->db->table('employee_face_templates')->insert([
            'id'                => $tmplUuid,
            'employee_id'       => $empUuid,
            'embedding_data'    => $dummyBlob,
            'template_version'  => 'human-v3-arcface',
            'vector_dimensions' => 512,
            'model_name'        => 'ArcFace',
            'quality_score'     => 0.9500,
            'registered_by'     => 'admin',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $controller = new \App\Controllers\Admin\EmployeeController();
        $controller->initController(service('request'), service('response'), service('logger'));
        session()->set([
            'admin_logged_in' => true,
            'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
            'admin_username'  => 'admin',
            'admin_role'      => 'ROLE_ADMIN',
        ]);

        // Attempt deletion
        $response = $controller->delete($empUuid);
        $this->assertSame(409, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('BIOMETRIC_TEMPLATE_EXISTS', $body['code']);

        // Verify record still exists
        $stillExists = $this->employeeModel->find($empUuid);
        $this->assertNotNull($stillExists);

        // Cleanup
        $this->db->table('employee_face_templates')->where('id', $tmplUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testPostWithoutCsrfTokenIsRejected()
    {
        $this->expectException(SecurityException::class);
        $this->post('/admin/employees', [
            'employee_id' => 'FORBIDDEN-01',
            'full_name'   => 'No CSRF',
        ]);
    }
}
