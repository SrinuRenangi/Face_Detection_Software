<?php

namespace Tests\Feature;

use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class KioskAdministrationAndAuthoritativeSettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected KioskDeviceModel $kioskModel;
    protected string $deviceId;
    protected string $rawToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->kioskModel = new KioskDeviceModel();

        $this->rawToken = 'auth-token-' . bin2hex(random_bytes(16));
        $this->deviceId = EmployeeModel::generateUuidV4();

        $this->kioskModel->insert([
            'id'                  => $this->deviceId,
            'device_name'         => 'Authoritative Terminal Alpha',
            'device_identifier'   => 'AUTH-DEV-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'server-authoritative-epoch-1',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'device_token_hash'   => hash('sha256', $this->rawToken),
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->kioskModel->delete($this->deviceId);
        parent::tearDown();
    }

    public function testGuestRedirectedFromKiosksAdminPage()
    {
        $result = $this->get('admin/kiosks');
        $result->assertRedirectTo('/admin/login');
    }

    public function testManagerForbiddenFromKiosksAdminPage()
    {
        $result = $this->withSession([
            'admin_logged_in' => true,
            'admin_user_id'   => 'manager-id-123',
            'admin_user_name' => 'Test Manager',
            'admin_role'      => 'MANAGER',
        ])->get('admin/kiosks');

        $result->assertStatus(403);
        $result->assertSee('403');
    }

    public function testAdminAllowedToAccessKiosksPage()
    {
        $result = $this->withSession([
            'admin_logged_in' => true,
            'admin_user_id'   => 'admin-id-123',
            'admin_user_name' => 'School Admin',
            'admin_role'      => 'ADMIN',
        ])->get('admin/kiosks');

        $result->assertOK();
        $result->assertSee('Attendance Kiosk Terminals');
        $result->assertSee('Register Kiosk Terminal');
    }

    public function testClientCannotOverrideDeviceEpochId()
    {
        $empId = EmployeeModel::generateUuidV4();
        $empCode = 'OVR-EMP-' . bin2hex(random_bytes(3));
        $this->db->table('employees')->insert([
            'id'          => $empId,
            'employee_id' => $empCode,
            'full_name'   => 'Override Test Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            // Client attempts to pass a fabricated device_epoch_id different from server's registered epoch
            $result = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $this->rawToken,
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('api/kiosk/attendance/sync', [
                'client_uuid'     => EmployeeModel::generateUuidV4(),
                'employee_id'     => $empId,
                'device_id'       => $this->deviceId,
                'device_epoch_id' => 'forged-client-epoch-xyz', // Mismatched!
                'timestamp'       => date('Y-m-d H:i:s'),
                'sync_source'     => 'DIRECT_ONLINE',
            ]);

            // Server must reject active epoch override with 409 Conflict
            $result->assertStatus(409);
        } finally {
            $this->db->table('employees')->where('id', $empId)->delete();
        }
    }

    public function testFailedPairingIncrementsCounterAndLocksAfterMaxAttempts()
    {
        $codeId = EmployeeModel::generateUuidV4();
        // 128-bit pairing code (32 hex characters)
        $rawCode = strtoupper(bin2hex(random_bytes(16)));
        $codeHash = hash('sha256', $rawCode);

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Pairing Counter Test',
            'pairing_code_hash' => $codeHash,
            'created_by'        => 'admin',
            'expires_at'        => date('Y-m-d H:i:s', strtotime('+10 minutes')),
            'status'            => 'PENDING',
            'failed_attempts'   => 4, // 1 failure away from max (5)
            'max_attempts'      => 5,
            'created_at'        => date('Y-m-d H:i:s'),
        ]);

        try {
            // Send invalid code
            $result = $this->post('api/kiosk/pair', [
                'pairing_code' => strtoupper(bin2hex(random_bytes(16))),
            ]);
            $result->assertStatus(401);

            // Trigger the 5th failure on the actual code hash
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->update(['failed_attempts' => 5]);

            // Now attempting to pair with the REAL code must be rejected because it is exhausted!
            $result2 = $this->post('api/kiosk/pair', [
                'pairing_code' => $rawCode,
            ]);
            $result2->assertStatus(401);

            $row = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertTrue(in_array($row['status'], ['EXHAUSTED', 'EXPIRED'], true));
        } finally {
            $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->delete();
        }
    }
}

