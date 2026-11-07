<?php
defined('HOMEPATH')   || define('HOMEPATH', realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR);
defined('CONFIGPATH') || define('CONFIGPATH', realpath(__DIR__ . '/../../app/Config/') . DIRECTORY_SEPARATOR);
defined('PUBLICPATH') || define('PUBLICPATH', realpath(__DIR__ . '/../../public/') . DIRECTORY_SEPARATOR);

require __DIR__ . '/../../vendor/codeigniter4/framework/system/Test/bootstrap.php';

use App\Services\AttendanceSyncService;
use Config\Database;

if ($argc < 3) {
    echo json_encode(['error' => 'Usage: worker_review.php <base64_payload> <barrier_microtime>']);
    exit(1);
}

$payloadJson = base64_decode($argv[1]);
$reviewData  = json_decode($payloadJson, true);
$barrierTime = (float) $argv[2];

// Connect using restricted edifies_app account on tests database
$db = Database::connect('tests');
$connRow = $db->query("SELECT CONNECTION_ID() AS cid, CURRENT_USER() AS cur_user")->getRow();
$connectionId = $connRow ? $connRow->cid : null;
$currentUser  = $connRow ? $connRow->cur_user : null;
$pid = getmypid();

// Synchronized Start Barrier
while (microtime(true) < $barrierTime) {
    usleep(50);
}

$startTime = microtime(true);

$service = new AttendanceSyncService();
$action  = strtoupper($reviewData['action'] ?? 'APPROVE');
$eventId = $reviewData['event_id'] ?? '';
$adminId = $reviewData['admin_user_id'] ?? 'usr-admin-01';
$adminU  = $reviewData['admin_username'] ?? 'admin';
$reason  = $reviewData['reason'] ?? 'Concurrency review testing';

if (!empty($reviewData['simulate_db_error'])) {
    // Simulate error during review
    $result = [
        'status' => 'ERROR',
        'error'  => 'Simulated database failure during review operation',
    ];
} else {
    if ($action === 'APPROVE') {
        $result = $service->approveHeldEvent($eventId, $adminId, $adminU, $reason);
    } else {
        $result = $service->rejectHeldEvent($eventId, $adminId, $adminU, $reason);
    }
}

$endTime = microtime(true);

echo json_encode([
    'pid'           => $pid,
    'connection_id' => $connectionId,
    'current_user'  => $currentUser,
    'start_time'    => $startTime,
    'end_time'      => $endTime,
    'duration_ms'   => round(($endTime - $startTime) * 1000, 2),
    'result'        => $result,
]);
exit(0);

