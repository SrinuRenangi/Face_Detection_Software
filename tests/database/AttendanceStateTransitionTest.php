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

class AttendanceStateTransitionTest extends CIUnitTestCase
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

    protected string $entryDeviceId;
    protected string $exitDeviceId;
    protected string $autoDeviceId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::connect();
        $this->attendanceModel = new AttendanceRecordModel();
        $this->eventModel      = new AttendanceEventModel();
        $this->employeeModel   = new EmployeeModel();
        $this->kioskModel      = new KioskDeviceModel();
        $this->syncService     = new AttendanceSyncService();

        $this->entryDeviceId = EmployeeModel::generateUuidV4();
        $this->exitDeviceId  = EmployeeModel::generateUuidV4();
        $this->autoDeviceId  = EmployeeModel::generateUuidV4();

        // 1. Entry-Only Terminal (e.g. Main Lobby Stand)
        $this->kioskModel->insert([
            'id'                => $this->entryDeviceId,
            'device_name'       => 'Lobby Entry Kiosk',
            'device_identifier' => 'ENTRY-KIOSK-' . bin2hex(random_bytes(3)),
            'device_type'       => 'TABLET',
            'terminal_mode'     => 'ENTRY_ONLY',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        // 2. Exit-Only Terminal (e.g. Turnstile Exit Gate)
        $this->kioskModel->insert([
            'id'                => $this->exitDeviceId,
            'device_name'       => 'Gate Exit Kiosk',
            'device_identifier' => 'EXIT-KIOSK-' . bin2hex(random_bytes(3)),
            'device_type'       => 'SMARTPHONE',
            'terminal_mode'     => 'EXIT_ONLY',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);

        // 3. Directional Auto Terminal (e.g. Split-Lane Corridor)
        $this->kioskModel->insert([
            'id'                => $this->autoDeviceId,
            'device_name'       => 'Corridor Auto Kiosk',
            'device_identifier' => 'AUTO-KIOSK-' . bin2hex(random_bytes(3)),
            'device_type'       => 'TABLET',
            'terminal_mode'     => 'DIRECTIONAL_AUTO',
            'status'            => 'ACTIVE',
            'created_at'        => date('Y-m-d H:i:s'),
            'updated_at'        => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->kioskModel->delete($this->entryDeviceId);
        $this->kioskModel->delete($this->exitDeviceId);
        $this->kioskModel->delete($this->autoDeviceId);
        parent::tearDown();
    }

    public function testEntryOnlyTerminalNeverGeneratesCheckOutOnRepeatedFacePresence()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'TRANS-EMP-01',
            'full_name'   => 'Continuous Presence Teacher',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            $punchTime1 = date('Y-m-d H:i:s', time() - 3600);
            $punchTime2 = date('Y-m-d H:i:s', time() - 3180); // 7 min later

            // First appearance: Check-In 60 mins ago
            $punch1 = $this->syncService->processPunch([
                'client_uuid' => EmployeeModel::generateUuidV4(),
                'employee_id' => $empUuid,
                'device_id'   => $this->entryDeviceId,
                'timestamp'   => $punchTime1,
            ]);
            $this->assertSame('CREATED', $punch1['status']);
            $this->assertSame('CHECK_IN', $punch1['event_type']);

            // Second appearance: Employee stays talking in front of camera (7 min later)
            // Under old 5-minute rule, this wrongly generated a check-out!
            // Under new state machine, this must be PRESENCE_CONFIRMED and DUPLICATE_IGNORED!
            $punch2 = $this->syncService->processPunch([
                'client_uuid' => EmployeeModel::generateUuidV4(),
                'employee_id' => $empUuid,
                'device_id'   => $this->entryDeviceId,
                'timestamp'   => $punchTime2,
            ]);
            $this->assertSame('DUPLICATE_IGNORED', $punch2['status']);
            $this->assertSame('PRESENCE_CONFIRMED', $punch2['event_type']);

            // Verify the daily record check_out_time is STILL NULL!
            $dailySummary = $this->attendanceModel->find($punch1['record_id']);
            $this->assertSame('CHECKED_IN', $dailySummary['status']);
            $this->assertNull($dailySummary['check_out_time']);
            $this->assertSame($punchTime1, $dailySummary['check_in_time']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testExitOnlyTerminalRejectsCheckOutWithoutOpenCheckInRecord()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'TRANS-EMP-02',
            'full_name'   => 'Ghost Departure Staff',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            // Employee scans at EXIT_ONLY terminal without ever checking in today
            $punch = $this->syncService->processPunch([
                'client_uuid' => EmployeeModel::generateUuidV4(),
                'employee_id' => $empUuid,
                'device_id'   => $this->exitDeviceId,
                'timestamp'   => date('Y-m-d H:i:s', time() - 3600),
            ]);

            // Must NOT create fake attendance record!
            $this->assertSame('REJECTED_NO_OPEN_RECORD', $punch['status']);
            $this->assertSame('CHECK_OUT', $punch['event_type']);

            $dailySummary = $this->attendanceModel->where('employee_id', $empUuid)->where('work_date', date('Y-m-d'))->first();
            $this->assertNull($dailySummary);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testDirectionalAutoTerminalWithAmbiguousMovementDoesNotFabricateCheckOut()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'TRANS-EMP-03',
            'full_name'   => 'Ambiguous Movement Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            $punchTime1 = date('Y-m-d H:i:s', time() - 7200);
            $punchTime2 = date('Y-m-d H:i:s', time() - 3600);

            // 1. Initial check-in on ENTRY
            $punch1 = $this->syncService->processPunch([
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'employee_id'        => $empUuid,
                'device_id'          => $this->autoDeviceId,
                'timestamp'          => $punchTime1,
                'movement_direction' => 'ENTRY',
            ]);
            $this->assertSame('CREATED', $punch1['status']);

            // 2. Later observation with AMBIGUOUS / UNKNOWN direction
            // Must NOT trigger check-out!
            $punch2 = $this->syncService->processPunch([
                'client_uuid'        => EmployeeModel::generateUuidV4(),
                'employee_id'        => $empUuid,
                'device_id'          => $this->autoDeviceId,
                'timestamp'          => $punchTime2,
                'movement_direction' => 'UNKNOWN',
            ]);
            $this->assertSame('DUPLICATE_IGNORED', $punch2['status']);
            $this->assertSame('PRESENCE_CONFIRMED', $punch2['event_type']);

            $dailySummary = $this->attendanceModel->find($punch1['record_id']);
            $this->assertNull($dailySummary['check_out_time']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }

    public function testDuplicateSequenceDetectedIsQuarantined()
    {
        $empUuid = EmployeeModel::generateUuidV4();
        $this->employeeModel->insert([
            'id'          => $empUuid,
            'employee_id' => 'TRANS-EMP-04',
            'full_name'   => 'Sequence Test Subject',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        try {
            // First punch with sequence 42
            $punch1 = $this->syncService->processPunch([
                'client_uuid'           => EmployeeModel::generateUuidV4(),
                'employee_id'           => $empUuid,
                'device_id'             => $this->entryDeviceId,
                'device_epoch_id'       => 'test-epoch-99',
                'device_event_sequence' => 42,
                'timestamp'             => date('Y-m-d H:i:s', time() - 3600),
            ]);
            $this->assertSame('CREATED', $punch1['status']);

            // Malicious or corrupted punch with DUPLICATE sequence 42 in same epoch
            $punch2 = $this->syncService->processPunch([
                'client_uuid'           => EmployeeModel::generateUuidV4(),
                'employee_id'           => $empUuid,
                'device_id'             => $this->entryDeviceId,
                'device_epoch_id'       => 'test-epoch-99',
                'device_event_sequence' => 42,
                'timestamp'             => date('Y-m-d H:i:s', time() - 3300),
            ]);
            $this->assertSame('QUARANTINED', $punch2['status']);
        } finally {
            $this->db->table('attendance_events')->where('employee_id', $empUuid)->delete();
            $this->db->table('attendance_records')->where('employee_id', $empUuid)->delete();
            $this->employeeModel->delete($empUuid);
        }
    }
}

