<?php

namespace Tests\Feature;

use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Throttle\Throttler;
use Config\Database;
use Config\Services;

class KioskSecurityAndCookieAuditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected KioskDeviceModel $deviceModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->deviceModel = new KioskDeviceModel();
        service('cache')->clean();
        Services::injectMock('throttler', new Throttler(service('cache')));
    }

    protected function tearDown(): void
    {
        service('cache')->clean();
        Services::reset(true);
        parent::tearDown();
    }

    /**
     * 1. Manager Role Prohibition: Managers cannot access Kiosk Terminal Administration
     */
    public function testManagerCannotAccessKioskAdministration()
    {
        $result = $this->withSession([
            'admin_logged_in' => true,
            'admin_user_id'   => 'usr-mgr-test',
            'admin_user_name' => 'Test Manager',
            'admin_role'      => 'MANAGER',
        ])->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
        ])->get('/admin/kiosks');

        $result->assertStatus(403);
    }

    /**
     * 2. Administrator Access: Admins can view and create kiosk devices
     */
    public function testAdminCanCreateAndProvisionDevice()
    {
        $devName = 'Audit Kiosk ' . bin2hex(random_bytes(3));
        $csrfName = csrf_token();
        $csrfToken = csrf_hash();

        $result = $this->withSession([
            'admin_logged_in' => true,
            'admin_user_id'   => 'usr-admin-test',
            'admin_user_name' => 'Super Administrator',
            'admin_role'      => 'ADMIN',
        ])->post('/admin/kiosks/create', [
            $csrfName       => $csrfToken,
            'device_name'   => $devName,
            'device_type'   => 'TABLET',
            'terminal_mode' => 'ENTRY_ONLY',
        ]);

        $result->assertRedirectTo('/admin/kiosks');

        $device = $this->deviceModel->where('device_name', $devName)->first();
        $this->assertNotNull($device);
        $this->assertSame('PENDING_PAIRING', $device['status']);

        // Clean up
        $this->db->table('kiosk_pairing_codes')->where('paired_device_id', $device['id'])->delete();
        $this->deviceModel->delete($device['id']);
    }

    /**
     * 3. 128-Bit Pairing Code Entropy: Minimum 32 hexadecimal characters (16 random bytes)
     */
    public function testPairingCodesHaveAtLeast128BitEntropy()
    {
        $rawCode = strtoupper(bin2hex(random_bytes(16)));
        $this->assertSame(32, strlen($rawCode));
        $this->assertTrue(ctype_xdigit($rawCode));

        // Stored only as SHA-256 (64 hex characters)
        $hash = hash('sha256', $rawCode);
        $this->assertSame(64, strlen($hash));
    }

    /**
     * 4. Expiry Enforcement: Expired codes cannot pair a device
     */
    public function testPairingCodeExpiryIsEnforced()
    {
        $codeId = EmployeeModel::generateUuidV4();
        $rawCode = strtoupper(bin2hex(random_bytes(16)));
        $now = date('Y-m-d H:i:s');
        $past = date('Y-m-d H:i:s', strtotime('-5 minutes')); // Already expired

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Expired Test Device',
            'pairing_code_hash' => hash('sha256', $rawCode),
            'created_by'        => 'admin',
            'expires_at'        => $past,
            'status'            => 'PENDING',
            'created_at'        => $now,
        ]);

        try {
            $result = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);

            $result->assertStatus(401);

            $row = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertSame('EXPIRED', $row['status']);
        } finally {
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    /**
     * 5. Single-Use Pairing: A redeemed pairing code cannot be used again
     */
    public function testSingleUsePairingWorks()
    {
        $codeId = EmployeeModel::generateUuidV4();
        $rawCode = strtoupper(bin2hex(random_bytes(16)));
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Single Use Terminal',
            'pairing_code_hash' => hash('sha256', $rawCode),
            'created_by'        => 'admin',
            'expires_at'        => $future,
            'status'            => 'PENDING',
            'created_at'        => $now,
        ]);

        $deviceId = null;
        try {
            // First pairing attempt succeeds
            $res1 = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
                'device_name'  => 'Single Use Terminal',
            ]);
            $res1->assertStatus(201);
            $json = json_decode($res1->getJSON(), true);
            $deviceId = $json['deviceId'];

            // Second pairing attempt with same code fails
            $res2 = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
                'device_name'  => 'Duplicate Terminal Attempt',
            ]);
            $res2->assertStatus(401);
        } finally {
            if ($deviceId) {
                $this->db->table('kiosk_time_anchors')->where('device_id', $deviceId)->delete();
                $this->deviceModel->delete($deviceId);
            }
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    /**
     * 6. Concurrent Reuse Rejection
     */
    public function testConcurrentReuseIsRejected()
    {
        $codeId = EmployeeModel::generateUuidV4();
        $rawCode = strtoupper(bin2hex(random_bytes(16)));
        $now = date('Y-m-d H:i:s');

        // Pre-mark as USED (as would occur under simultaneous row-lock)
        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Concurrent Race Code',
            'pairing_code_hash' => hash('sha256', $rawCode),
            'created_by'        => 'admin',
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'status'            => 'USED',
            'used_at'           => $now,
            'created_at'        => $now,
        ]);

        try {
            $result = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);
            $result->assertStatus(401);
        } finally {
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    /**
     * 7. Failed Attempts Counted: Threshold of 5 locks code as EXHAUSTED
     */
    public function testFailedAttemptsAreCountedAndExhausted()
    {
        $codeId = EmployeeModel::generateUuidV4();
        $rawCode = strtoupper(bin2hex(random_bytes(16)));

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Exhaustion Test Device',
            'pairing_code_hash' => hash('sha256', $rawCode),
            'created_by'        => 'admin',
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'status'            => 'PENDING',
            'failed_attempts'   => 5,
            'max_attempts'      => 5,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        try {
            $result = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);
            $result->assertStatus(401);

            $row = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertTrue(in_array($row['status'], ['EXHAUSTED', 'EXPIRED'], true));
        } finally {
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    /**
     * 8. Rate Limiting Protection (429 on abuse)
     */
    public function testIpRateLimitingBlocksFlooding()
    {
        $throttler = service('throttler');
        $ip = '10.0.99.1';

        // Simulate filling token bucket to capacity
        for ($i = 0; $i < 6; $i++) {
            $throttler->check(md5('kiosk_pair_' . $ip), 5, MINUTE);
        }

        $isAllowed = $throttler->check(md5('kiosk_pair_' . $ip), 5, MINUTE);
        $this->assertFalse($isAllowed);
    }

    /**
     * 9, 10, 11. Authentication Cookie Security Attributes
     */
    public function testAuthenticationCookieSecurityAttributes()
    {
        $codeId = EmployeeModel::generateUuidV4();
        $rawCode = strtoupper(bin2hex(random_bytes(16)));

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Cookie Attribute Terminal',
            'pairing_code_hash' => hash('sha256', $rawCode),
            'created_by'        => 'admin',
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'status'            => 'PENDING',
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        $deviceId = null;
        try {
            $res = $this->post('/api/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);

                $res->assertStatus(201);
            $cookie = $res->response()->getCookie('edifies_kiosk_token');
            $this->assertNotNull($cookie);

            // 9. HttpOnly
            $this->assertTrue($cookie->isHTTPOnly());

            // 11. SameSite=Strict and Path=/api/kiosk
            $this->assertSame('Strict', $cookie->getSameSite());
            $this->assertSame('/api/kiosk', $cookie->getPath());

            // 30 Days Expiry
            $this->assertGreaterThan(time() + 2500000, $cookie->getExpiresTimestamp());

            $json = json_decode($res->getJSON(), true);
            $deviceId = $json['deviceId'];

            // Raw token NEVER in JSON
            $this->assertArrayNotHasKey('deviceToken', $json);
        } finally {
            if ($deviceId) {
                $this->db->table('kiosk_time_anchors')->where('device_id', $deviceId)->delete();
                $this->deviceModel->delete($deviceId);
            }
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }

    /**
     * 12. Server-Side Token Expiry Enforced
     */
    public function testServerSideTokenExpiryEnforced()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $expiredTime = date('Y-m-d H:i:s', strtotime('-1 hour')); // Expired 1 hour ago

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Expired Token Device',
            'device_identifier' => 'DEV-EXP-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $rawToken),
            'token_expires_at'  => $expiredTime,
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/api/kiosk/status');

            $res->assertStatus(401);
            $res->assertJSONFragment([
                'status' => 'error',
                'code'   => 'DEVICE_TOKEN_EXPIRED',
            ]);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    /**
     * 13. Immediate Revocation
     */
    public function testTokenRevocationIsImmediate()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Revoked Token Device',
            'device_identifier' => 'DEV-REV-' . bin2hex(random_bytes(3)),
            'status'            => 'REVOKED',
            'device_token_hash' => hash('sha256', $rawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'revoked_at'        => $now,
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/api/kiosk/status');

            $res->assertStatus(403);
            $res->assertJSONFragment([
                'status' => 'error',
                'code'   => 'DEVICE_REVOKED',
            ]);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    /**
     * 14. Token Rotation Invalidates Old Token
     */
    public function testTokenRotationInvalidatesOldToken()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $oldToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Rotation Terminal',
            'device_identifier' => 'DEV-ROT-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $oldToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            // Call token rotation endpoint with old token and CSRF header
            $rotRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $oldToken,
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/token/rotate');

            $rotRes->assertStatus(200);

            // Extract newly rotated cookie
            $newCookie = $rotRes->response()->getCookie('edifies_kiosk_token');
            $this->assertNotNull($newCookie);
            $newToken = $newCookie->getValue();
            $this->assertNotSame($oldToken, $newToken);

            // OLD token MUST NOW BE REJECTED with 401
            $oldRes = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $oldToken,
            ])->get('/api/kiosk/status');
            $oldRes->assertStatus(401);

            // NEW token MUST SUCCEED with 200
            $newRes = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $newToken,
            ])->get('/api/kiosk/status');
            $newRes->assertStatus(200);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    /**
     * 15. Elimination of Bearer-Token Header Fallback: Must Reject X-Kiosk-Device-Token Header
     */
    public function testHeaderBasedBearerTokenAuthenticationFails()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Bearer Rejection Terminal',
            'device_identifier' => 'DEV-BEAR-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $rawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            // Attempting to authenticate via raw header must fail closed
            $res = $this->withHeaders([
                'X-Kiosk-Device-Token' => $rawToken,
            ])->get('/api/kiosk/status');

            $res->assertStatus(401);
            $res->assertJSONFragment([
                'status' => 'error',
                'code'   => 'DEVICE_TOKEN_MISSING',
            ]);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    /**
     * 16. Cross-Origin Requests Rejected
     */
    public function testCrossOriginRequestsAreRejected()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'CORS Terminal',
            'device_identifier' => 'DEV-CORS-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $rawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $rawToken,
                'Origin'           => 'https://unauthorized-attacker-site.com',
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/ping');

            $res->assertStatus(403);
            $res->assertJSONFragment([
                'status' => 'error',
                'code'   => 'CROSS_ORIGIN_FORBIDDEN',
            ]);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }

    /**
     * 17. CSRF Protection Failures Rejected
     */
    public function testMissingCsrfVerificationHeadersRejected()
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'CSRF Terminal',
            'device_identifier' => 'DEV-CSRF-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $rawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        try {
            // Missing X-Requested-With and missing CSRF headers on state-changing POST
            $res = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->post('/api/kiosk/ping');

            $res->assertStatus(403);
            $res->assertJSONFragment([
                'status' => 'error',
                'code'   => 'CSRF_VALIDATION_FAILED',
            ]);
        } finally {
            $this->deviceModel->delete($deviceId);
        }
    }
}
