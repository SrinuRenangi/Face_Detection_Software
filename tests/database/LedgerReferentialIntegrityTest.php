<?php

namespace Tests\Database;

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class LedgerReferentialIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;
    protected $refresh = false;

    protected $db;
    protected AttendanceRecordModel $attendanceModel;
    protected AttendanceEventModel $eventModel;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $kioskModel;
    protected AttendanceSyncService $syncService;

    protected string $kioskId;
    protected string $employeeId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db              = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->kioskId    = EmployeeModel::generateUuidV4();
        $this->employeeId = EmployeeModel::generateUuidV4();

        $this->kioskModel->insert([
            'id'                  => $this->kioskId,
            'device_name'         => 'Integrity Test Terminal',
            'device_identifier'   => 'INT-DEV-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-integrity',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'INT-EMP-01',
            'full_name'   => 'Integrity Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        // Record a punch into the immutable ledger
        $this->syncService->processPunch([
            'client_uuid'           => EmployeeModel::generateUuidV4(),
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => date('Y-m-d H:i:s', time() - 120),
            'device_event_sequence' => 1,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('attendance_events')->where('employee_id', $this->employeeId)->delete();
        $this->db->table('attendance_records')->where('employee_id', $this->employeeId)->delete();
        $this->employeeModel->delete($this->employeeId);
        $this->kioskModel->delete($this->kioskId);
        parent::tearDown();
    }

    public function testEmployeeWithHistoryCannotBeHardDeleted()
    {
        $this->expectException(DatabaseException::class);
        // Attempt hard-deletion of employee who has records in attendance_events
        $this->employeeModel->delete($this->employeeId);
    }

    public function testDeviceWithHistoryCannotBeHardDeleted()
    {
        $this->expectException(DatabaseException::class);
        // Attempt hard-deletion of terminal which has records in attendance_events
        $this->kioskModel->delete($this->kioskId);
    }

    public function testEmployeeCanBeDeactivatedAndLedgerRemainsIntact()
    {
        // Deactivation is the supported operational method
        $updated = $this->employeeModel->update($this->employeeId, ['status' => 'INACTIVE']);
        $this->assertTrue($updated);

        $emp = $this->employeeModel->find($this->employeeId);
        $this->assertSame('INACTIVE', $emp['status']);

        // Historical ledger events are preserved
        $events = $this->eventModel->where('employee_id', $this->employeeId)->findAll();
        $this->assertCount(1, $events);
    }

    public function testDeviceCanBeRevokedAndLedgerRemainsIntact()
    {
        // Revocation is the supported operational method
        $updated = $this->kioskModel->update($this->kioskId, [
            'status'            => 'REVOKED',
            'revoked_at'        => date('Y-m-d H:i:s'),
            'revocation_reason' => 'Hardware retired',
        ]);
        $this->assertTrue($updated);

        $dev = $this->kioskModel->find($this->kioskId);
        $this->assertSame('REVOKED', $dev['status']);

        // Historical ledger events are preserved
        $events = $this->eventModel->where('device_id', $this->kioskId)->findAll();
        $this->assertCount(1, $events);
    }
}

