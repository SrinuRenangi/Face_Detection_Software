<?php

namespace Tests\Feature;

use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class CanonicalTokenRotationAndAppHttpsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected KioskDeviceModel $deviceModel;
    protected string $deviceId;
    protected string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }

        $this->db = Database::connect();
        $this->deviceModel = new KioskDeviceModel();

        // Create active device
        $this->deviceId = EmployeeModel::generateUuidV4();
        $this->rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->deviceModel->insert([
            'id'                  => $this->deviceId,
            'device_name'         => 'Token Rotation Test Kiosk',
            'device_identifier'   => 'ROT-DEV-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_token_hash'   => hash('sha256', $this->rawToken),
            'token_rotated_at'    => $now,
            'token_expires_at'    => $expires,
            'device_epoch_id'     => 'epoch-rot-test',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    protected function tearDown(): void
    {
        $this->deviceModel->delete($this->deviceId);
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }
        service('cache')->clean();
        putenv('CI_ENVIRONMENT=testing');
        $_ENV['CI_ENVIRONMENT'] = 'testing';
        parent::tearDown();
    }

    // 1. Canonical route /api/kiosk/token/rotate rotates token and invalidates old token
    public function testActiveDeviceRotatesTokenSuccessfullyViaCanonicalRoute()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('success', $json['status']);
        $this->assertArrayNotHasKey('deviceToken', $json, 'Raw token must never be leaked in JSON response body');

        // Extract new cookie
        $cookie = $result->response()->getCookie('edifies_kiosk_token');
        $this->assertNotNull($cookie);
        $newToken = $cookie->getValue();
        $this->assertSame(64, strlen($newToken));
        $this->assertNotSame($this->rawToken, $newToken);
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertSame('Strict', $cookie->getSameSite());
        $this->assertSame('/api/kiosk', $cookie->getPath());

        // Old token MUST now be rejected with 401
        \Config\Services::reset(true);
        $oldResult = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=' . $this->rawToken,
        ])->get('/api/kiosk/status');
        $oldResult->assertStatus(401);

        // New token MUST now succeed with 200
        \Config\Services::reset(true);
        $newResult = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=' . $newToken,
        ])->get('/api/kiosk/status');
        $newResult->assertStatus(200);
        $newJson = json_decode($newResult->getJSON(), true);
        $this->assertTrue($newJson['authorized']);
        $this->assertSame($this->deviceId, $newJson['deviceId']);
    }

    // 2. Alias route /api/kiosk/rotate-token functions identically
    public function testActiveDeviceRotatesTokenSuccessfullyViaAliasRoute()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/rotate-token', []);

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('success', $json['status']);
        $this->assertArrayNotHasKey('deviceToken', $json);

        $cookie = $result->response()->getCookie('edifies_kiosk_token');
        $this->assertNotNull($cookie);
        $this->assertSame(64, strlen($cookie->getValue()));
    }

    // 3. Revoked device cannot rotate its token (403 DEVICE_REVOKED)
    public function testRevokedDeviceCannotRotateToken()
    {
        $this->deviceModel->update($this->deviceId, ['status' => 'REVOKED']);

        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(403);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('DEVICE_REVOKED', $json['code']);
    }

    // 4. Expired token cannot rotate itself (401 DEVICE_TOKEN_EXPIRED)
    public function testExpiredTokenCannotRotateItself()
    {
        $pastDate = date('Y-m-d H:i:s', strtotime('-1 day'));
        $this->deviceModel->update($this->deviceId, ['token_expires_at' => $pastDate]);

        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(401);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('DEVICE_TOKEN_EXPIRED', $json['code']);
    }

    // 5. Missing CSRF / custom header context is rejected with 403
    public function testMissingCsrfContextIsRejected()
    {
        // Notice: NO X-Requested-With or CSRF header provided
        $result = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=' . $this->rawToken,
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(403);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('CSRF_VALIDATION_FAILED', $json['code']);
    }

    // 6. Cross-site fetch request is rejected with 403
    public function testCrossSiteFetchRequestIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
            'Sec-Fetch-Site'   => 'cross-site',
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(403);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('CROSS_SITE_REQUEST_FORBIDDEN', $json['code']);
    }

    // 7. Rotation over plain HTTP in production is rejected with 403
    public function testRotationOverProductionHttpIsRejected()
    {
        putenv('CI_ENVIRONMENT=production');
        $_ENV['CI_ENVIRONMENT'] = 'production';

        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/token/rotate', []);

        $result->assertStatus(403);
        $json = json_decode($result->getJSON(), true);
        $this->assertSame('INSECURE_TRANSPORT', $json['code']);
    }

    // 8. Whole-application production HTTPS protection
    public function testWholeApplicationProductionHttpsEnforcement()
    {
        putenv('CI_ENVIRONMENT=production');
        $_ENV['CI_ENVIRONMENT'] = 'production';

        // 8a. Admin Login POST over HTTP: MUST return 403 Forbidden (credentials protected)
        \Config\Services::reset(true);
        $loginPost = $this->post('/admin/login', [
            'username' => 'admin',
            'password' => 'secret_password_123',
        ]);
        $loginPost->assertStatus(403);
        $json = json_decode($loginPost->getJSON(), true);
        $this->assertSame('INSECURE_TRANSPORT', $json['code']);

        // 8b. Admin Login GET over HTTP: MUST return 301 Redirect to HTTPS
        \Config\Services::reset(true);
        $loginGet = $this->get('/admin/login');
        $this->assertSame(301, $loginGet->response()->getStatusCode());
        $this->assertStringStartsWith('https://', $loginGet->response()->getHeaderLine('Location'));
        $this->assertNotEmpty($loginGet->response()->getHeaderLine('Strict-Transport-Security'));

        // 8c. Admin Dashboard GET over HTTP: MUST return 301 Redirect to HTTPS
        \Config\Services::reset(true);
        $dashGet = $this->get('/admin/dashboard');
        $this->assertSame(301, $dashGet->response()->getStatusCode());
        $this->assertStringStartsWith('https://', $dashGet->response()->getHeaderLine('Location'));

        // 8d. Attendance Review Queue GET over HTTP: MUST return 301 Redirect to HTTPS
        \Config\Services::reset(true);
        $reviewGet = $this->get('/admin/attendance/review');
        $this->assertSame(301, $reviewGet->response()->getStatusCode());
        $this->assertStringStartsWith('https://', $reviewGet->response()->getHeaderLine('Location'));

        // 8e. Kiosk Pairing POST over HTTP: MUST return 403
        \Config\Services::reset(true);
        $pairPost = $this->post('/api/kiosk/pair', ['pairing_code' => 'ABC']);
        $pairPost->assertStatus(403);
        $pairJson = json_decode($pairPost->getJSON(), true);
        $this->assertSame('INSECURE_TRANSPORT', $pairJson['code']);

        // 8f. Attendance Sync POST over HTTP: MUST return 403
        \Config\Services::reset(true);
        $syncPost = $this->post('/api/kiosk/attendance/sync', []);
        $syncPost->assertStatus(403);

        // 8g. Time Anchor GET over HTTP: MUST return 403
        \Config\Services::reset(true);
        $anchorGet = $this->get('/api/kiosk/time-anchor');
        $anchorGet->assertStatus(403);
    }
}
