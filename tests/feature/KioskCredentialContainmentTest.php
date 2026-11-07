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

/**
 * Stage 3.3.2.2 Feature Test Suite:
 * - Test Credential Exposure Containment (DEV-8DFEBD29)
 * - Strict RFC 6454 Exact-Origin Validation (Scheme, Host, Port)
 * - Cross-Site Request and CSRF Protections
 */
class KioskCredentialContainmentTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected KioskDeviceModel $deviceModel;
    protected string $activeDeviceId;
    protected string $activeRawToken;
    protected string $revokedDeviceId;
    protected string $revokedRawToken;
    protected string $originalBaseURL;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->deviceModel = new KioskDeviceModel();
        service('cache')->clean();
        Services::injectMock('throttler', new Throttler(service('cache')));

        // Ensure canonical baseURL is http://localhost:8088/ for test consistency
        $appConfig = config('App');
        $this->originalBaseURL = $appConfig->baseURL;
        $appConfig->baseURL = 'http://localhost:8088/';

        // Seed an ACTIVE test device fixture
        $this->activeDeviceId = EmployeeModel::generateUuidV4();
        $this->activeRawToken = bin2hex(random_bytes(32));
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $this->activeDeviceId,
            'device_name'       => 'Origin Test Active Terminal',
            'device_identifier' => 'DEV-ORIGIN-' . bin2hex(random_bytes(3)),
            'status'            => 'ACTIVE',
            'device_token_hash' => hash('sha256', $this->activeRawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        // Seed a REVOKED test device fixture
        $this->revokedDeviceId = EmployeeModel::generateUuidV4();
        $this->revokedRawToken = bin2hex(random_bytes(32));

        $this->deviceModel->insert([
            'id'                => $this->revokedDeviceId,
            'device_name'       => 'Origin Test Revoked Terminal',
            'device_identifier' => 'DEV-REV-' . bin2hex(random_bytes(3)),
            'status'            => 'REVOKED',
            'device_token_hash' => hash('sha256', $this->revokedRawToken),
            'token_expires_at'  => date('Y-m-d H:i:s', strtotime('+30 days')),
            'revoked_at'        => $now,
            'revocation_reason' => 'Test revocation fixture',
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
    }

    protected function tearDown(): void
    {
        // Restore original baseURL
        $appConfig = config('App');
        $appConfig->baseURL = $this->originalBaseURL;

        // Cleanup test fixtures
        if (!empty($this->activeDeviceId)) {
            $this->deviceModel->delete($this->activeDeviceId);
        }
        if (!empty($this->revokedDeviceId)) {
            $this->deviceModel->delete($this->revokedDeviceId);
        }

        service('cache')->clean();
        Services::reset(true);
        parent::tearDown();
    }

    /**
     * Requirement 1: Exact configured canonical origin is ACCEPTED (200 OK)
     */
    public function testExactConfiguredOriginIsAccepted()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'http://localhost:8088',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(200);
        $result->assertJSONFragment([
            'status' => 'success',
            'pong'   => true,
        ]);
    }

    /**
     * Requirement 2: Same scheme and port but different hostname is REJECTED (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testSameSchemePortDifferentHostnameIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'http://otherhost:8088',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_ORIGIN_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 3: Same hostname and scheme but different port is REJECTED (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testSameHostDifferentPortIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'http://localhost:9999',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_ORIGIN_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 4: Same host and port but different scheme is REJECTED (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testSameHostPortDifferentSchemeIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'https://localhost:8088',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_ORIGIN_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 5: Unapproved loopback alias (127.0.0.1 vs localhost) is REJECTED (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testUnapprovedLoopbackAliasIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'http://127.0.0.1:8088',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_ORIGIN_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 6: Attacker-controlled external Origin is REJECTED (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testAttackerControlledOriginIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'https://evil-attacker.com',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_ORIGIN_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 7: Sec-Fetch-Site cross-site is REJECTED (403 CROSS_SITE_REQUEST_FORBIDDEN)
     */
    public function testSecFetchSiteCrossSiteIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'http://localhost:8088',
            'Sec-Fetch-Site'   => 'cross-site',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CROSS_SITE_REQUEST_FORBIDDEN',
        ]);
    }

    /**
     * Requirement 8: Missing custom security headers on state-changing requests is REJECTED (403 CSRF_VALIDATION_FAILED)
     */
    public function testMissingSecurityHeadersOnStateChangingRequestsIsRejected()
    {
        $result = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin' => 'http://localhost:8088',
            // Omit X-Requested-With, X-CSRF-TOKEN, X-Kiosk-CSRF
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'CSRF_VALIDATION_FAILED',
        ]);
    }

    /**
     * Requirement 9a: Opaque or null Origin is REJECTED fail-closed (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testNullOriginIsRejectedFailClosed()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'null',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status'  => 'error',
            'code'    => 'CROSS_ORIGIN_FORBIDDEN',
            'message' => 'Opaque or null Origin is strictly prohibited for state-changing requests.',
        ]);
    }

    /**
     * Requirement 9b: Malformed Origin header is REJECTED fail-closed (403 CROSS_ORIGIN_FORBIDDEN)
     */
    public function testMalformedOriginIsRejectedFailClosed()
    {
        $result = $this->withHeaders([
            'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
            'Origin'           => 'not_a_valid_url_without_scheme_or_host',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->post('/api/kiosk/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status'  => 'error',
            'code'    => 'CROSS_ORIGIN_FORBIDDEN',
            'message' => 'Malformed Origin header is prohibited.',
        ]);
    }

    /**
     * Requirement 10: Revoked device credential receives HTTP 403 DEVICE_REVOKED
     */
    public function testRevokedDeviceCredentialReceives403DeviceRevoked()
    {
        $result = $this->withHeaders([
            'Cookie' => 'edifies_kiosk_token=' . $this->revokedRawToken,
        ])->get('/api/kiosk/status');

        $result->assertStatus(403);
        $result->assertJSONFragment([
            'status' => 'error',
            'code'   => 'DEVICE_REVOKED',
        ]);
    }

    /**
     * Requirement 11: Explicit allowlist permits approved alternative origin
     */
    public function testExplicitAllowlistPermitsApprovedAlternativeOrigin()
    {
        putenv('kiosk.allowedOrigins=http://approved-kiosk.internal:8088,http://127.0.0.1:8088');

        try {
            $result = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $this->activeRawToken,
                'Origin'           => 'http://approved-kiosk.internal:8088',
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/ping');

            $result->assertStatus(200);
            $result->assertJSONFragment([
                'status' => 'success',
                'pong'   => true,
            ]);
        } finally {
            putenv('kiosk.allowedOrigins=');
        }
    }
}
