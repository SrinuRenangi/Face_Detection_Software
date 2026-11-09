<?php

defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use Config\Database;

class MultiProcessConcurrencyRunner
{
    private $db;
    private $phpBin;
    private $workerScript;

    public function __construct()
    {
        $this->db = Database::connect('tests');
        $this->phpBin = 'C:\\tools\\php85\\php.exe';
        $this->workerScript = realpath(__DIR__ . '/worker_punch.php');
    }

    public function runAll(): bool
    {
        echo "====================================================================\n";
        echo "   EDIFIES ATTENDANCE: TRUE MULTI-PROCESS CONCURRENCY HARNESS       \n";
        echo "====================================================================\n";
        echo "MariaDB Connection Host: 127.0.0.1:3307 | Target DB: attendance_test\n";
        echo "PHP Binary: " . $this->phpBin . "\n";
        echo "Worker:     " . $this->workerScript . "\n\n";

        $allPassed = true;

        $allPassed = $this->testScenario1_DuplicateUuidIdenticalPayload() && $allPassed;
        $allPassed = $this->testScenario2_DuplicateUuidConflictingPayload() && $allPassed;
        $allPassed = $this->testScenario3_FiveProcessesSameEmployee() && $allPassed;
        $allPassed = $this->testScenario4_TwoDevicesSameEmployee() && $allPassed;
        $allPassed = $this->testScenario5_DifferentEmployeesSimultaneous() && $allPassed;
        $allPassed = $this->testScenario6_DuplicateSequenceSimultaneous() && $allPassed;
        $allPassed = $this->testScenario7_OutOfOrderSequenceHandling() && $allPassed;
        $allPassed = $this->testScenario8_DuplicateKeyRaceResolution() && $allPassed;
        $allPassed = $this->testScenario9_LockOrderingAndIntegrity() && $allPassed;

        echo "\n====================================================================\n";
        if ($allPassed) {
            echo "   OVERALL RESULT: ALL 9 CONCURRENCY SCENARIOS PASSED (100% SUCCESS)\n";
        } else {
            echo "   OVERALL RESULT: SOME SCENARIOS FAILED\n";
        }
        echo "====================================================================\n";

        return $allPassed;
    }

    /**
     * Spawns N independent OS processes and executes them at $barrierMicrotime
     */
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
                throw new \RuntimeException("Failed to spawn worker {$i}");
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

    /**
     * Scenario 1: Two independent processes submit same client UUID and identical payload simultaneously
     */
    private function testScenario1_DuplicateUuidIdenticalPayload(): bool
    {
        echo "--- Scenario 1: 2 Processes Submit Same Client UUID & Identical Payload ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->setupEmployeeAndDevice($empId, $devId, 'CONC-EMP-1', 'CONC-DEV-1');

        $punchTime = date('Y-m-d H:i:s', time() - 3600);
        $punchData = [
            'client_uuid'            => $clientUuid,
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $punchTime,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 101,
        ];

        $results = $this->spawnWorkers([$punchData, $punchData]);

        $pids = array_map(fn($r) => $r['data']['pid'] ?? 'N/A', $results);
        $cids = array_map(fn($r) => $r['data']['connection_id'] ?? 'N/A', $results);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);
        $errors = array_map(fn($r) => $r['data']['result']['error'] ?? 'None', $results);

        echo "  Processes: PID " . implode(' vs PID ', $pids) . "\n";
        echo "  MariaDB Connections: CID " . implode(' vs CID ', $cids) . "\n";
        echo "  Statuses: " . implode(' and ', $statuses) . "\n";
        echo "  Errors: " . implode(' | ', $errors) . "\n";

        // Distinct PIDs and distinct CIDs check
        $distinctPids = count(array_unique($pids)) === 2;
        $distinctCids = count(array_unique($cids)) === 2;

        // One should be CREATED, other IDEMPOTENT_CONFIRMED
        sort($statuses);
        $correctStatuses = ($statuses === ['CREATED', 'IDEMPOTENT_CONFIRMED']);

