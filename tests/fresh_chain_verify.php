<?php

defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

echo "=== FRESH MIGRATION CHAIN VERIFICATION (Stage 3.2.4-R1) ===" . PHP_EOL;

// 1. Create fresh database
$rawDb = new mysqli('127.0.0.1', 'root', '', '', 3307);
if ($rawDb->connect_error) {
    echo "ERROR: Failed to connect to MariaDB: " . $rawDb->connect_error . PHP_EOL;
    exit(1);
}

echo "1. Recreating empty database 'attendance_verify_chain'..." . PHP_EOL;
$rawDb->query("DROP DATABASE IF EXISTS `attendance_verify_chain`");
$rawDb->query("CREATE DATABASE `attendance_verify_chain` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$rawDb->close();

// 2. Connect via CodeIgniter verify_chain group
$db = Config\Database::connect('verify_chain');
$tablesBefore = $db->listTables();
echo "   Initial tables: " . count($tablesBefore) . PHP_EOL;
if (count($tablesBefore) !== 0) {
    echo "ERROR: Database is not empty!" . PHP_EOL;
    exit(1);
}

// 3. Run migration runner
$runner = Config\Services::migrations(null, $db);
$runner->setNamespace('App');
echo "2. Running all migrations (1 to 8) on verify_chain..." . PHP_EOL;
$success = $runner->latest('verify_chain');

if (!$success) {
    echo "ERROR: Migrations failed!" . PHP_EOL;
    foreach ($runner->getCliMessages() as $msg) {
        echo $msg . PHP_EOL;
    }
    exit(1);
}
echo "   All migrations (1 through 8) executed successfully!" . PHP_EOL;

// 4. Verify indexes on attendance_events
echo "3. Inspecting attendance_events indexes on fresh database..." . PHP_EOL;
$indexRows = $db->query("SHOW INDEX FROM attendance_events")->getResultArray();

$expectedIndexes = [
    'PRIMARY'                   => ['unique' => true,  'cols' => ['id']],
    'uk_att_events_client_uuid' => ['unique' => true,  'cols' => ['client_uuid']],
    'uk_att_events_device_seq'  => ['unique' => true,  'cols' => ['device_id', 'device_epoch_id', 'device_event_sequence']],
    'idx_att_events_emp_time'   => ['unique' => false, 'cols' => ['employee_id', 'client_recorded_at']],
    'idx_att_events_status'     => ['unique' => false, 'cols' => ['processing_status']],
    'idx_att_events_received'   => ['unique' => false, 'cols' => ['server_received_at']],
];

$actualIndexes = [];
foreach ($indexRows as $row) {
    $name = $row['Key_name'];
    if (!isset($actualIndexes[$name])) {
        $actualIndexes[$name] = [
            'unique' => ((int)$row['Non_unique']) === 0,
            'cols'   => [],
        ];
    }
    $actualIndexes[$name]['cols'][] = $row['Column_name'];
}

$allMatched = true;
foreach ($expectedIndexes as $name => $exp) {
    if (!isset($actualIndexes[$name])) {
        echo "   [FAIL] Missing index: {$name}" . PHP_EOL;
        $allMatched = false;
        continue;
    }
    $act = $actualIndexes[$name];
    $uniqueMatch = ($exp['unique'] === $act['unique']);
    $colsMatch = ($exp['cols'] === $act['cols']);

    if ($uniqueMatch && $colsMatch) {
        $uStr = $act['unique'] ? 'UNIQUE' : 'NON-UNIQUE';
        echo "   [OK] Index {$name} ({$uStr}) cols: " . implode(', ', $act['cols']) . PHP_EOL;
    } else {
        echo "   [FAIL] Index {$name} mismatch! Expected unique=" . ($exp['unique']?'true':'false') . ", got " . ($act['unique']?'true':'false') . PHP_EOL;
        $allMatched = false;
    }
}

// 5. Verify Foreign Key Rules
echo "4. Inspecting attendance_events foreign keys..." . PHP_EOL;
$fkRows = $db->query("
    SELECT CONSTRAINT_NAME, DELETE_RULE, UPDATE_RULE
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = 'attendance_verify_chain' AND TABLE_NAME = 'attendance_events'
")->getResultArray();

foreach ($fkRows as $fk) {
    $dRule = strtoupper($fk['DELETE_RULE']);
    $uRule = strtoupper($fk['UPDATE_RULE']);
    if ($dRule === 'RESTRICT') {
        echo "   [OK] FK {$fk['CONSTRAINT_NAME']}: ON DELETE {$dRule}, ON UPDATE {$uRule}" . PHP_EOL;
    } else {
        echo "   [FAIL] FK {$fk['CONSTRAINT_NAME']}: ON DELETE {$dRule} (Expected RESTRICT!)" . PHP_EOL;
        $allMatched = false;
    }
}

// 6. Inspect kiosk_time_anchors for browser_session_hash
echo "5. Inspecting kiosk_time_anchors.browser_session_hash (Migration 7)..." . PHP_EOL;
$anchorCols = $db->getFieldData('kiosk_time_anchors');
$foundHashCol = false;
foreach ($anchorCols as $col) {
    if ($col->name === 'browser_session_hash') {
        $foundHashCol = true;
        echo "   [OK] Column browser_session_hash present, type: {$col->type}, max_length: {$col->max_length}" . PHP_EOL;
        break;
    }
}
if (!$foundHashCol) {
    echo "   [FAIL] Column browser_session_hash missing on kiosk_time_anchors!" . PHP_EOL;
    $allMatched = false;
}

// 7. Inspect attendance_review_decisions table & Foreign Keys (Migrations 7 & 8)
echo "6. Inspecting attendance_review_decisions (Migrations 7 & 8)..." . PHP_EOL;
$revTables = $db->query("SHOW TABLES LIKE 'attendance_review_decisions'")->getResultArray();
if (empty($revTables)) {
    echo "   [FAIL] Table attendance_review_decisions missing!" . PHP_EOL;
    $allMatched = false;
} else {
    echo "   [OK] Table attendance_review_decisions exists." . PHP_EOL;
    $revCols = $db->getFieldNames('attendance_review_decisions');
    echo "   [OK] Columns: " . implode(', ', $revCols) . PHP_EOL;

    // Check FKs on attendance_review_decisions
    $ardFks = $db->query("
        SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME, DELETE_RULE, UPDATE_RULE
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = 'attendance_verify_chain' AND TABLE_NAME = 'attendance_review_decisions'
    ")->getResultArray();

    $expectedArdFks = [
        'attendance_events'  => 'RESTRICT',
        'admin_users'        => 'RESTRICT',
        'attendance_records' => 'RESTRICT',
    ];

    foreach ($ardFks as $fk) {
        $refTable = $fk['REFERENCED_TABLE_NAME'];
        $dRule = strtoupper($fk['DELETE_RULE']);
        if (isset($expectedArdFks[$refTable]) && $expectedArdFks[$refTable] === $dRule) {
            echo "   [OK] ARD FK {$fk['CONSTRAINT_NAME']} -> {$refTable}: ON DELETE {$dRule}" . PHP_EOL;
            unset($expectedArdFks[$refTable]);
        }
    }

    if (!empty($expectedArdFks)) {
        echo "   [FAIL] Missing or invalid ARD FKs for referenced tables: " . implode(', ', array_keys($expectedArdFks)) . PHP_EOL;
        $allMatched = false;
    }
}

if (!$allMatched) {
    echo "VERIFICATION FAILED!" . PHP_EOL;
    exit(1);
}

echo "=== FRESH MIGRATION CHAIN VERIFICATION: 100% SUCCESS ===" . PHP_EOL;
exit(0);

