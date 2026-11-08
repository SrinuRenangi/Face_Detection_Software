<?php

namespace Tests\Feature;

use App\Models\KioskDeviceModel;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

class RouteAndSecurityFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testHomePageRendersSuccessfully()
    {
        $result = $this->get('/');
        $result->assertStatus(200);
        $result->assertSee('Staff Attendance Management System');
        $result->assertSee('Pair Scanner with 1-Time Code');
        $result->assertSee('Administrator Sign In');
    }

    public function testAdminLoginRouteRendersForm()
    {
        $result = $this->get('/admin/login');
        $result->assertStatus(200);
        $result->assertSee('Administrator Sign In');
        $result->assertSee('Username');
        $result->assertSee('Password');
        $result->assertSee('Sign In');
    }

    public function testAttendanceKioskRouteRendersInterface()
    {
        $result = $this->get('/attendance');
        $result->assertStatus(200);
        $result->assertSee('Attendance Kiosk');
        $result->assertSee('Touchless Presence Verification System');
        $result->assertSee('Terminal:');
    }

    public function testAdminDashboardRequiresAuthenticationRedirect()
    {
        $result = $this->get('/admin');
        $result->assertStatus(302);
        $result->assertRedirectTo('/admin/login');
    }

    public function testAdminApiRequiresAuthentication()
    {
        $result = $this->get('/api/admin/me');
        $result->assertStatus(401);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'UNAUTHORIZED',
        ]);
    }

    public function testAdminLoginWithoutCsrfTokenIsRejected()
    {
        // State-changing POST to /admin/login without CSRF token must throw SecurityException
        $this->expectException(SecurityException::class);
        $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'WrongPass123!',
        ]);
    }

    public function testAdminLoginWithInvalidCsrfTokenIsRejected()
    {
        // State-changing POST to /admin/login with forged CSRF token must throw SecurityException
        $this->expectException(SecurityException::class);
        $this->withHeaders([
            'X-CSRF-TOKEN' => 'forged_invalid_csrf_token_value_99999',
        ])->post('/admin/login', [
            'csrf_token_name' => 'forged_invalid_csrf_token_value_99999',
            'username'        => 'admin',
            'password'        => 'WrongPass123!',
        ]);
    }

    public function testAdminLogoutViaGetIsRejectedWith405()
    {
        // GET /admin/logout must be rejected with 405 Method Not Allowed
        $result = $this->get('/admin/logout');
        $result->assertStatus(405);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'METHOD_NOT_ALLOWED',
        ]);
    }

    public function testAdminLogoutViaPostRequiresCsrfToken()
    {
        // POST /admin/logout without CSRF token must throw SecurityException
        $this->expectException(SecurityException::class);
        $this->post('/admin/logout');
    }

    public function testKioskApiRequiresDeviceToken()
    {
        $result = $this->get('/api/kiosk/status');
        $result->assertStatus(401);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'DEVICE_TOKEN_MISSING',
        ]);
    }

    public function testKioskApiRejectsInvalidDeviceToken()
    {
        // Must send cookie, not header
        $result = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=bogus-device-token-value-999',
        ])->get('/api/kiosk/status');

        $result->assertStatus(401);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'DEVICE_UNAUTHORIZED',
        ]);
    }

    public function testKioskApiRejectsRevokedDeviceToken()
    {
        $deviceModel = new KioskDeviceModel();
        $deviceId = 'dev-rev-' . bin2hex(random_bytes(6));
        $devIdent = 'TABLET-REV-' . bin2hex(random_bytes(4));
        $rawToken = 'revoked-test-token-' . bin2hex(random_bytes(8));
        $tokenHash = hash('sha256', $rawToken);
        $now = date('Y-m-d H:i:s');

        try {
            $deviceModel->insert([
                'id'                => $deviceId,
                'device_name'       => 'Decommissioned Tablet',
                'device_identifier' => $devIdent,
                'status'            => 'REVOKED',
                'device_token_hash' => $tokenHash,
                'paired_at'         => $now,
                'revoked_at'        => $now,
                'revocation_reason' => 'Damaged in transit',
                'created_by'        => 'admin',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            $result = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/api/kiosk/status');

            $result->assertStatus(403);
            $result->assertJSONFragment([
                'status' => 'error',
                'code'   => 'DEVICE_REVOKED',
            ]);
        } finally {
            $deviceModel->delete($deviceId);
        }
    }

    public function testKioskApiAcceptsValidDeviceToken()
    {
        $deviceModel = new KioskDeviceModel();
        $deviceId = 'dev-feat-' . bin2hex(random_bytes(6));
        $devIdent = 'TABLET-FEAT-' . bin2hex(random_bytes(4));
        $rawToken = 'valid-test-kiosk-token-' . bin2hex(random_bytes(8));
        $tokenHash = hash('sha256', $rawToken);
        $now = date('Y-m-d H:i:s');

        try {
            $deviceModel->insert([
                'id'                => $deviceId,
                'device_name'       => 'Feature Test Tablet',
                'device_identifier' => $devIdent,
                'status'            => 'ACTIVE',
                'device_token_hash' => $tokenHash,
                'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
                'paired_at'         => $now,
                'last_seen_at'      => $now,
                'created_by'        => 'admin',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            $result = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/api/kiosk/status');

            $result->assertStatus(200);
            $result->assertJSONFragment([
                'status'           => 'success',
                'authorized'       => true,
                'deviceIdentifier' => $devIdent,
                'deviceName'       => 'Feature Test Tablet',
            ]);
        } finally {
            $deviceModel->delete($deviceId);
        }
    }

    public function testKioskSyncAttendanceThroughApi()
    {
        $db = \Config\Database::connect();
        $deviceModel = new KioskDeviceModel();
        $deviceId = 'dev-sync-' . bin2hex(random_bytes(6));
        $devIdent = 'SMARTPHONE-SYNC-' . bin2hex(random_bytes(4));
        $rawToken = 'kiosk-sync-token-' . bin2hex(random_bytes(8));
        $tokenHash = hash('sha256', $rawToken);
        $empUuid = \App\Models\EmployeeModel::generateUuidV4();
        $empId = 'API-EMP-' . bin2hex(random_bytes(4));
        $clientUuid = 'c0000000-0000-4000-8000-' . bin2hex(random_bytes(6));
        $now = date('Y-m-d H:i:s');

        try {
            // 1. Insert device
            $deviceModel->insert([
                'id'                => $deviceId,
                'device_name'       => 'Security Desk Phone',
                'device_identifier' => $devIdent,
                'device_type'       => 'SMARTPHONE',
                'device_model'      => 'Redmi Note 12',
                'hardware_tier'     => 'MIDRANGE',
                'status'            => 'ACTIVE',
                'device_token_hash' => $tokenHash,
                'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
                'paired_at'         => $now,
                'last_seen_at'      => $now,
                'created_by'        => 'admin',
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);

            // 2. Insert employee
            $db->table('employees')->insert([
                'id'            => $empUuid,
                'employee_id'   => $empId,
                'full_name'     => 'API Sync Worker',
                'department'    => 'Security',
                'designation'   => 'Officer',
                'status'        => 'ACTIVE',
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);

            // 3. Post first punch
            $postData = [
                'client_uuid'      => $clientUuid,
                'employee_id'      => $empUuid,
                'device_id'        => $deviceId,
                'timestamp'        => date('Y-m-d H:i:s', time() - 3600),
                'confidence_score' => 0.92,
                'sync_source'      => 'DIRECT_ONLINE',
            ];

            $result = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $rawToken,
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->withBody(json_encode($postData))->post('/api/kiosk/attendance/sync');

            $result->assertStatus(200);
            $result->assertJSONFragment([
                'status'      => 'success',
                'sync_status' => 'CREATED',
            ]);

            // 4. Test Idempotency with exact same client_uuid
            $dupResult = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $rawToken,
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->withBody(json_encode($postData))->post('/api/kiosk/attendance/sync');

            $dupResult->assertStatus(200);
            $dupResult->assertJSONFragment([
                'status'      => 'success',
                'sync_status' => 'IDEMPOTENT_CONFIRMED',
            ]);
        } finally {
            $db->table('attendance_records')->where('client_uuid', $clientUuid)->delete();
            $db->table('attendance_events')->where('client_uuid', $clientUuid)->delete();
            $db->table('employees')->where('id', $empUuid)->delete();
            $deviceModel->delete($deviceId);
        }
    }
}
