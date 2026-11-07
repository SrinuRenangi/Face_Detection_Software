<?php
defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AttendanceSyncService;
use Config\Database;

if ($argc < 3) {
    echo json_encode(['error' => 'Usage: worker_punch.php <base64_payload> <barrier_microtime>']);
    exit(1);
}

$payloadJson = base64_decode($argv[1]);
$punchData   = json_decode($payloadJson, true);
$barrierTime = (float) $argv[2];

$db = Database::connect('tests');
$connRow = $db->query("SELECT CONNECTION_ID() AS cid")->getRow();
$connectionId = $connRow ? $connRow->cid : null;
$pid = getmypid();

// Synchronized Start Barrier
while (microtime(true) < $barrierTime) {
    usleep(50);
}

$startTime = microtime(true);

$service = new AttendanceSyncService();
$result  = $service->processPunch($punchData);

$endTime = microtime(true);

echo json_encode([
    'pid'           => $pid,
    'connection_id' => $connectionId,
    'start_time'    => $startTime,
    'end_time'      => $endTime,
    'duration_ms'   => round(($endTime - $startTime) * 1000, 2),
    'result'        => $result,
]);
exit(0);
