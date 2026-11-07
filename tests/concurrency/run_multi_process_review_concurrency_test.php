<?php

defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Models\EmployeeModel;
use Config\Database;

class MultiProcessReviewConcurrencyRunner
{
    private $db;
    private $phpBin;
    private $workerScript;
    private string $testEmpId;
    private string $testDevId;
    private string $adminId1;
    private string $adminId2;

    public function __construct()
    {
        $this->db = Database::connect('tests');
        $this->phpBin = 'C:\\tools\\php85\\php.exe';
        $this->workerScript = realpath(__DIR__ . '/worker_review.php');

        $this->setupFixtures();
    }

    private function setupFixtures(): void
    {
        $now = date('Y-m-d H:i:s');

        // Ensure baseline employee
        $emp = $this->db->query("SELECT id FROM employees WHERE employee_id = 'CONCUR-REV-EMP'")->getRowArray();
        if (!$emp) {
            $this->testEmpId = EmployeeModel::generateUuidV4();
            $this->db->table('employees')->insert([
                'id'          => $this->testEmpId,
                'employee_id' => 'CONCUR-REV-EMP',
                'full_name'   => 'Review Concurrency Employee',
                'department'  => 'Science',
                'status'      => 'ACTIVE',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        } else {
            $this->testEmpId = $emp['id'];
        }

        // Ensure baseline device
        $dev = $this->db->query("SELECT id FROM kiosk_devices WHERE device_identifier = 'CONCUR-REV-DEV'")->getRowArray();
        if (!$dev) {
            $this->testDevId = EmployeeModel::generateUuidV4();
            $this->db->table('kiosk_devices')->insert([
                'id'                  => $this->testDevId,
                'device_name'         => 'Review Concurrency Kiosk',
                'device_identifier'   => 'CONCUR-REV-DEV',
                'terminal_mode'       => 'ENTRY_ONLY',
                'device_epoch_id'     => 'epoch-concur-rev',
                'last_event_sequence' => 0,
                'status'              => 'ACTIVE',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        } else {
            $this->testDevId = $dev['id'];
        }

        // Ensure 2 admin users
        $a1 = $this->db->query("SELECT id FROM admin_users WHERE username = 'admin_concur_1'")->getRowArray();
        if (!$a1) {
            $this->adminId1 = EmployeeModel::generateUuidV4();
            $this->db->table('admin_users')->insert([
                'id'            => $this->adminId1,
                'username'      => 'admin_concur_1',
                'password_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
                'full_name'     => 'Admin Concurrency Alpha',
                'role'          => 'ROLE_ADMIN',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } else {
            $this->adminId1 = $a1['id'];
        }

        $a2 = $this->db->query("SELECT id FROM admin_users WHERE username = 'admin_concur_2'")->getRowArray();
        if (!$a2) {
            $this->adminId2 = EmployeeModel::generateUuidV4();
            $this->db->table('admin_users')->insert([
                'id'            => $this->adminId2,
                'username'      => 'admin_concur_2',
                'password_hash' => '$2y$10$abcdefghijklmnopqrstuvwxyz1234567890',
                'full_name'     => 'Admin Concurrency Beta',
                'role'          => 'ROLE_ADMIN',
                'is_active'     => 1,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        } else {
            $this->adminId2 = $a2['id'];
        }
    }

    private function createHeldEvent(string $timeStr = '2026-11-03 09:00:00', ?string $empId = null): string
    {
        $eventId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();
        $now = date('Y-m-d H:i:s');
        $epochId = 'epoch-rev-' . bin2hex(random_bytes(3));
        $seq = rand(1000, 999999);
        $targetEmp = $empId ?? $this->testEmpId;

        $this->db->table('attendance_events')->insert([
            'id'                     => $eventId,
            'client_uuid'            => $clientUuid,
            'employee_id'            => $targetEmp,
            'device_id'              => $this->testDevId,
            'device_epoch_id'        => $epochId,
            'device_event_sequence'  => $seq,
            'event_type'             => 'CHECK_IN',
            'terminal_mode'          => 'ENTRY_ONLY',
            'client_recorded_at'     => $timeStr,
            'server_received_at'     => $now,
            'client_timezone'        => 'Asia/Kolkata',
            'clock_trust_status'     => 'TIME_MANIPULATED',
            'synchronization_source' => 'OFFLINE_SYNC',
            'payload_hash'           => hash('sha256', $clientUuid),
            'processing_status'      => 'REVIEW_REQUIRED',
            'notes'                  => 'Held for review test',
            'created_at'             => $now,
        ]);

        return $eventId;
    }

    public function runAll(): bool
    {
        echo "====================================================================\n";
        echo "   EDIFIES ATTENDANCE: MULTI-PROCESS REVIEW CONCURRENCY HARNESS    \n";
        echo "====================================================================\n";
        echo "MariaDB Host: 127.0.0.1:3307 | Target DB: attendance_test\n";
        echo "PHP Binary:   " . $this->phpBin . "\n";
        echo "Worker:       " . $this->workerScript . "\n\n";

        $allPassed = true;

        $allPassed = $this->testScenario1_TwoAdminsApproveSimultaneously() && $allPassed;
        $allPassed = $this->testScenario2_TwoAdminsRejectSimultaneously() && $allPassed;
        $allPassed = $this->testScenario3_OneApproveOneRejectSimultaneously() && $allPassed;
        $allPassed = $this->testScenario4_TwoEventsSameEmployeeWorkDate() && $allPassed;
        $allPassed = $this->testScenario5_ApprovalFailsDuringSimulatedError() && $allPassed;

        echo "\n====================================================================\n";
        if ($allPassed) {
            echo "   OVERALL RESULT: ALL 5 REVIEW CONCURRENCY SCENARIOS PASSED (100% SUCCESS)\n";
        } else {
            echo "   OVERALL RESULT: SOME SCENARIOS FAILED\n";
        }
        echo "====================================================================\n";

        return $allPassed;
    }

    private function spawnWorkers(array $payloads, float $delaySeconds = 0.5): array
    {
        $barrierTime = microtime(true) + $delaySeconds;
        $processes = [];
        $pipes = [];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        foreach ($payloads as $i => $payload) {
            $encoded = base64_encode(json_encode($payload));
            $cmd = "\"{$this->phpBin}\" \"{$this->workerScript}\" \"{$encoded}\" {$barrierTime}";
            $proc = proc_open($cmd, $descriptors, $procPipes);
            if (is_resource($proc)) {
                $processes[$i] = $proc;
                $pipes[$i]     = $procPipes;
            } else {
                throw new \RuntimeException("Failed to spawn review worker {$i}");
            }
        }

        $results = [];
        foreach ($processes as $i => $proc) {
            $stdout = stream_get_contents($pipes[$i][1]);
            $stderr = stream_get_contents($pipes[$i][2]);
            fclose($pipes[$i][0]);
            fclose($pipes[$i][1]);
            fclose($pipes[$i][2]);
            $exitCode = proc_close($proc);

            $data = json_decode($stdout, true);
            $results[$i] = [
                'exit_code' => $exitCode,
                'stdout'    => $stdout,
                'stderr'    => $stderr,
                'data'      => $data,
            ];
        }

        return $results;
    }

    // Scenario 1: Two Admin processes approve the same held event simultaneously
    public function testScenario1_TwoAdminsApproveSimultaneously(): bool
    {
        echo "--- Scenario 1: Two Admin Processes Approve Same Event Simultaneously ---\n";
        $eventId = $this->createHeldEvent('2026-11-03 08:30:00');

        $p1 = [
            'action'         => 'APPROVE',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId1,
            'admin_username' => 'admin_concur_1',
            'reason'         => 'Approved by Admin 1 via concurrent process',
        ];
        $p2 = [
            'action'         => 'APPROVE',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId2,
            'admin_username' => 'admin_concur_2',
            'reason'         => 'Approved by Admin 2 via concurrent process',
        ];

        $workers = $this->spawnWorkers([$p1, $p2]);

        $statuses = array_map(function($w) {
            return $w['data']['result']['status'] ?? 'UNKNOWN';
        }, $workers);

        echo "  Processes: PID {$workers[0]['data']['pid']} vs PID {$workers[1]['data']['pid']}\n";
        echo "  MariaDB CIDs: {$workers[0]['data']['connection_id']} vs {$workers[1]['data']['connection_id']}\n";
        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        $approvedCount = count(array_filter($statuses, fn($s) => $s === 'APPROVED'));
        $duplicateCount = count(array_filter($statuses, fn($s) => $s === 'DUPLICATE_DECISION'));

        // DB Assertions
        $decisions = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getResultArray();
        $eventAfter = $this->db->table('attendance_events')->where('id', $eventId)->get()->getRowArray();

        $pass = ($approvedCount === 1) 
             && ($duplicateCount === 1) 
             && (count($decisions) === 1)
             && ($eventAfter['processing_status'] === 'REVIEW_REQUIRED'); // Ledger event immutable!

        echo "  [Decisions in DB: " . count($decisions) . " (Expected 1) | Original Event Status: {$eventAfter['processing_status']} (Immutable)]\n";
        echo "  ==> " . ($pass ? "PASS" : "FAIL") . "\n\n";

        return $pass;
    }

    // Scenario 2: Two Admin processes reject the same held event simultaneously
    public function testScenario2_TwoAdminsRejectSimultaneously(): bool
    {
        echo "--- Scenario 2: Two Admin Processes Reject Same Event Simultaneously ---\n";
        $eventId = $this->createHeldEvent('2026-11-03 08:35:00');

        $recCountBefore = (int) $this->db->query("SELECT COUNT(*) as cnt FROM attendance_records WHERE employee_id = '{$this->testEmpId}'")->getRow()->cnt;

        $p1 = [
            'action'         => 'REJECT',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId1,
            'admin_username' => 'admin_concur_1',
            'reason'         => 'Rejected by Admin 1 via concurrent process',
        ];
        $p2 = [
            'action'         => 'REJECT',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId2,
            'admin_username' => 'admin_concur_2',
            'reason'         => 'Rejected by Admin 2 via concurrent process',
        ];

        $workers = $this->spawnWorkers([$p1, $p2]);

        $statuses = array_map(function($w) {
            return $w['data']['result']['status'] ?? 'UNKNOWN';
        }, $workers);

        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        $rejectedCount = count(array_filter($statuses, fn($s) => $s === 'REJECTED'));
        $duplicateCount = count(array_filter($statuses, fn($s) => $s === 'DUPLICATE_DECISION'));

        $decisions = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getResultArray();
        $recCountAfter = (int) $this->db->query("SELECT COUNT(*) as cnt FROM attendance_records WHERE employee_id = '{$this->testEmpId}'")->getRow()->cnt;
        $eventAfter = $this->db->table('attendance_events')->where('id', $eventId)->get()->getRowArray();

        $pass = ($rejectedCount === 1) 
             && ($duplicateCount === 1) 
             && (count($decisions) === 1) 
             && ($decisions[0]['decision'] === 'REJECTED')
             && ($recCountBefore === $recCountAfter) // Records untouched!
             && ($eventAfter['processing_status'] === 'REVIEW_REQUIRED'); // Event immutable!

        echo "  [Decisions in DB: " . count($decisions) . " (Expected 1) | Records Modified: " . ($recCountAfter - $recCountBefore) . " (Expected 0)]\n";
        echo "  ==> " . ($pass ? "PASS" : "FAIL") . "\n\n";

        return $pass;
    }

    // Scenario 3: One Admin approves while another rejects the same event simultaneously
    public function testScenario3_OneApproveOneRejectSimultaneously(): bool
    {
        echo "--- Scenario 3: One Approve vs One Reject Simultaneously on Same Event ---\n";
        $eventId = $this->createHeldEvent('2026-11-03 08:40:00');

        $p1 = [
            'action'         => 'APPROVE',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId1,
            'admin_username' => 'admin_concur_1',
            'reason'         => 'Simultaneous approve attempt',
        ];
        $p2 = [
            'action'         => 'REJECT',
            'event_id'       => $eventId,
            'admin_user_id'  => $this->adminId2,
            'admin_username' => 'admin_concur_2',
            'reason'         => 'Simultaneous reject attempt',
        ];

        $workers = $this->spawnWorkers([$p1, $p2]);

        $statuses = array_map(function($w) {
            return $w['data']['result']['status'] ?? 'UNKNOWN';
        }, $workers);

        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        $decisions = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getResultArray();
        $eventAfter = $this->db->table('attendance_events')->where('id', $eventId)->get()->getRowArray();

        $oneWon = (in_array('APPROVED', $statuses, true) || in_array('REJECTED', $statuses, true));
        $oneBlocked = in_array('DUPLICATE_DECISION', $statuses, true);

        $pass = $oneWon && $oneBlocked && (count($decisions) === 1) && ($eventAfter['processing_status'] === 'REVIEW_REQUIRED');

        echo "  [Winner Decision: {$decisions[0]['decision']} | Decisions in DB: " . count($decisions) . " (Expected 1)]\n";
        echo "  ==> " . ($pass ? "PASS" : "FAIL") . "\n\n";

        return $pass;
    }

    // Scenario 4: Two Admin processes attempt to approve two distinct events for the same employee and work date
    public function testScenario4_TwoEventsSameEmployeeWorkDate(): bool
    {
        echo "--- Scenario 4: Two Distinct Events for Same Employee Approved Simultaneously ---\n";
        $now = date('Y-m-d H:i:s');
        $freshEmpId = EmployeeModel::generateUuidV4();
        $this->db->table('employees')->insert([
            'id'          => $freshEmpId,
            'employee_id' => 'CONCUR-EMP-' . bin2hex(random_bytes(3)),
            'full_name'   => 'Dual Event Concurrency Staff',
            'department'  => 'Math',
            'status'      => 'ACTIVE',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);

        $randomDay = rand(10, 28);
        $workDateStr = "2026-11-{$randomDay}";
        $event1Id = $this->createHeldEvent("{$workDateStr} 08:00:00", $freshEmpId);
        $event2Id = $this->createHeldEvent("{$workDateStr} 08:15:00", $freshEmpId);

        $p1 = [
            'action'         => 'APPROVE',
            'event_id'       => $event1Id,
            'admin_user_id'  => $this->adminId1,
            'admin_username' => 'admin_concur_1',
            'reason'         => 'Approve first punch',
        ];
        $p2 = [
            'action'         => 'APPROVE',
            'event_id'       => $event2Id,
            'admin_user_id'  => $this->adminId2,
            'admin_username' => 'admin_concur_2',
            'reason'         => 'Approve second punch',
        ];

        $workers = $this->spawnWorkers([$p1, $p2]);

        $statuses = array_map(function($w) {
            return $w['data']['result']['status'] ?? 'UNKNOWN';
        }, $workers);

        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        // Both review decisions should be APPROVED
        $bothApproved = ($statuses[0] === 'APPROVED' && $statuses[1] === 'APPROVED');

        // Aggregate records for this employee on this work date must be EXACTLY 1 row (no duplicate summaries)
        $summaries = $this->db->table('attendance_records')
            ->where('employee_id', $freshEmpId)
            ->where('work_date', $workDateStr)
            ->get()
            ->getResultArray();

        $summaryCount = count($summaries);
        $earliestCheckIn = $summaries[0]['check_in_time'] ?? null;

        $pass = $bothApproved && ($summaryCount === 1);

        echo "  [Both Decisions Created: " . ($bothApproved ? "YES" : "NO") . " | Daily Aggregate Count: {$summaryCount} (Expected 1) | Check-in: {$earliestCheckIn}]\n";
        echo "  ==> " . ($pass ? "PASS" : "FAIL") . "\n\n";

        return $pass;
    }

    // Scenario 5: Approval fails during a simulated database error
    public function testScenario5_ApprovalFailsDuringSimulatedError(): bool
    {
        echo "--- Scenario 5: Approval Fails During Simulated Error (Rollback Integrity) ---\n";
        $eventId = $this->createHeldEvent('2026-11-03 08:45:00');

        $p1 = [
            'action'             => 'APPROVE',
            'event_id'           => $eventId,
            'admin_user_id'      => $this->adminId1,
            'admin_username'     => 'admin_concur_1',
            'reason'             => 'Simulated failure attempt',
            'simulate_db_error'  => true,
        ];

        $workers = $this->spawnWorkers([$p1]);
        $status = $workers[0]['data']['result']['status'] ?? 'UNKNOWN';

        echo "  Worker Status: {$status}\n";

        // DB Assertions: No decision row, original event untouched
        $decisions = $this->db->table('attendance_review_decisions')->where('event_id', $eventId)->get()->getResultArray();
        $eventAfter = $this->db->table('attendance_events')->where('id', $eventId)->get()->getRowArray();

        $pass = ($status === 'ERROR') 
             && (count($decisions) === 0) 
             && ($eventAfter['processing_status'] === 'REVIEW_REQUIRED');

        echo "  [Decisions in DB: " . count($decisions) . " (Expected 0) | Original Status: {$eventAfter['processing_status']} (Untouched)]\n";
        echo "  ==> " . ($pass ? "PASS" : "FAIL") . "\n\n";

        return $pass;
    }
}

$runner = new MultiProcessReviewConcurrencyRunner();
$success = $runner->runAll();
exit($success ? 0 : 1);
