<?php

namespace Tests\Unit;

use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;

class DeviceAuthFoundationTest extends CIUnitTestCase
{
    protected KioskDeviceModel $deviceModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->deviceModel = new KioskDeviceModel();
    }

    public function testDeviceTokenHashingSha256()
    {
        $rawToken = 'kiosk_token_secret_' . bin2hex(random_bytes(16));
        $hash = hash('sha256', $rawToken);

        $this->assertSame(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $hash);
        $this->assertSame($hash, hash('sha256', $rawToken));
        $this->assertNotSame($hash, hash('sha256', 'different_token_123'));
    }

    public function testActiveDeviceRegistrationAndLookup()
    {
        $deviceId = 'dev-' . bin2hex(random_bytes(6));
        $rawToken = 'tablet_token_active_' . bin2hex(random_bytes(8));
        $tokenHash = hash('sha256', $rawToken);
        $now = date('Y-m-d H:i:s');

        $inserted = $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'School Main Gate Tablet',
            'device_identifier' => 'TABLET-UNIT-01',
            'status'            => 'ACTIVE',
            'device_token_hash' => $tokenHash,
            'paired_at'         => $now,
            'last_seen_at'      => $now,
            'created_by'        => 'admin',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $this->assertTrue($inserted !== false);

        // Lookup device by token hash
        $found = $this->deviceModel->where('device_token_hash', $tokenHash)->first();
        $this->assertNotNull($found);
        $this->assertSame('ACTIVE', $found['status']);
        $this->assertSame('TABLET-UNIT-01', $found['device_identifier']);

        // Clean up
        $this->deviceModel->delete($deviceId);
    }

    public function testRevokedDeviceLookup()
    {
        $deviceId = 'dev-' . bin2hex(random_bytes(6));
        $rawToken = 'tablet_token_revoked_' . bin2hex(random_bytes(8));
        $tokenHash = hash('sha256', $rawToken);
        $now = date('Y-m-d H:i:s');

        $this->deviceModel->insert([
            'id'                => $deviceId,
            'device_name'       => 'Decommissioned Tablet',
            'device_identifier' => 'TABLET-DECOM-02',
            'status'            => 'REVOKED',
            'device_token_hash' => $tokenHash,
            'paired_at'         => $now,
            'revoked_at'        => $now,
            'revocation_reason' => 'Device damaged and replaced',
            'created_by'        => 'admin',
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $found = $this->deviceModel->where('device_token_hash', $tokenHash)->first();
        $this->assertNotNull($found);
        $this->assertSame('REVOKED', $found['status']);
        $this->assertSame('Device damaged and replaced', $found['revocation_reason']);
        $this->assertNotNull($found['revoked_at']);

        // Clean up
        $this->deviceModel->delete($deviceId);
    }
}

