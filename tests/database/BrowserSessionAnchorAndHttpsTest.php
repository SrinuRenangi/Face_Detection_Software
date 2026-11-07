<?php

namespace Tests\Database;

use App\Models\AttendanceEventModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Services\AttendanceSyncService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;

class BrowserSessionAnchorAndHttpsTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $db;
    protected AttendanceEventModel $eventModel;
    protected AttendanceRecordModel $recordModel;
    protected EmployeeModel $employeeModel;
    protected KioskDeviceModel $kioskModel;
    protected AttendanceSyncService $syncService;

    protected string $deviceId;
    protected string $employeeId;
    protected string $deviceEpochId;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }
        $this->db            = Database::connect();
        $this->eventModel    = new AttendanceEventModel();
        $this->recordModel   = new AttendanceRecordModel();
        $this->employeeModel = new EmployeeModel();
        $this->kioskModel    = new KioskDeviceModel();
        $this->syncService   = new AttendanceSyncService();

        // Clean up prior test data in FK-safe order (children first)
        $this->db->table('attendance_events')->where('device_epoch_id', 'epoch-sess-test-01')->delete();
        $this->db->table('kiosk_time_anchors')->where('device_epoch_id', 'epoch-sess-test-01')->delete();
        $this->db->table('employees')->where('employee_id LIKE', 'ANCHOR-EMP%')->delete();
        $this->db->table('kiosk_devices')->where('device_identifier LIKE', 'ANCHOR%')->delete();

        $this->deviceId      = EmployeeModel::generateUuidV4();
        $this->employeeId    = EmployeeModel::generateUuidV4();
        $this->deviceEpochId = 'epoch-sess-test-01';

        $this->kioskModel->insert([
            'id'                  => $this->deviceId,
            'device_name'         => 'Anchor Session Test Terminal',
            'device_identifier'   => 'ANCHOR-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => $this->deviceEpochId,
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->employeeModel->insert([
            'id'          => $this->employeeId,
            'employee_id' => 'ANCHOR-EMP-' . bin2hex(random_bytes(3)),
            'full_name'   => 'Anchor Test Employee',
            'department'  => 'Mathematics',
            'status'      => 'ACTIVE',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->table('kiosk_time_anchors')->where('device_id', $this->deviceId)->delete();
        $this->db->table('attendance_events')->where('device_id', $this->deviceId)->delete();
        $this->db->table('attendance_records')->where('employee_id', $this->employeeId)->delete();
        $this->db->table('kiosk_devices')->where('id', $this->deviceId)->delete();
        $this->db->table('employees')->where('id', $this->employeeId)->delete();
        foreach (glob(WRITEPATH . 'cache/throttler_*') ?: [] as $f) {
            @unlink($f);
        }
        service('cache')->clean();
        parent::tearDown();
    }

    /**
     * Helper to create a time anchor bound to a session
     */
    protected function createAnchor(string $sessionId, string $serverUtcTime, ?string $deviceId = null, ?string $epochId = null): string
    {
        $anchorId = EmployeeModel::generateUuidV4();
        $targetDevice = $deviceId ?? $this->deviceId;
        $targetEpoch  = $epochId ?? $this->deviceEpochId;

        $this->db->table('kiosk_time_anchors')->insert([
            'id'                   => $anchorId,
            'device_id'            => $targetDevice,
            'device_epoch_id'      => $targetEpoch,
            'server_utc_time'      => $serverUtcTime,
            'expires_at'           => date('Y-m-d H:i:s', strtotime($serverUtcTime . ' +48 hours')),
            'browser_session_hash' => hash('sha256', $sessionId),
            'created_at'           => $serverUtcTime,
        ]);

        return $anchorId;
    }

    // 1. Valid anchor and same session -> accepted without review
    public function testScenario1_ValidAnchorAndSameSessionAccepted()
    {
        $sessionId = 'browser-session-alpha-12345';
        $nowEpoch = time();
        $anchorTime = gmdate('Y-m-d H:i:s', $nowEpoch - 300); // Created 5 mins ago
        $anchorId = $this->createAnchor($sessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => 240000, // 4 minutes elapsed from anchor
        ]);

        $this->assertContains($result['status'], ['CREATED', 'RECONCILED_EARLIER', 'PROCESSED']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('PROCESSED', $event['processing_status']);
        $this->assertContains($event['clock_trust_status'], ['NORMAL_SYNC', 'TRUSTED_SERVER_TIME']);
    }

    // 2. Same session after two hours offline -> accepted without review (< 48h limit)
    public function testScenario2_ExtendedOfflineTwoHoursSameSessionAccepted()
    {
        $sessionId = 'browser-session-beta-67890';
        $nowEpoch = time();
        $anchorTime = gmdate('Y-m-d H:i:s', $nowEpoch - 7200); // 2 hours ago
        $anchorId = $this->createAnchor($sessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => 7140000, // 1h 59m elapsed from anchor
        ]);

        $this->assertContains($result['status'], ['CREATED', 'RECONCILED_EARLIER', 'PROCESSED']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('PROCESSED', $event['processing_status']);
    }

    // 3. Browser reload generates new session ID -> session mismatch -> REVIEW_REQUIRED
    public function testScenario3_BrowserReloadNewSessionIdHeldForReview()
    {
        $originalSessionId = 'original-session-11111';
        $reloadedSessionId = 'reloaded-session-22222';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 600);
        $anchorId = $this->createAnchor($originalSessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $reloadedSessionId, // Reload generated new ID!
            'anchor_elapsed_ms'     => 540000,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
        $this->assertSame('UNVERIFIED_OFFLINE_TIME', $event['clock_trust_status']);
    }

    // 4. Browser restart -> fresh session ID -> held for review
    public function testScenario4_BrowserRestartFreshSessionHeldForReview()
    {
        $preRestartSessionId  = 'pre-restart-sess-33333';
        $postRestartSessionId = 'post-restart-sess-44444';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 1800);
        $anchorId = $this->createAnchor($preRestartSessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $postRestartSessionId,
            'anchor_elapsed_ms'     => 1740000,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    // 5. Replayed old anchor with new session ID -> held for review
    public function testScenario5_ReplayedOldAnchorWithDifferentSessionHeldForReview()
    {
        $oldSessionId = 'stale-session-55555';
        $newSessionId = 'attacker-session-66666';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 7200);
        $anchorId = $this->createAnchor($oldSessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $newSessionId,
            'anchor_elapsed_ms'     => 7140000,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    // 6. Anchor from another device -> rejected or held for review
    public function testScenario6_AnchorFromAnotherDeviceHeldForReview()
    {
        $foreignDeviceId = EmployeeModel::generateUuidV4();
        $this->kioskModel->insert([
            'id'                  => $foreignDeviceId,
            'device_name'         => 'Foreign Terminal',
            'device_identifier'   => 'FOR-' . bin2hex(random_bytes(3)),
            'terminal_mode'       => 'ENTRY_ONLY',
            'device_epoch_id'     => 'epoch-foreign',
            'last_event_sequence' => 0,
            'status'              => 'ACTIVE',
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $sessionId = 'valid-session-77777';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 900);
        $anchorId = $this->createAnchor($sessionId, $anchorTime, $foreignDeviceId, 'epoch-foreign');

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId, // Punch submitted from our device
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId, // Foreign anchor
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => 840000,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);

        // Clean up foreign device
        $this->db->table('kiosk_time_anchors')->where('device_id', $foreignDeviceId)->delete();
        $this->db->table('kiosk_devices')->where('id', $foreignDeviceId)->delete();
    }

    // 7. Anchor from an old retired epoch -> rejected or held for review
    public function testScenario7_AnchorFromRetiredEpochHeldForReview()
    {
        $retiredEpochId = 'retired-epoch-99999';
        $sessionId = 'epoch-session-88888';
        $anchorTime = date('Y-m-d 08:00:00');
        $anchorId = $this->createAnchor($sessionId, $anchorTime, $this->deviceId, $retiredEpochId);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId, // Current device epoch
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d 08:15:00'),
            'client_synced_at'      => date('Y-m-d 08:15:05'),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId, // Anchor bound to retired epoch
            'browser_session_id'    => $sessionId,
            'monotonic_elapsed_ms'  => 900000,
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    // 8. Negative monotonic elapsed time -> clock corruption -> held for review
    public function testScenario8_NegativeMonotonicElapsedHeldForReview()
    {
        $sessionId = 'monotonic-session-99999';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 600);
        $anchorId = $this->createAnchor($sessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => -5000, // Negative monotonic time!
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    // 9. Excessive monotonic elapsed time (> 48h) -> held for review
    public function testScenario9_ExcessiveMonotonicElapsedHeldForReview()
    {
        $sessionId = 'excessive-session-10101';
        $nowEpoch = time();
        $anchorTime = date('Y-m-d H:i:s', $nowEpoch - 600);
        $anchorId = $this->createAnchor($sessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => date('Y-m-d H:i:s', $nowEpoch - 60),
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => 173000000, // > 48 hours (48h = 172,800,000 ms)
        ]);

        $this->assertSame('HELD_FOR_REVIEW', $result['status']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('REVIEW_REQUIRED', $event['processing_status']);
    }

    // 10. Manipulated device wall-clock time with valid monotonic time -> derived UTC accurately calculated
    public function testScenario10_ManipulatedDeviceWallClockIgnoredInFavorOfDerivedUtc()
    {
        $sessionId = 'tampered-wall-clock-session';
        $nowEpoch = time();
        $anchorTime = gmdate('Y-m-d H:i:s', $nowEpoch - 900); // 15 mins ago
        $anchorId = $this->createAnchor($sessionId, $anchorTime);

        $clientUuid = EmployeeModel::generateUuidV4();
        // Attacker manipulated system clock to say 2030-01-01 12:00:00!
        $tamperedWallClock = '2030-01-01 12:00:00';
        $result = $this->syncService->processPunch([
            'client_uuid'           => $clientUuid,
            'employee_id'           => $this->employeeId,
            'device_id'             => $this->deviceId,
            'device_epoch_id'       => $this->deviceEpochId,
            'device_event_sequence' => 1,
            'timestamp'             => $tamperedWallClock,
            'client_synced_at'      => date('Y-m-d H:i:s', $nowEpoch),
            'sync_source'           => 'OFFLINE_SYNC',
            'server_anchor_id'      => $anchorId,
            'browser_session_id'    => $sessionId,
            'anchor_elapsed_ms'     => 600000, // 10 minutes elapsed
        ]);

        $this->assertContains($result['status'], ['CREATED', 'RECONCILED_EARLIER', 'PROCESSED']);
        $event = $this->eventModel->where('client_uuid', $clientUuid)->first();
        $this->assertSame('PROCESSED', $event['processing_status']);

        // Check that authoritative derived time is anchor + 10 mins (600s), NOT the 2030 tampered wall clock!
        $derivedTime = $this->syncService->calculateAnchorDerivedUtc($event);
        $expectedTime = gmdate('Y-m-d H:i:s', strtotime($anchorTime . ' UTC') + 600);
        $this->assertEquals($expectedTime, $derivedTime);
    }

    // Pairing challenge attribution: 5 wrong guesses on specific challenge_id exhausts it across rotating IPs
    public function testPairingChallengeAttributionExhaustsAfter5AttemptsAcrossRotatingIps()
    {
        service('cache')->clean();
        $origIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $codeId = 'challenge-test-' . bin2hex(random_bytes(4));
        $pairingCode = strtoupper(bin2hex(random_bytes(16)));
        $now = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => 'Rotating IP Attack Test',
            'pairing_code_hash' => hash('sha256', $pairingCode),
            'failed_attempts'   => 0,
            'max_attempts'      => 5,
            'created_by'        => 'admin',
            'expires_at'        => $expires,
            'status'            => 'PENDING',
            'created_at'        => $now,
        ]);

        try {
            // Submit 4 incorrect attempts with challenge_id from 4 different IPs (using valid 32-hex guesses)
            for ($i = 1; $i <= 4; $i++) {
                \Config\Services::reset(true);
                $_SERVER['REMOTE_ADDR'] = "198.51.100.{$i}";
                $wrongCode = strtoupper(bin2hex(random_bytes(16)));
                $res = $this->post('/api/kiosk/pair', [
                    'challenge_id' => $codeId,
                    'pairing_code' => $wrongCode,
                ]);
                $res->assertStatus(401);
                $challenge = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
                $this->assertEquals($i, (int) $challenge['failed_attempts']);
                $this->assertEquals('PENDING', $challenge['status']);
            }

            // 5th incorrect attempt from 5th distinct IP
            \Config\Services::reset(true);
            $_SERVER['REMOTE_ADDR'] = '198.51.100.5';
            $wrongCode5 = strtoupper(bin2hex(random_bytes(16)));
            $res5 = $this->post('/api/kiosk/pair', [
                'challenge_id' => $codeId,
                'pairing_code' => $wrongCode5,
            ]);
            $res5->assertStatus(401);

            // Challenge is now EXHAUSTED
            $challenge5 = $this->db->table('kiosk_pairing_codes')->where('id', $codeId)->get()->getRowArray();
            $this->assertEquals('EXHAUSTED', $challenge5['status']);
            $this->assertEquals(5, (int) $challenge5['failed_attempts']);

            // 6th attempt with CORRECT code should now be REJECTED because challenge is EXHAUSTED!
            \Config\Services::reset(true);
            $_SERVER['REMOTE_ADDR'] = '198.51.100.6';
            $res6 = $this->post('/api/kiosk/pair', [
                'challenge_id' => $codeId,
                'pairing_code' => $pairingCode, // Valid code, but challenge exhausted
            ]);
            $res6->assertStatus(401);
            $res6->assertJSONFragment(['code' => 'CHALLENGE_EXHAUSTED']);
        } finally {
            $_SERVER['REMOTE_ADDR'] = $origIp;
        }
    }

    // HTTPS transport enforcement in production environment
    public function testProductionEnforcesHttpsTransportForPairingAndSync()
    {
        service('cache')->clean();
        $origIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.99';
        $originalEnv     = getenv('CI_ENVIRONMENT');
        $origEnvSuper    = $_ENV['CI_ENVIRONMENT'] ?? null;
        $origServerSuper = $_SERVER['CI_ENVIRONMENT'] ?? null;

        putenv('CI_ENVIRONMENT=production');
        $_ENV['CI_ENVIRONMENT']    = 'production';
        $_SERVER['CI_ENVIRONMENT'] = 'production';

        try {
            // Attempt pair over plain HTTP (no HTTPS header, no forwarded proto)
            $resPair = $this->withHeaders([
                'HTTPS' => 'off',
            ])->post('/api/kiosk/pair', [
                'pairing_code' => strtoupper(bin2hex(random_bytes(16))),
            ]);
            $resPair->assertStatus(403);
            $resPair->assertJSONFragment(['code' => 'INSECURE_TRANSPORT']);

            // Attempt sync over plain HTTP (protected by DeviceAuthFilter)
            $resSync = $this->withHeaders([
                'HTTPS' => 'off',
            ])->post('/api/kiosk/attendance/sync', [
                'client_uuid' => EmployeeModel::generateUuidV4(),
            ]);
            $resSync->assertStatus(403);
            $resSync->assertJSONFragment(['code' => 'INSECURE_TRANSPORT']);

            // Attempt token rotation over plain HTTP (protected by DeviceAuthFilter)
            $resRotate = $this->withHeaders([
                'HTTPS' => 'off',
            ])->post('/api/kiosk/token/rotate', []);
            $resRotate->assertStatus(403);
            $resRotate->assertJSONFragment(['code' => 'INSECURE_TRANSPORT']);

        } finally {
            // Restore original environment
            putenv('CI_ENVIRONMENT=' . ($originalEnv !== false ? $originalEnv : 'testing'));
            if ($origEnvSuper !== null) {
                $_ENV['CI_ENVIRONMENT'] = $origEnvSuper;
            } else {
                unset($_ENV['CI_ENVIRONMENT']);
            }
            if ($origServerSuper !== null) {
                $_SERVER['CI_ENVIRONMENT'] = $origServerSuper;
            } else {
                unset($_SERVER['CI_ENVIRONMENT']);
            }
            $_SERVER['REMOTE_ADDR'] = $origIp;
        }
    }
}
