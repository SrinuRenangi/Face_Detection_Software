<?php

namespace Tests\Feature;

use App\Models\AdminAuditLogModel;
use App\Models\AttendanceEventModel;
use App\Models\EmployeeFaceTemplateModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class BiometricVerificationFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $deviceModel;
    protected EmployeeFaceTemplateModel $templateModel;
    protected AttendanceEventModel $eventModel;
    protected AdminAuditLogModel $auditModel;

    protected array $adminSession = [
        'admin_logged_in' => true,
        'admin_user_id'   => 'a0000000-0000-0000-0000-000000000001',
        'admin_username'  => 'biometric_admin',
        'admin_full_name' => 'Biometric Admin',
        'admin_role'      => 'ROLE_ADMIN',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->db            = Database::connect();
        $this->employeeModel = new EmployeeModel();
        $this->deviceModel   = new KioskDeviceModel();
        $this->templateModel = new EmployeeFaceTemplateModel();
        $this->eventModel    = new AttendanceEventModel();
        $this->auditModel    = new AdminAuditLogModel();
        service('cache')->clean();
    }

    protected function tearDown(): void
    {
        service('cache')->clean();
        parent::tearDown();
    }

    private function createAuthorizedKioskDevice(string $terminalMode = 'ENTRY_ONLY'): array
    {
        $deviceId = EmployeeModel::generateUuidV4();
        $rawToken = bin2hex(random_bytes(32));
        $now      = date('Y-m-d H:i:s');

        $deviceData = [
            'id'                  => $deviceId,
            'device_name'         => 'Biometric Test Kiosk',
            'device_identifier'   => 'DEV-BIO-' . strtoupper(bin2hex(random_bytes(3))),
            'device_type'         => 'TABLET',
            'terminal_mode'       => $terminalMode,
            'device_epoch_id'     => EmployeeModel::generateUuidV4(),
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'device_token_hash'   => hash('sha256', $rawToken),
            'token_created_at'    => $now,
            'token_expires_at'    => date('Y-m-d H:i:s', strtotime('+1 year')),
            'paired_at'           => $now,
            'last_seen_at'        => $now,
            'created_at'          => $now,
            'updated_at'          => $now,
        ];

        $this->deviceModel->insert($deviceData);

        return [
            'device_id' => $deviceId,
            'raw_token' => $rawToken,
            'record'    => $deviceData,
        ];
    }

    /**
     * Rule 1: Confidence score below 85% (0.85) or distance > 0.40 must be rejected as HTTP 422
     */
    public function testFacePunchRejectsConfidenceBelowThreshold()
    {
        $kiosk = $this->createAuthorizedKioskDevice();
        $empId = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'            => $empId,
            'employee_id'   => 'TEST-BIO-001',
            'full_name'     => 'Low Confidence Subject',
            'gender'        => 'MALE',
            'department'    => 'Engineering',
            'designation'   => 'Engineer',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        try {
            $payload = [
                'employee_id'       => 'TEST-BIO-001',
                'confidence'        => 0.72,
                'matching_distance' => 0.52,
                'client_uuid'       => 'test-client-' . bin2hex(random_bytes(4)),
            ];

            $response = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $kiosk['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->post('/api/kiosk/face-punch', $payload);

            $response->assertStatus(422);
            $response->assertJSONFragment([
                'status' => 'error',
                'code'   => 'CONFIDENCE_BELOW_THRESHOLD',
            ]);

            // Verify audit trail logged the low-confidence attempt
            $audit = $this->auditModel
                ->where('action', 'FACE_CONFIDENCE_BELOW_THRESHOLD')
                ->orderBy('created_at', 'DESC')
                ->first();

            $this->assertNotNull($audit);
            $this->assertStringContainsString('TEST-BIO-001', $audit['details']);
        } finally {
            $this->employeeModel->delete($empId);
            $this->deviceModel->delete($kiosk['device_id']);
        }
    }

    /**
     * Rule 2: Detected biometric gender mismatch must be rejected with HTTP 422 BIOMETRIC_GENDER_MISMATCH
     */
    public function testFacePunchRejectsBiometricGenderMismatch()
    {
        $kiosk = $this->createAuthorizedKioskDevice();
        $empId = EmployeeModel::generateUuidV4();

        // Enrolled as FEMALE (e.g. Madhavi)
        $this->employeeModel->insert([
            'id'            => $empId,
            'employee_id'   => 'TEST-FEM-002',
            'full_name'     => 'Female Profile Test',
            'gender'        => 'FEMALE',
            'department'    => 'Science',
            'designation'   => 'Faculty',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        try {
            // Scanner detects MALE face with 95% gender probability
            $payload = [
                'employee_id'        => 'TEST-FEM-002',
                'confidence'         => 0.92,
                'matching_distance'  => 0.30,
                'detected_gender'    => 'MALE',
                'gender_probability' => 0.95,
                'client_uuid'        => 'test-client-' . bin2hex(random_bytes(4)),
            ];

            $response = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $kiosk['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->post('/api/kiosk/face-punch', $payload);

            $response->assertStatus(422);
            $response->assertJSONFragment([
                'status' => 'error',
                'code'   => 'BIOMETRIC_GENDER_MISMATCH',
            ]);

            // Verify audit trail logged gender mismatch
            $audit = $this->auditModel
                ->where('action', 'BIOMETRIC_GENDER_MISMATCH')
                ->orderBy('created_at', 'DESC')
                ->first();

            $this->assertNotNull($audit);
            $this->assertStringContainsString('MALE', $audit['details']);
            $this->assertStringContainsString('FEMALE', $audit['details']);
        } finally {
            $this->employeeModel->delete($empId);
            $this->deviceModel->delete($kiosk['device_id']);
        }
    }

    /**
     * Rule 3: Server-side 128-d Euclidean distance check rejects descriptors with distance > 0.40
     */
    public function testFacePunchRejectsServerEmbeddingMismatch()
    {
        $kiosk = $this->createAuthorizedKioskDevice();
        $empId = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'            => $empId,
            'employee_id'   => 'TEST-VEC-003',
            'full_name'     => 'Vector Verified Subject',
            'gender'        => 'MALE',
            'department'    => 'Math',
            'designation'   => 'Teacher',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        // Enrolled vector: 128 elements of 0.05
        $enrolledVector = array_fill(0, 128, 0.05);
        $templateId = (string) time() . '_' . bin2hex(random_bytes(4));
        $this->templateModel->insert([
            'id'                => $templateId,
            'employee_id'       => $empId,
            'biometric_gender'  => 'MALE',
            'template_version'  => 'v1.0',
            'vector_dimensions' => 128,
            'embedding_data'    => json_encode($enrolledVector),
            'model_name'        => 'face-api-resnet34',
            'quality_score'     => 0.98,
            'registered_by'     => 'admin',
        ]);

        try {
            // Captured vector: 128 elements of 0.50 (Euclidean distance = sqrt(128 * (0.45)^2) ≈ 5.09 >> 0.40)
            $capturedVector = array_fill(0, 128, 0.50);

            $payload = [
                'employee_id'         => 'TEST-VEC-003',
                'confidence'          => 0.90,
                'matching_distance'   => 0.35,
                'detected_gender'     => 'MALE',
                'gender_probability'  => 0.90,
                'captured_descriptor' => $capturedVector,
                'client_uuid'         => 'test-client-' . bin2hex(random_bytes(4)),
            ];

            $response = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $kiosk['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->post('/api/kiosk/face-punch', $payload);

            $response->assertStatus(422);
            $response->assertJSONFragment([
                'status' => 'error',
                'code'   => 'SERVER_EMBEDDING_MISMATCH',
            ]);
        } finally {
            $this->templateModel->where('employee_id', $empId)->delete();
            $this->employeeModel->delete($empId);
            $this->deviceModel->delete($kiosk['device_id']);
        }
    }

    /**
     * Rule 4: Ambiguous runner-up delta (< 0.08) tags biometric_flag as AMBIGUOUS_DELTA and logs audit
     */
    public function testFacePunchLogsAmbiguousDeltaAlertWhenRunnerUpClose()
    {
        $kiosk = $this->createAuthorizedKioskDevice();
        $empId = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'            => $empId,
            'employee_id'   => 'TEST-AMB-004',
            'full_name'     => 'Ambiguous Match Staff',
            'gender'        => 'MALE',
            'department'    => 'Sports',
            'designation'   => 'Coach',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $clientUuid = 'test-amb-' . bin2hex(random_bytes(4));

        try {
            $payload = [
                'employee_id'        => 'TEST-AMB-004',
                'confidence'         => 0.91,
                'matching_distance'  => 0.32,
                'runner_up_distance' => 0.35,
                'ambiguity_delta'    => 0.03, // < 0.08: Ambiguous!
                'detected_gender'    => 'MALE',
                'gender_probability' => 0.88,
                'client_uuid'        => $clientUuid,
            ];

            $response = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $kiosk['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->post('/api/kiosk/face-punch', $payload);

            $response->assertStatus(200);

            // Verify event table tagged as AMBIGUOUS_DELTA
            $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
            $this->assertNotNull($event);
            $this->assertSame('AMBIGUOUS_DELTA', $event['biometric_flag']);
            $this->assertEqualsWithDelta(0.03, (float) $event['ambiguity_delta'], 0.001);

            // Verify audit trail logged AMBIGUOUS_DISTANCE_DELTA
            $audit = $this->auditModel
                ->where('action', 'AMBIGUOUS_DISTANCE_DELTA')
                ->orderBy('created_at', 'DESC')
                ->first();

            $this->assertNotNull($audit);
            $this->assertStringContainsString('TEST-AMB-004', $audit['details']);
        } finally {
            $this->eventModel->where('client_uuid', $clientUuid)->delete();
            $this->employeeModel->delete($empId);
            $this->deviceModel->delete($kiosk['device_id']);
        }
    }

    /**
     * Rule 5: Successful high-confidence, distinct match logs VERIFIED_STRICT
     */
    public function testFacePunchSucceedsWithHighConfidenceAndStrictMatching()
    {
        $kiosk = $this->createAuthorizedKioskDevice();
        $empId = EmployeeModel::generateUuidV4();

        $this->employeeModel->insert([
            'id'            => $empId,
            'employee_id'   => 'TEST-OK-005',
            'full_name'     => 'Strict Verified Member',
            'gender'        => 'MALE',
            'department'    => 'Administration',
            'designation'   => 'Officer',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $clientUuid = 'test-ok-' . bin2hex(random_bytes(4));

        try {
            $payload = [
                'employee_id'        => 'TEST-OK-005',
                'confidence'         => 0.96,
                'matching_distance'  => 0.22,
                'runner_up_distance' => 0.45,
                'ambiguity_delta'    => 0.23, // >= 0.08
                'detected_gender'    => 'MALE',
                'gender_probability' => 0.95,
                'client_uuid'        => $clientUuid,
            ];

            $response = $this->withHeaders([
                'Cookie'           => 'edifies_kiosk_token=' . $kiosk['raw_token'],
                'X-Requested-With' => 'XMLHttpRequest',
                'Content-Type'     => 'application/json',
            ])->post('/api/kiosk/face-punch', $payload);

            $response->assertStatus(200);

            // Verify event table recorded with VERIFIED_STRICT
            $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
            $this->assertNotNull($event);
            $this->assertSame('VERIFIED_STRICT', $event['biometric_flag']);
            $this->assertEqualsWithDelta(0.22, (float) $event['matching_distance'], 0.001);
            $this->assertEqualsWithDelta(0.23, (float) $event['ambiguity_delta'], 0.001);
        } finally {
            $this->eventModel->where('client_uuid', $clientUuid)->delete();
            $this->employeeModel->delete($empId);
            $this->deviceModel->delete($kiosk['device_id']);
        }
    }

    /**
     * Rule 6: Admin CRUD operations persist employee gender accurately
     */
    public function testEmployeeCreateAndUpdatePersistsGender()
    {
        $uniqueEmpId = 'EMP-GEND-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        // Create with FEMALE
        $createRes = $this->withSession($this->adminSession)->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-CSRF-TOKEN'     => csrf_hash(),
        ])->post('/admin/employees', [
            csrf_token()    => csrf_hash(),
            'employee_id'   => $uniqueEmpId,
            'full_name'     => 'Madhavi Verification Test',
            'gender'        => 'FEMALE',
            'department'    => 'Academics',
            'designation'   => 'Senior Teacher',
            'status'        => 'ACTIVE',
        ]);

        $createRes->assertStatus(201);
        $createJson = json_decode($createRes->getJSON(), true);
        $this->assertSame('success', $createJson['status']);

        $emp = $this->employeeModel->where('employee_id', $uniqueEmpId)->first();
        $this->assertNotNull($emp);
        $this->assertSame('FEMALE', $emp['gender']);

        try {
            // Update to MALE
            $updateRes = $this->withSession($this->adminSession)->withHeaders([
                'X-Requested-With' => 'XMLHttpRequest',
                'X-CSRF-TOKEN'     => csrf_hash(),
            ])->post("/admin/employees/{$emp['id']}/update", [
                csrf_token()    => csrf_hash(),
                'employee_id'   => $uniqueEmpId,
                'full_name'     => 'Madhavi Verification Test Updated',
                'gender'        => 'MALE',
                'department'    => 'Academics',
                'designation'   => 'Senior Teacher',
                'status'        => 'ACTIVE',
            ]);

            $updateRes->assertStatus(200);

            $updated = $this->employeeModel->find($emp['id']);
            $this->assertSame('MALE', $updated['gender']);
        } finally {
            $this->employeeModel->delete($emp['id']);
        }
    }
}
