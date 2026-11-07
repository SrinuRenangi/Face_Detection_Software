<?php

namespace Tests\Feature;

use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class KioskPairingFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected KioskDeviceModel $deviceModel;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }
        $this->db = Database::connect();
        $this->deviceModel = new KioskDeviceModel();
    }

    protected function tearDown(): void
    {
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }
        service('cache')->clean();
        parent::tearDown();
    }

    public function testPairingRejectsMissingCode()
    {
        $result = $this->post('/api/kiosk/pair', []);
        $result->assertStatus(400);
        $result->assertJSONFragment(['messages' => ['error' => 'Pairing code is required.']]);
    }

    public function testPairingRejectsInvalidOrExpiredCode()
    {
        $result = $this->post('/api/kiosk/pair', [
            'pairing_code' => 'BOGUS-PAIRING-CODE-999',
        ]);
        $result->assertStatus(401);
    }

    public function testPairingSucceedsWithValidCodeAndIssuesToken()
    {
        // High-entropy 128-bit pairing code (32 hexadecimal chars)
        $pairingCode = strtoupper(bin2hex(random_bytes(16)));
        $codeHash = hash('sha256', $pairingCode);
        $codeId = 'pair-code-' . bin2hex(random_bytes(4));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Insert pending pairing code
        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'School Lobby Tablet Test',
            'pairing_code_hash' => $codeHash,
            'created_by'        => 'admin',
            'expires_at'        => $expires,
            'status'            => 'PENDING',
            'created_at'        => $now,
        ]);

        $deviceId = null;

        try {
            $result = $this->post('/api/kiosk/pair', [
                'pairing_code'  => $pairingCode,
                'device_name'   => 'School Lobby Tablet Test',
                'device_type'   => 'TABLET',
                'device_model'  => 'Samsung Galaxy Tab A9',
                'hardware_tier' => 'MIDRANGE',
            ]);

            $result->assertStatus(201);
            $result->assertJSONFragment([
                'status'       => 'success',
                'deviceType'   => 'TABLET',
                'terminalMode' => 'ENTRY_ONLY',
            ]);

            $json = json_decode($result->getJSON(), true);
            // Section 4 requirement: Raw deviceToken is NEVER exposed in JSON response body
            $this->assertArrayNotHasKey('deviceToken', $json);
            $deviceId = $json['deviceId'];

            // Verify pairing code is now marked USED in DB
            $codeRow = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertSame('USED', $codeRow['status']);
            $this->assertSame($deviceId, $codeRow['paired_device_id']);

            // Extract secure cookie from response
            $cookie = $result->response()->getCookie('edifies_kiosk_token');
            $this->assertNotNull($cookie);
            $rawToken = $cookie->getValue();
            $this->assertSame(64, strlen($rawToken)); // 32 bytes hex = 64 characters
            $this->assertSame('/api/kiosk', $cookie->getPath());
            $this->assertTrue($cookie->isHTTPOnly());
            $this->assertSame('Strict', $cookie->getSameSite());

            // Header-based authentication MUST FAIL (Section 4)
            $headerResult = $this->withHeaders([
                'X-Kiosk-Device-Token' => $rawToken,
            ])->get('/api/kiosk/status');
            $headerResult->assertStatus(401);

            // Cookie-based authentication MUST SUCCEED
            $statusResult = $this->withHeaders([
                'Cookie' => 'edifies_kiosk_token=' . $rawToken,
            ])->get('/api/kiosk/status');

            $statusResult->assertStatus(200);
            $statusResult->assertJSONFragment([
                'status'     => 'success',
                'authorized' => true,
                'deviceId'   => $deviceId,
            ]);
        } finally {
            if ($deviceId) {
                $this->deviceModel->delete($deviceId);
            }
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }
}
