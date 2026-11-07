<?php

namespace Tests\Database;

use App\Models\EmployeeModel;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class EmployeeManagementDatabaseTest extends CIUnitTestCase
{
    protected $db;
    protected EmployeeModel $employeeModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->employeeModel = new EmployeeModel();
    }

    public function testCreateEmployeeSuccessWithUuidV4()
    {
        $id = EmployeeModel::generateUuidV4();
        $empId = 'TEST-EMP-' . bin2hex(random_bytes(4));

        $data = [
            'id'            => $id,
            'employee_id'   => $empId,
            'full_name'     => 'Dr. Jane Goodall',
            'department'    => 'Biology',
            'designation'   => 'Lead Researcher',
            'joining_date'  => '2024-01-15',
            'contact_phone' => '+91 9876543210',
            'status'        => 'ACTIVE',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];

        $inserted = $this->employeeModel->insert($data);
        $this->assertNotFalse($inserted);

        $found = $this->employeeModel->find($id);
        $this->assertNotNull($found);
        $this->assertSame($empId, $found['employee_id']);
        $this->assertSame('Dr. Jane Goodall', $found['full_name']);
        $this->assertSame('Biology', $found['department']);
        $this->assertSame('Lead Researcher', $found['designation']);
        $this->assertSame('ACTIVE', $found['status']);

        // Cleanup
        $this->employeeModel->delete($id);
    }

    public function testDuplicateEmployeeIdRejection()
    {
        $id1 = EmployeeModel::generateUuidV4();
        $id2 = EmployeeModel::generateUuidV4();
        $empId = 'UNIQ-EMP-' . bin2hex(random_bytes(4));

        $this->employeeModel->insert([
            'id'          => $id1,
            'employee_id' => $empId,
            'full_name'   => 'First Employee',
            'department'  => 'Physics',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->expectException(DatabaseException::class);

        try {
            $this->employeeModel->insert([
                'id'          => $id2,
                'employee_id' => $empId, // Duplicate employee_id
                'full_name'   => 'Duplicate Employee',
                'department'  => 'Chemistry',
                'status'      => 'ACTIVE',
                'created_at'  => date('Y-m-d H:i:s'),
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
        } finally {
            $this->employeeModel->delete($id1);
        }
    }

    public function testEmployeeModelFindByEmployeeId()
    {
        $id = EmployeeModel::generateUuidV4();
        $empId = 'FIND-ME-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $id,
            'employee_id' => $empId,
            'full_name'   => 'Searchable Employee',
            'department'  => 'Literature',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $found = $this->employeeModel->findByEmployeeId($empId);
        $this->assertNotNull($found);
        $this->assertSame($id, $found['id']);
        $this->assertSame('Literature', $found['department']);

        // Non-existent search returns null
        $notFound = $this->employeeModel->findByEmployeeId('DOES-NOT-EXIST-999');
        $this->assertNull($notFound);

        $this->employeeModel->delete($id);
    }

    public function testBiometricStatusWhenNoTemplateIsRecorded()
    {
        $id = EmployeeModel::generateUuidV4();
        $empId = 'BIO-NONE-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $id,
            'employee_id' => $empId,
            'full_name'   => 'No Template Employee',
            'department'  => 'History',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $emp = $this->employeeModel->findByIdWithBiometrics($id);
        $this->assertNotNull($emp);
        $this->assertSame('NOT_REGISTERED', $emp['face_status']);
        $this->assertNull($emp['face_template_id']);

        $this->employeeModel->delete($id);
    }

    public function testBiometricStatusWhenActiveTemplateExists()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $tmplUuid = EmployeeModel::generateUuidV4();
        $empId = 'BIO-REG-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Enrolled Employee',
            'department'  => 'Engineering',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Insert valid dummy embedding (512 floats base64 encoded)
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

        $emp = $this->employeeModel->findByIdWithBiometrics($empUuid);
        $this->assertNotNull($emp);
        $this->assertSame('REGISTERED', $emp['face_status']);
        $this->assertSame($tmplUuid, $emp['face_template_id']);

        // Cleanup
        $this->db->table('employee_face_templates')->where('id', $tmplUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testBiometricStatusWhenTemplateExistsAndEmployeeInactive()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $tmplUuid = EmployeeModel::generateUuidV4();
        $empId = 'BIO-DIS-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Disabled Employee',
            'department'  => 'Administration',
            'status'      => 'INACTIVE', // Account deactivated
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
            'quality_score'     => 0.9000,
            'registered_by'     => 'admin',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        $emp = $this->employeeModel->findByIdWithBiometrics($empUuid);
        $this->assertNotNull($emp);
        $this->assertSame('DISABLED', $emp['face_status']);

        // Cleanup
        $this->db->table('employee_face_templates')->where('id', $tmplUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testNonDestructiveStatusTogglePreservesRecord()
    {
        $id = EmployeeModel::generateUuidV4();
        $empId = 'TOGGLE-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $id,
            'employee_id' => $empId,
            'full_name'   => 'Toggle Subject',
            'department'  => 'Sports',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Toggle to INACTIVE (deactivation)
        $this->employeeModel->update($id, ['status' => 'INACTIVE']);
        $afterDeact = $this->employeeModel->find($id);
        $this->assertNotNull($afterDeact);
        $this->assertSame('INACTIVE', $afterDeact['status']);

        // Toggle back to ACTIVE (reactivation)
        $this->employeeModel->update($id, ['status' => 'ACTIVE']);
        $afterReact = $this->employeeModel->find($id);
        $this->assertNotNull($afterReact);
        $this->assertSame('ACTIVE', $afterReact['status']);

        $this->employeeModel->delete($id);
    }

    public function testAttendanceRecordSafeguardDetectsHistory()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $attUuid = EmployeeModel::generateUuidV4();
        $empId = 'ATT-SAFE-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Attendance Protected',
            'department'  => 'Arts',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($this->employeeModel->hasAttendanceRecords($empUuid));

        // Insert historical attendance record
        $this->db->table('attendance_records')->insert([
            'id'             => $attUuid,
            'employee_id'    => $empUuid,
            'work_date'      => date('Y-m-d'),
            'status'         => 'CHECKED_IN',
            'check_in_time'  => date('Y-m-d 08:30:00'),
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        // Safeguard must detect presence of attendance records
        $this->assertTrue($this->employeeModel->hasAttendanceRecords($empUuid));

        // Cleanup
        $this->db->table('attendance_records')->where('id', $attUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testFaceTemplateSafeguardDetectsHistory()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $tmplUuid = EmployeeModel::generateUuidV4();
        $empId = 'TMPL-SAFE-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => $empId,
            'full_name'   => 'Template Protected',
            'department'  => 'Music',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->assertFalse($this->employeeModel->hasFaceTemplates($empUuid));

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

        $this->assertTrue($this->employeeModel->hasFaceTemplates($empUuid));

        // Cleanup
        $this->db->table('employee_face_templates')->where('id', $tmplUuid)->delete();
        $this->employeeModel->delete($empUuid);
    }

    public function testSearchAndFilterByDepartmentAndStatus()
    {
        $id1 = EmployeeModel::generateUuidV4();
        $id2 = EmployeeModel::generateUuidV4();
        $empId1 = 'SRCH-1-' . bin2hex(random_bytes(3));
        $empId2 = 'SRCH-2-' . bin2hex(random_bytes(3));

        $this->employeeModel->insert([
            'id'          => $id1,
            'employee_id' => $empId1,
            'full_name'   => 'Albert Einstein Physics',
            'department'  => 'Theoretical Physics',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $id2,
            'employee_id' => $empId2,
            'full_name'   => 'Isaac Newton Inactive',
            'department'  => 'Classical Physics',
            'status'      => 'INACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Search by employee ID
        $resId = $this->employeeModel->getWithBiometricStatus($empId1);
        $this->assertCount(1, $resId);
        $this->assertSame($empId1, $resId[0]['employee_id']);

        // Search by name substring
        $resName = $this->employeeModel->getWithBiometricStatus('Einstein');
        $this->assertCount(1, $resName);
        $this->assertSame('Albert Einstein Physics', $resName[0]['full_name']);

        // Filter by status ACTIVE
        $countActive = $this->employeeModel->countFiltered('Einstein', 'ACTIVE');
        $this->assertSame(1, $countActive);

        // Filter by status INACTIVE
        $countInactive = $this->employeeModel->countFiltered('Einstein', 'INACTIVE');
        $this->assertSame(0, $countInactive);

        // Cleanup
        $this->employeeModel->delete($id1);
        $this->employeeModel->delete($id2);
    }

    public function testGetCountsSummary()
    {
        $counts = $this->employeeModel->getCountsSummary();
        $this->assertIsArray($counts);
        $this->assertArrayHasKey('total', $counts);
        $this->assertArrayHasKey('active', $counts);
        $this->assertArrayHasKey('inactive', $counts);
        $this->assertArrayHasKey('faceRegistered', $counts);
        $this->assertGreaterThanOrEqual(0, $counts['total']);
        $this->assertGreaterThanOrEqual(0, $counts['active']);
    }
}
