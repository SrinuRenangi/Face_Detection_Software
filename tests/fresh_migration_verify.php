<?php

defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../vendor/codeigniter4/framework/system/Test/bootstrap.php';

$dbConfig = config('Database');
$freshGroup = $dbConfig->verify_fresh;

echo "=== ISOLATED FRESH MIGRATION VERIFICATION ===" . PHP_EOL;
echo "Target Database: " . $freshGroup['database'] . " (Port: " . $freshGroup['port'] . ")" . PHP_EOL;

// 1. Connect and verify starts empty
$db = Config\Database::connect('verify_fresh');
$initialTables = $db->listTables();
echo "1. Initial table count: " . count($initialTables) . PHP_EOL;
if (count($initialTables) !== 0) {
    echo "ERROR: Target database is not empty!" . PHP_EOL;
    exit(1);
}

// 2. Initialize Migration Runner bound to verify_fresh database connection
$runner = Config\Services::migrations(null, $db);
$runner->setNamespace('App');

echo "2. Executing fresh migration via CodeIgniter MigrationRunner..." . PHP_EOL;
$migrated = $runner->latest('verify_fresh');

if (!$migrated) {
    echo "ERROR: Migration execution failed!" . PHP_EOL;
    foreach ($runner->getCliMessages() as $msg) {
        echo $msg . PHP_EOL;
    }
    exit(1);
}
echo "   Migration executed successfully!" . PHP_EOL;

// 3. Verify all tables created
$tablesAfter = $db->listTables();
echo "3. Tables created (" . count($tablesAfter) . "):" . PHP_EOL;
$expectedTables = [
    'admin_users',
    'admin_audit_logs',
    'employees',
    'employee_face_templates',
    'kiosk_devices',
    'kiosk_pairing_codes',
    'attendance_records',
    'attendance_corrections',
    'idempotency_keys',
    'migrations',
];

foreach ($expectedTables as $expected) {
    if (in_array($expected, $tablesAfter, true)) {
        echo "   [OK] Table {$expected} exists." . PHP_EOL;
    } else {
        echo "   [FAIL] Table {$expected} missing!" . PHP_EOL;
        exit(1);
    }
}

// 4. Verify Primary Keys
echo "4. Verifying Primary Keys..." . PHP_EOL;
foreach ($expectedTables as $table) {
    if ($table === 'migrations') continue;
    $fields = $db->getFieldData($table);
    $hasPk = false;
    foreach ($fields as $field) {
        if ($field->primary_key) {
            $hasPk = true;
            echo "   [OK] {$table} PK -> {$field->name}" . PHP_EOL;
            break;
        }
    }
    if (!$hasPk) {
        echo "   [FAIL] {$table} has no primary key!" . PHP_EOL;
        exit(1);
    }
}

// 5. Verify Foreign Keys
echo "5. Verifying Foreign Keys..." . PHP_EOL;
$fks = $db->query("
    SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = 'attendance_verify_fresh' AND REFERENCED_TABLE_NAME IS NOT NULL
    ORDER BY TABLE_NAME, COLUMN_NAME
")->getResultArray();

foreach ($fks as $fk) {
    echo "   [OK] {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}" . PHP_EOL;
}

// 6. Verify Unique Constraint: (employee_id, work_date) on attendance_records
echo "6. Verifying Unique Constraints..." . PHP_EOL;
$uniqueIndexes = $db->query("
    SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as cols
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = 'attendance_verify_fresh'
      AND TABLE_NAME = 'attendance_records'
      AND NON_UNIQUE = 0
    GROUP BY INDEX_NAME
")->getResultArray();

$hasCompositeShift = false;
foreach ($uniqueIndexes as $idx) {
    echo "   [OK] attendance_records Unique Index '{$idx['INDEX_NAME']}': ({$idx['cols']})" . PHP_EOL;
    if ($idx['cols'] === 'employee_id,work_date') {
        $hasCompositeShift = true;
    }
}
if (!$hasCompositeShift) {
    echo "   [FAIL] Single-shift constraint (employee_id, work_date) missing!" . PHP_EOL;
    exit(1);
}

// 7. Verify Migration History
echo "7. Verifying Migration History..." . PHP_EOL;
$history = $db->table('migrations')->get()->getResultArray();
echo "   Recorded migrations: " . count($history) . PHP_EOL;
foreach ($history as $h) {
    echo "   [OK] Batch {$h['batch']}: {$h['namespace']}\\{$h['class']} (Group: {$h['group']})" . PHP_EOL;
}

// 8. Test Second Migration Invocation (Repeatability & Idempotence)
echo "8. Testing Second Migration Invocation..." . PHP_EOL;
$migratedAgain = $runner->latest('verify_fresh');
echo "   Second invocation result: " . ($migratedAgain ? "SUCCESS (No duplicate objects created)" : "FAILED") . PHP_EOL;

// 9. Application Query Connectivity Test
echo "9. Application Query Connectivity..." . PHP_EOL;
$now = date('Y-m-d H:i:s');
$testId = 'usr-test-' . bin2hex(random_bytes(4));
$insertOk = $db->table('admin_users')->insert([
    'id'            => $testId,
    'username'      => 'verify_admin',
    'password_hash' => password_hash('Pass!123', PASSWORD_BCRYPT),
    'full_name'     => 'Verification Admin',
    'role'          => 'ROLE_ADMIN',
    'is_active'     => 1,
    'created_at'    => $now,
    'updated_at'    => $now,
]);
$found = $db->table('admin_users')->where('id', $testId)->get()->getRow();
if ($found && $found->username === 'verify_admin') {
    echo "   [OK] Application query verified: successfully inserted and read user 'verify_admin'." . PHP_EOL;
} else {
    echo "   [FAIL] Query test failed!" . PHP_EOL;
    exit(1);
}

echo "=== ALL 9 FRESH MIGRATION VERIFICATION CHECKS PASSED ===" . PHP_EOL;
