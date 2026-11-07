<?php

namespace Tests\Feature;

use App\Models\AdminAuditLogModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Services;

class EmployeePinAttendanceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $deviceModel;
    protected AdminAuditLogModel $auditModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db            = Database::connect();
        $this->employeeModel = new EmployeeModel();
        $this->deviceModel   = new KioskDeviceModel();
        $this->auditModel    = new AdminAuditLogModel();
        service('cache')->clean();
    }

    protected function tearDown(): void
    {
        service('cache')->clean();
        parent::tearDown();
    }

    private function createTestEmployee(array $overrides = []): array
    {
        $id = EmployeeModel::generateUuidV4();
        $empCode = 'TEST-EMP-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        $data = array_merge([
            'id'          => $id,
            'employee_id' => $empCode,
            'full_name'   => 'Test Staff ' . bin2hex(random_bytes(2)),
            'department'  => 'Academics',
            'designation' => 'Teacher',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ], $overrides);

        $this->employeeModel->insert($data);
        return $data;
    }

    private function createTestKioskDevice(string $terminalMode = 'ENTRY_ONLY'): array
    {
        $deviceId  = EmployeeModel::generateUuidV4();
        $rawToken  = bin2hex(random_bytes(32));
        $epochId   = EmployeeModel::generateUuidV4();
        $now       = date('Y-m-d H:i:s');

        $deviceData = [
            'id'                  => $deviceId,
            'device_name'         => 'Test Kiosk ' . bin2hex(random_bytes(3)),
            'device_identifier'   => 'DEV-TEST-' . bin2hex(random_bytes(3)),
            'device_type'         => 'TABLET',
            'terminal_mode'       => $terminalMode,
            'device_epoch_id'     => $epochId,
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'device_token_hash'   => hash('sha256', $rawToken),
            'token_created_at'    => $now,
            'token_expires_at'    => date('Y-m-d H:i:s', strtotime('+30 days')),
            'paired_at'           => $now,
            'last_seen_at'        => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $this->deviceModel->insert($deviceData);

        return [
            'id'        => $deviceId,
            'raw_token' => $rawToken,
            'epoch_id'  => $epochId,
            'mode'      => $terminalMode,
        ];
    }

    // =========================================================================
    // 1. PIN Management Authorization & Verification
    // =========================================================================

    public function testAdminCanAssignPinToEmployee()
    {
        $emp = $this->createTestEmployee();

        try {
            $sessionData = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            $res = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()       => csrf_hash(),
                    'pin'              => '123456',
                    'pin_confirm'      => '123456',
                ]);

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);
            $this->assertSame('success', $json['status']);

            // Verify pin_hash in database is a valid bcrypt hash
            $updated = $this->db->table('employees')->where('id', $emp['id'])->get()->getRowArray();
            $this->assertNotEmpty($updated['pin_hash']);
            $this->assertTrue(password_verify('123456', $updated['pin_hash']));

            // Verify raw PIN was NEVER stored
            $this->assertNotSame('123456', $updated['pin_hash']);

            // Verify audit log entry
            $audit = $this->db->table('admin_audit_logs')
                ->where('target_id', $emp['id'])
                ->where('action', 'EMPLOYEE_PIN_ASSIGNED')
                ->get()->getRowArray();
            $this->assertNotNull($audit);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testAdminCanResetExistingPin()
    {
        $initialHash = password_hash('111111', PASSWORD_BCRYPT);
        $emp = $this->createTestEmployee(['pin_hash' => $initialHash]);

        try {
            $sessionData = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            $res = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()       => csrf_hash(),
                    'pin'              => '999888',
                    'pin_confirm'      => '999888',
                ]);

            $res->assertStatus(200);

            // Verify hash changed to match new PIN
            $updated = $this->db->table('employees')->where('id', $emp['id'])->get()->getRowArray();
            $this->assertTrue(password_verify('999888', $updated['pin_hash']));
            $this->assertFalse(password_verify('111111', $updated['pin_hash']));

            // Verify audit log entry is EMPLOYEE_PIN_RESET
            $audit = $this->db->table('admin_audit_logs')
                ->where('target_id', $emp['id'])
                ->where('action', 'EMPLOYEE_PIN_RESET')
                ->get()->getRowArray();
            $this->assertNotNull($audit);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testManagerCannotAssignOrResetPin()
    {
        $emp = $this->createTestEmployee();

        try {
            $sessionData = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000002',
                'admin_username'  => 'manager',
                'admin_full_name' => 'Duty Manager',
                'admin_role'      => 'ROLE_MANAGER',
            ];

            $res = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()       => csrf_hash(),
                    'pin'              => '123456',
                    'pin_confirm'      => '123456',
                ]);

            $res->assertStatus(403);

            // Employee pin_hash must remain null
            $check = $this->db->table('employees')->where('id', $emp['id'])->get()->getRowArray();
            $this->assertNull($check['pin_hash']);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testUnauthenticatedCannotAssignPin()
    {
        $emp = $this->createTestEmployee();

        try {
            $res = $this->withHeaders([
                csrf_header() => csrf_hash(),
            ])->post('/admin/employees/' . $emp['id'] . '/pin', [
                csrf_token()  => csrf_hash(),
                'pin'         => '123456',
                'pin_confirm' => '123456',
            ]);

            $res->assertStatus(302);
            $res->assertRedirectTo('/admin/login');
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testPinAssignmentValidationRequiresExact6Digits()
    {
        $emp = $this->createTestEmployee();

        try {
            $sessionData = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            // 5 digits rejected
            $res1 = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()  => csrf_hash(),
                    'pin'         => '12345',
                    'pin_confirm' => '12345',
                ]);
            $res1->assertStatus(422);

            // Letters rejected
            $res2 = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()  => csrf_hash(),
                    'pin'         => '12345a',
                    'pin_confirm' => '12345a',
                ]);
            $res2->assertStatus(422);

            // Mismatch rejected
            $res3 = $this->withSession($sessionData)
                ->withHeaders([
                    'X-Requested-With' => 'XMLHttpRequest',
                    csrf_header()      => csrf_hash(),
                ])
                ->post('/admin/employees/' . $emp['id'] . '/pin', [
                    csrf_token()  => csrf_hash(),
                    'pin'         => '123456',
                    'pin_confirm' => '654321',
                ]);
            $res3->assertStatus(422);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testEmployeeApiNeverExposesPinHash()
    {
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $sessionData = [
                'admin_logged_in' => true,
                'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
                'admin_username'  => 'admin',
                'admin_full_name' => 'System Administrator',
                'admin_role'      => 'ROLE_ADMIN',
            ];

            $res = $this->withSession($sessionData)->get('/api/admin/employees/' . $emp['id']);
            $res->assertStatus(200);

            $json = json_decode($res->getJSON(), true);
            $this->assertSame('success', $json['status']);

            // pin_configured flag must be present
            $this->assertTrue((bool) $json['employee']['pin_configured']);

            // pin_hash MUST NEVER be present
            $this->assertArrayNotHasKey('pin_hash', $json['employee']);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 2. Kiosk PIN Verification Flow (/api/kiosk/verify-pin)
    // =========================================================================

    public function testKioskVerifyPinSuccess()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('654321', PASSWORD_BCRYPT),
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321',
            ]);

            $res->assertStatus(200);
            $json = json_decode($res->getJSON(), true);

            $this->assertSame('success', $json['status']);
            $this->assertNotEmpty($json['verification_token']);
            $this->assertSame($emp['id'], $json['employee']['id']);
            $this->assertSame($emp['full_name'], $json['employee']['full_name']);
            $this->assertSame('ENTRY', $json['action']);

            // No sensitive hashes in output
            $this->assertArrayNotHasKey('pin_hash', $json);
            $this->assertArrayNotHasKey('pin', $json);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testKioskVerifyPinIncorrectPinFailsWithGenericMessage()
    {
        $device = $this->createTestKioskDevice();
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '654321', // Wrong PIN
            ]);

            $res->assertStatus(401);
            $json = json_decode($res->getJSON(), true);
            $this->assertSame('error', $json['status']);
            $this->assertSame('Invalid Employee ID or PIN.', $json['message']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testKioskVerifyPinUnknownEmployeeFailsWithGenericMessage()
    {
        $device = $this->createTestKioskDevice();

        try {
            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => 'NON-EXISTENT-ID-999',
                'pin'           => '123456',
            ]);

            $res->assertStatus(401);
            $json = json_decode($res->getJSON(), true);
            $this->assertSame('error', $json['status']);
            $this->assertSame('Invalid Employee ID or PIN.', $json['message']);
        } finally {
            $this->deviceModel->delete($device['id']);
        }
    }

    public function testKioskVerifyPinInactiveEmployeeFails()
    {
        $device = $this->createTestKioskDevice();
        $emp = $this->createTestEmployee([
            'status'   => 'INACTIVE',
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $res = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);

            $res->assertStatus(401);
            $json = json_decode($res->getJSON(), true);
            $this->assertSame('error', $json['status']);
            $this->assertSame('Invalid Employee ID or PIN.', $json['message']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testKioskVerifyPinRateLimitingBlocksAfterFiveFailures()
    {
        $device = $this->createTestKioskDevice();
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // First 5 attempts return 401
            for ($i = 1; $i <= 5; $i++) {
                $res = $this->withHeaders([
                    'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                    'X-Requested-With' => 'XMLHttpRequest',
                ])->post('/api/kiosk/verify-pin', [
                    'employee_code' => $emp['employee_id'],
                    'pin'           => '000000',
                ]);
                $res->assertStatus(401);
            }

            // 6th attempt must be throttled with 429 Too Many Requests
            $res6 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '000000',
            ]);
            $res6->assertStatus(429);
            $json = json_decode($res6->getJSON(), true);
            $this->assertSame('TOO_MANY_FAILED_ATTEMPTS', $json['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 3. Attendance Recording & Two-Step Confirmation (/api/kiosk/confirm-punch)
    // =========================================================================

    public function testKioskConfirmPunchRecordsCheckInOnEntryOnlyTerminal()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Step 1: Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $verifJson = json_decode($verifRes->getJSON(), true);
            $token = $verifJson['verification_token'];

            // Step 2: Confirm punch
            $clientUuid = EmployeeModel::generateUuidV4();
            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
            ]);

            $confRes->assertStatus(200);
            $confJson = json_decode($confRes->getJSON(), true);
            $this->assertSame('success', $confJson['status']);
            $this->assertSame('CHECK_IN', $confJson['action']);

            // Verify event recorded in ledger
            $event = $this->db->table('attendance_events')
                ->where('client_uuid', $clientUuid)
                ->get()->getRowArray();
            $this->assertNotNull($event);
            $this->assertSame($emp['id'], $event['employee_id']);
            $this->assertSame('CHECK_IN', $event['event_type']);

            // Verify token is single-use: second attempt with same token fails with 401
            $replayRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);
            $replayRes->assertStatus(401);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testKioskConfirmPunchExitOnlyRejectsWithoutPriorCheckIn()
    {
        $device = $this->createTestKioskDevice('EXIT_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Step 1: Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Step 2: Confirm punch on EXIT terminal without check-in
            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);

            // Must reject with 422 Unprocessable Entity
            $confRes->assertStatus(422);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('REJECTED_NO_OPEN_RECORD', $json['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testKioskConfirmPunchIsIdempotentOnDuplicateSubmission()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Verify PIN
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $token1 = json_decode($verifRes->getJSON(), true)['verification_token'];

            // First punch submission
            $clientUuid = EmployeeModel::generateUuidV4();
            $punchTimestamp = date('Y-m-d H:i:s');
            $confRes1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token1,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
                'timestamp'          => $punchTimestamp,
            ]);
            $confRes1->assertStatus(200);

            // Re-verify to get new token, but submit SAME client_uuid and timestamp
            $verifRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $token2 = json_decode($verifRes2->getJSON(), true)['verification_token'];

            $confRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token2,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid, // Same UUID
                'timestamp'          => $punchTimestamp, // Same timestamp
            ]);
            $confRes2->assertStatus(200);
            $json2 = json_decode($confRes2->getJSON(), true);
            $this->assertSame('IDEMPOTENT_CONFIRMED', $json2['sync_status']);

            // Verify only ONE attendance event exists for this UUID
            $events = $this->db->table('attendance_events')->where('client_uuid', $clientUuid)->get()->getResultArray();
            $this->assertCount(1, $events);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testUnpairedDeviceCannotVerifyPinOrPunch()
    {
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Request without device cookie
            $res1 = $this->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $res1->assertStatus(401);

            $res2 = $this->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => 'dummy-token',
                'employee_id'        => $emp['id'],
            ]);
            $res2->assertStatus(401);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }

    // =========================================================================
    // 4. Verification Token Lifecycle & Expiry Enforcement (60s TTL, Single-Use)
    // =========================================================================

    public function testVerificationTokenValidWithin60Seconds()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $verifRes->assertStatus(200);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // Confirm punch immediately within 60s
            $clientUuid = EmployeeModel::generateUuidV4();
            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid,
            ]);
            $confRes->assertStatus(200);
            $this->assertSame('success', json_decode($confRes->getJSON(), true)['status']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testVerificationTokenExpiredAfter60Seconds()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Seed a verification token that was issued 65 seconds ago
            $token = EmployeeModel::generateUuidV4();
            service('cache')->save('kiosk_verif_' . $token, [
                'employee_id' => $emp['id'],
                'device_id'   => $device['id'],
                'verified_at' => time() - 65,
            ], 60);

            $confRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);

            $confRes->assertStatus(401);
            $json = json_decode($confRes->getJSON(), true);
            $this->assertSame('VERIFICATION_EXPIRED', $json['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testVerificationTokenReuseAfterSuccessfulConfirmationIsBlocked()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // First confirmation succeeds
            $clientUuid1 = EmployeeModel::generateUuidV4();
            $confRes1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid1,
            ]);
            $confRes1->assertStatus(200);

            // Reusing token for a second punch attempt must fail with 401 VERIFICATION_EXPIRED
            $clientUuid2 = EmployeeModel::generateUuidV4();
            $confRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => $clientUuid2,
            ]);
            $confRes2->assertStatus(401);
            $json2 = json_decode($confRes2->getJSON(), true);
            $this->assertSame('VERIFICATION_EXPIRED', $json2['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testVerificationTokenReuseAfterFailedConfirmationIsBlocked()
    {
        $device = $this->createTestKioskDevice('EXIT_ONLY'); // EXIT without prior check-in fails
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            $verifRes = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/verify-pin', [
                'employee_code' => $emp['employee_id'],
                'pin'           => '123456',
            ]);
            $token = json_decode($verifRes->getJSON(), true)['verification_token'];

            // First confirmation fails with 422 REJECTED_NO_OPEN_RECORD
            $confRes1 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);
            $confRes1->assertStatus(422);

            // Reusing token even after failed attempt must fail with 401 VERIFICATION_EXPIRED
            $confRes2 = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $token,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);
            $confRes2->assertStatus(401);
            $json2 = json_decode($confRes2->getJSON(), true);
            $this->assertSame('VERIFICATION_EXPIRED', $json2['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->orWhere('device_id', $device['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }

    public function testVerificationTokenExpiryBoundary()
    {
        $device = $this->createTestKioskDevice('ENTRY_ONLY');
        $emp = $this->createTestEmployee([
            'pin_hash' => password_hash('123456', PASSWORD_BCRYPT),
        ]);

        try {
            // Case A: Token at boundary (55 seconds elapsed, <= 60s) -> Valid
            $validToken = EmployeeModel::generateUuidV4();
            service('cache')->save('kiosk_verif_' . $validToken, [
                'employee_id' => $emp['id'],
                'device_id'   => $device['id'],
                'verified_at' => time() - 55,
            ], 60);

            $resValid = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $validToken,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);
            $resValid->assertStatus(200);

            // Case B: Token at boundary (62 seconds elapsed, > 60s) -> Expired
            $expiredToken = EmployeeModel::generateUuidV4();
            service('cache')->save('kiosk_verif_' . $expiredToken, [
                'employee_id' => $emp['id'],
                'device_id'   => $device['id'],
                'verified_at' => time() - 62,
            ], 60);

            $resExpired = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $device['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
            ])->post('/api/kiosk/confirm-punch', [
                'verification_token' => $expiredToken,
                'employee_id'        => $emp['id'],
                'client_uuid'        => EmployeeModel::generateUuidV4(),
            ]);
            $resExpired->assertStatus(401);
            $this->assertSame('VERIFICATION_EXPIRED', json_decode($resExpired->getJSON(), true)['code']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $emp['id'])->delete();
            $this->db->table('attendance_records')->where('employee_id', $emp['id'])->delete();
            $this->deviceModel->delete($device['id']);
            $this->employeeModel->delete($emp['id']);
        }
    }
}
