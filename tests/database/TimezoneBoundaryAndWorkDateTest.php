<?php

namespace Tests\Database;

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Database;

class TimezoneBoundaryAndWorkDateTest extends CIUnitTestCase
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
            'device_name'         => 'Timezone Test Terminal',
            'device_identifier'   => 'TZ-TEST-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-tz-test',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'TZ-EMP-01',
            'full_name'   => 'Timezone Subject Alpha',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
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

    /**
     * Test Case 1: 00:30:00 IST on Nov 3 is 19:00:00 UTC on Nov 2 (Previous UTC date, next IST date).
     * Must map to official work_date 2026-11-03!
     */
    public function testEarlyMorningBoundaryPreviousUtcDayMapsToNextIstDate()
    {
        $utcIsoString = '2026-11-02T19:00:00Z'; // 00:30 IST on Nov 3
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $utcIsoString,
            'device_event_sequence' => 1,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('CREATED', $result['status']);
        $this->assertSame('2026-11-03', $result['work_date']);

        $record = $this->attendanceModel->find($result['record_id']);
        $this->assertNotNull($record);
        $this->assertSame('2026-11-03', $record['work_date']);
    }

    /**
     * Test Case 2: 03:00:00 IST on Nov 3 is 21:30:00 UTC on Nov 2.
     */
    public function test0300IstBoundary()
    {
        $rawIstOffsetString = '2026-11-03T03:00:00+05:30';
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $rawIstOffsetString,
            'device_event_sequence' => 2,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('2026-11-03', $result['work_date']);
    }

    /**
     * Test Case 3: 05:59:59 IST on Nov 3 is 00:29:59 UTC on Nov 3.
     */
    public function test0559IstBoundary()
    {
        $rawString = '2026-11-03T05:59:59+05:30';
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $rawString,
            'device_event_sequence' => 3,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('2026-11-03', $result['work_date']);
    }

    /**
     * Test Case 4: 06:00:00 IST on Nov 3 is 00:30:00 UTC on Nov 3.
     */
    public function test0600IstBoundary()
    {
        $rawString = '2026-11-03T06:00:00+05:30';
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $rawString,
            'device_event_sequence' => 4,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('2026-11-03', $result['work_date']);
    }

    /**
     * Test Case 5: 23:30:00 IST on Nov 2 is 18:00:00 UTC on Nov 2.
     */
    public function test2330IstBoundary()
    {
        $rawString = '2026-11-02T23:30:00+05:30';
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $rawString,
            'device_event_sequence' => 5,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('2026-11-02', $result['work_date']);
    }

    /**
     * Test Case 6: 23:59:59 IST on Nov 2 is 18:29:59 UTC on Nov 2.
     */
    public function test2359IstBoundary()
    {
        $rawString = '2026-11-02T23:59:59+05:30';
        $clientUuid = EmployeeModel::generateUuidV4();

        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->kioskId,
            'timestamp'             => $rawString,
            'device_event_sequence' => 6,
            'sync_source'           => 'DIRECT_ONLINE',
        ]);

        $this->assertSame('2026-11-02', $result['work_date']);
    }
}