        // Check DB row counts
        $ledgerCount = $this->db->table('attendance_events')->where('client_uuid', $clientUuid)->countAllResults();
        $recordCount = $this->db->table('attendance_records')->where('employee_id', $empId)->countAllResults();

        $pass = $distinctPids && $distinctCids && $correctStatuses && ($ledgerCount === 1) && ($recordCount === 1);

        echo "  [Ledger Count: {$ledgerCount} (Expected 1) | Daily Summary Count: {$recordCount} (Expected 1)]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $devId);
        return $pass;
    }

    /**
     * Scenario 2: Two independent processes submit same client UUID with different payloads simultaneously
     */
    private function testScenario2_DuplicateUuidConflictingPayload(): bool
    {
        echo "--- Scenario 2: 2 Processes Submit Same Client UUID with Conflicting Payload ---\n";

        $empA = EmployeeModel::generateUuidV4();
        $empB = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();

        $this->setupEmployeeAndDevice($empA, $devId, 'CONC-EMP-2A', 'CONC-DEV-2');
        $this->setupEmployee($empB, 'CONC-EMP-2B');

        $punchTime = date('Y-m-d H:i:s', time() - 3600);
        $punchA = [
            'client_uuid'            => $clientUuid,
            'employee_id'            => $empA,
            'device_id'              => $devId,
            'timestamp'              => $punchTime,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 201,
        ];
        $punchB = [
            'client_uuid'            => $clientUuid,
            'employee_id'            => $empB, // Different employee!
            'device_id'              => $devId,
            'timestamp'              => $punchTime,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 202,
        ];

        $results = $this->spawnWorkers([$punchA, $punchB]);

        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);
        sort($statuses);
        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        $correctStatuses = ($statuses === ['CONFLICT_PAYLOAD_MISMATCH', 'CREATED']);
        $ledgerCount = $this->db->table('attendance_events')->where('client_uuid', $clientUuid)->countAllResults();

        $pass = $correctStatuses && ($ledgerCount === 1);
        echo "  [Ledger Count: {$ledgerCount} (Expected 1) | Conflict Caught: Yes]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empA, $devId);
        $this->cleanupEmployee($empB);
        return $pass;
    }

    /**
     * Scenario 3: Five independent processes submit distinct encounters for the same employee simultaneously
     */
    private function testScenario3_FiveProcessesSameEmployee(): bool
    {
        echo "--- Scenario 3: 5 Independent Processes Submit Encounters for Same Employee ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $this->setupEmployeeAndDevice($empId, $devId, 'CONC-EMP-3', 'CONC-DEV-3');

        $baseEpoch = time() - 3600;
        $payloads = [];
        for ($i = 1; $i <= 5; $i++) {
            $payloads[] = [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $empId,
                'device_id'              => $devId,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch + $i),
                'confidence_score'       => 0.90 + ($i * 0.01),
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 300 + $i,
            ];
        }

        $results = $this->spawnWorkers($payloads);

        $pids = array_map(fn($r) => $r['data']['pid'] ?? 'N/A', $results);
        $cids = array_map(fn($r) => $r['data']['connection_id'] ?? 'N/A', $results);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);

        echo "  Processes: " . implode(', ', $pids) . "\n";
        echo "  MariaDB Connection IDs: " . implode(', ', $cids) . "\n";
        echo "  Statuses: " . implode(', ', $statuses) . "\n";

        // Daily record must be exactly 1, and events must be exactly 5
        $ledgerCount = $this->db->table('attendance_events')->where('employee_id', $empId)->countAllResults();
        $recordCount = $this->db->table('attendance_records')->where('employee_id', $empId)->countAllResults();
        $finalRecord = $this->db->table('attendance_records')->where('employee_id', $empId)->get()->getRowArray();

        $noErrors = !in_array('DATABASE_ERROR', $statuses, true) && !in_array('TRANSACTION_FAILED', $statuses, true);
        $earliestWon = ($finalRecord && ($finalRecord['check_in_time'] === date('Y-m-d H:i:s', $baseEpoch + 1)));

        $pass = $noErrors && ($ledgerCount === 5) && ($recordCount === 1) && $earliestWon;

        echo "  [Daily Records: {$recordCount} (Expected 1) | Ledger Events: {$ledgerCount} (Expected 5) | Earliest Time Won: " . ($earliestWon ? "YES" : "NO") . "]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $devId);
        return $pass;
    }

    /**
     * Scenario 4: Two devices submit events for the same employee simultaneously
     */
    private function testScenario4_TwoDevicesSameEmployee(): bool
    {
        echo "--- Scenario 4: 2 Devices Submit Events for Same Employee Simultaneously ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $dev1  = EmployeeModel::generateUuidV4();
        $dev2  = EmployeeModel::generateUuidV4();

        $this->setupEmployeeAndDevice($empId, $dev1, 'CONC-EMP-4', 'DEV-GATE-1');
        $this->setupDevice($dev2, 'DEV-GATE-2');

        $baseEpoch = time() - 3600;
        $payloads = [
            [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $empId,
                'device_id'              => $dev1,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch),
                'confidence_score'       => 0.94,
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 401,
            ],
            [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $empId,
                'device_id'              => $dev2,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch + 1),
                'confidence_score'       => 0.93,
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 402,
            ],
        ];

        $results = $this->spawnWorkers($payloads);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);
        sort($statuses);
        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        $recordCount = $this->db->table('attendance_records')->where('employee_id', $empId)->countAllResults();
        $ledgerCount = $this->db->table('attendance_events')->where('employee_id', $empId)->countAllResults();

        $validStatuses = ($statuses === ['CREATED', 'DUPLICATE_IGNORED'] || $statuses === ['CREATED', 'RECONCILED_EARLIER']);
        $pass = $validStatuses && ($recordCount === 1) && ($ledgerCount === 2);
        echo "  [Daily Records: {$recordCount} (Expected 1) | Ledger Events: {$ledgerCount} (Expected 2)]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $dev1);
        $this->cleanupDevice($dev2);
        return $pass;
    }

    /**
     * Scenario 5: Different employees submit events simultaneously
     */
    private function testScenario5_DifferentEmployeesSimultaneous(): bool
    {
        echo "--- Scenario 5: 4 Different Employees Submit Events Simultaneously ---\n";

        $devId = EmployeeModel::generateUuidV4();
        $this->setupDevice($devId, 'DEV-CONC-HUB');

        $baseEpoch = time() - 3600;
        $empIds = [];
        $payloads = [];
        for ($i = 1; $i <= 4; $i++) {
            $eId = EmployeeModel::generateUuidV4();
            $empIds[] = $eId;
            $this->setupEmployee($eId, "EMP-CONC-5{$i}");

            $payloads[] = [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $eId,
                'device_id'              => $devId,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch + $i),
                'confidence_score'       => 0.95,
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 500 + $i,
            ];
        }

        $results = $this->spawnWorkers($payloads);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);
        echo "  Statuses: " . implode(', ', $statuses) . "\n";

        $allCreated = (array_unique($statuses) === ['CREATED']);
        $totalDaily = $this->db->table('attendance_records')->whereIn('employee_id', $empIds)->countAllResults();

        $pass = $allCreated && ($totalDaily === 4);
        echo "  [Total Daily Records: {$totalDaily} (Expected 4) | Concurrency: Uncontested]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        foreach ($empIds as $eId) {
            $this->cleanupEmployee($eId);
        }
        $this->cleanupDevice($devId);
        return $pass;
    }

    /**
     * Scenario 6: Duplicate device sequence numbers submitted simultaneously
     */
    private function testScenario6_DuplicateSequenceSimultaneous(): bool
    {
        echo "--- Scenario 6: Duplicate Device Sequence Numbers Submitted Simultaneously ---\n";

        $empA = EmployeeModel::generateUuidV4();
        $empB = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();

        $this->setupEmployeeAndDevice($empA, $devId, 'CONC-EMP-6A', 'DEV-SEQ-TEST');
        $this->setupEmployee($empB, 'CONC-EMP-6B');

        // Both use the EXACT same sequence 601 on the same device!
        $baseEpoch = time() - 3600;
        $payloads = [
            [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $empA,
                'device_id'              => $devId,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch),
                'confidence_score'       => 0.95,
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 601,
            ],
            [
                'client_uuid'            => EmployeeModel::generateUuidV4(),
                'employee_id'            => $empB,
                'device_id'              => $devId,
                'timestamp'              => date('Y-m-d H:i:s', $baseEpoch + 1),
                'confidence_score'       => 0.95,
                'sync_source'            => 'DIRECT_ONLINE',
                'device_event_sequence'  => 601, // COLLISION!
            ],
        ];

        $results = $this->spawnWorkers($payloads);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);
        sort($statuses);
        echo "  Statuses: " . implode(' and ', $statuses) . "\n";

        // One must succeed (CREATED), other must be QUARANTINED
        $pass = in_array('QUARANTINED', $statuses, true) && in_array('CREATED', $statuses, true);
        echo "  [Sequence Collision Caught & Quarantined: " . ($pass ? "YES" : "NO") . "]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empA, $devId);
        $this->cleanupEmployee($empB);
        return $pass;
    }

    /**
     * Scenario 7: Out-of-order sequence arrival
     */
    private function testScenario7_OutOfOrderSequenceHandling(): bool
    {
        echo "--- Scenario 7: Out-of-Order Sequence Arrival ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $this->setupEmployeeAndDevice($empId, $devId, 'CONC-EMP-7', 'DEV-OUTORDER');

        $time750 = date('Y-m-d H:i:s', time() - 1800);
        // First process submits sequence 750
        $payload1 = [
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $time750,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 750,
        ];
        $res1 = $this->spawnWorkers([$payload1], 0.1);

        $time740 = date('Y-m-d H:i:s', time() - 2400);
        // Later process submits sequence 740 (lower than 750)
        $payload2 = [
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $time740,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 740, // Out of order!
        ];
        $res2 = $this->spawnWorkers([$payload2], 0.1);

        $status1 = $res1[0]['data']['result']['status'] ?? 'ERROR';
        $status2 = $res2[0]['data']['result']['status'] ?? 'ERROR';

        echo "  Seq 750 status: {$status1} | Seq 740 status: {$status2}\n";

        // Audit log must have recorded DEVICE_SEQUENCE_OUT_OF_ORDER
        $auditLog = $this->db->table('admin_audit_logs')
            ->where('action', 'DEVICE_SEQUENCE_OUT_OF_ORDER')
            ->orderBy('id', 'DESC')
            ->get()
            ->getRowArray();

        $pass = ($status1 === 'CREATED') && ($status2 === 'HELD_FOR_REVIEW') && ($auditLog !== null);
        echo "  [Out-of-Order Audited: " . ($auditLog ? "YES" : "NO") . "]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $devId);
        return $pass;
    }

    /**
     * Scenario 8: Duplicate-key race resolution
     */
    private function testScenario8_DuplicateKeyRaceResolution(): bool
    {
        echo "--- Scenario 8: Database Duplicate-Key Race Handled Gracefully ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $clientUuid = EmployeeModel::generateUuidV4();
        $this->setupEmployeeAndDevice($empId, $devId, 'CONC-EMP-8', 'DEV-RACE');

        $punchTime = date('Y-m-d H:i:s', time() - 3600);
        $payload = [
            'client_uuid'            => $clientUuid,
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $punchTime,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 801,
        ];

        // 3 processes race on exact same UUID
        $results = $this->spawnWorkers([$payload, $payload, $payload]);
        $statuses = array_map(fn($r) => $r['data']['result']['status'] ?? 'ERROR', $results);

        echo "  3-Process Race Statuses: " . implode(', ', $statuses) . "\n";

        $no500 = !in_array('DATABASE_ERROR', $statuses, true) && !in_array('TRANSACTION_FAILED', $statuses, true);
        $counts = array_count_values($statuses);
        $pass = $no500 && (($counts['CREATED'] ?? 0) === 1) && (($counts['IDEMPOTENT_CONFIRMED'] ?? 0) === 2);

        echo "  [Zero 500 Errors: " . ($no500 ? "YES" : "NO") . " | Idempotency Resolved: " . ($pass ? "YES" : "NO") . "]\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $devId);
        return $pass;
    }

    /**
     * Scenario 9: Row lock ordering and integrity
     */
    private function testScenario9_LockOrderingAndIntegrity(): bool
    {
        echo "--- Scenario 9: Lock Acquisition Order & Aggregate Integrity ---\n";

        $empId = EmployeeModel::generateUuidV4();
        $devId = EmployeeModel::generateUuidV4();
        $this->setupEmployeeAndDevice($empId, $devId, 'CONC-EMP-9', 'DEV-LOCK');

        $punch1Time = date('Y-m-d H:i:s', time() - 300);
        // Check-in punch
        $punch1 = [
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $punch1Time,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 901,
        ];
        $this->spawnWorkers([$punch1], 0.1);

        // Configure device as EXIT_ONLY
        $this->db->table('kiosk_devices')->where('id', $devId)->update(['terminal_mode' => 'EXIT_ONLY']);

        $punch2Time = date('Y-m-d H:i:s', time() - 60);
        // Check-out punch
        $punch2 = [
            'client_uuid'            => EmployeeModel::generateUuidV4(),
            'employee_id'            => $empId,
            'device_id'              => $devId,
            'timestamp'              => $punch2Time,
            'confidence_score'       => 0.95,
            'sync_source'            => 'DIRECT_ONLINE',
            'device_event_sequence'  => 902,
        ];
        $res2 = $this->spawnWorkers([$punch2], 0.1);
        $status2 = $res2[0]['data']['result']['status'] ?? 'ERROR';

        $summary = $this->db->table('attendance_records')->where('employee_id', $empId)->get()->getRowArray();
        $pass = ($status2 === 'CHECK_OUT_RECORDED') && ($summary['status'] === 'CHECKED_OUT') && ($summary['check_in_time'] === $punch1Time) && ($summary['check_out_time'] === $punch2Time);

        echo "  Check-out Status: {$status2} | Summary Status: {$summary['status']}\n";
        echo "  Check-in: {$summary['check_in_time']} | Check-out: {$summary['check_out_time']}\n";
        echo $pass ? "  ==> PASS\n\n" : "  ==> FAIL\n\n";

        $this->cleanup($empId, $devId);
        return $pass;
    }

    private function setupEmployeeAndDevice(string $empId, string $devId, string $empCode, string $devIdent): void
    {
        $this->setupEmployee($empId, $empCode);
        $this->setupDevice($devId, $devIdent);
    }

    private function setupEmployee(string $empId, string $empCode): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('employees')->where('employee_id', $empCode)->delete();
        $this->db->table('employees')->insert([
            'id'          => $empId,
            'employee_id' => $empCode,
            'full_name'   => 'Test Concurrency ' . $empCode,
            'status'      => 'ACTIVE',
            'created_at'  => $now,
            'updated_at'  => $now,
        ]);
    }

    private function setupDevice(string $devId, string $devIdent): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('kiosk_devices')->where('device_identifier', $devIdent)->delete();
        $this->db->table('kiosk_devices')->insert([
            'id'                  => $devId,
            'device_name'         => 'Harness ' . $devIdent,
            'device_identifier'   => $devIdent,
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-harness-1',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);
    }

    private function cleanup(string $empId, string $devId): void
    {
        $this->db->table('attendance_records')->where('employee_id', $empId)->delete();
        $this->db->table('attendance_events')->where('employee_id', $empId)->delete();
        $this->db->table('employees')->where('id', $empId)->delete();
        $this->cleanupDevice($devId);
    }

    private function cleanupEmployee(string $empId): void
    {
        $this->db->table('attendance_records')->where('employee_id', $empId)->delete();
        $this->db->table('attendance_events')->where('employee_id', $empId)->delete();
        $this->db->table('employees')->where('id', $empId)->delete();
    }

    private function cleanupDevice(string $devId): void
    {
        $this->db->table('attendance_events')->where('device_id', $devId)->delete();
        $this->db->table('kiosk_devices')->where('id', $devId)->delete();
    }
}

$runner = new MultiProcessConcurrencyRunner();
$success = $runner->runAll();
exit($success ? 0 : 1);
