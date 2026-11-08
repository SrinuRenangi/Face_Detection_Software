<?php

namespace App\Controllers\Api;

use App\Models\EmployeeModel;
use App\Models\AdminAuditLogModel;
use App\Models\AttendancePhotoModel;
use App\Services\AttendanceSyncService;
use App\Services\AttendancePhotoService;
use CodeIgniter\RESTful\ResourceController;
use Config\Database;

class KioskApiController extends ResourceController
{
    protected $format = 'json';

    /**
     * Authenticated Device Status Check
     */
    public function status()
    {
        $device = $this->request->device ?? null;

        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        return $this->respond([
            'status'           => 'success',
            'authorized'       => true,
            'deviceStatus'     => $device->status ?? 'ACTIVE',
            'deviceId'         => $device->id,
            'deviceIdentifier' => $device->device_identifier,
            'deviceName'       => $device->device_name,
            'terminalMode'     => $device->terminal_mode,
            'deviceEpochId'    => $device->device_epoch_id,
            'hardwareTier'     => $device->hardware_tier,
            'tokenExpiresAt'   => $device->token_expires_at ?? null,
            'lastSeenAt'       => $device->last_seen_at,
            'serverTime'       => date('Y-m-d H:i:s'),
            'serverUtcTime'    => gmdate('Y-m-d\TH:i:s\Z'),
        ]);
    }

    /**
     * Kiosk Heartbeat Ping
     */
    public function ping()
    {
        return $this->respond([
            'status'    => 'success',
            'pong'      => true,
            'timestamp' => time(),
        ]);
    }

    /**
     * Server-Time Anchor Endpoint
     * Issues an authoritative, tamper-resistant time anchor bound to the active browser session.
     */
    public function timeAnchor()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost() ?? [];
        $browserSessionId = trim((string) (
            $json['browser_session_id']
            ?? $this->request->getGet('browser_session_id')
            ?? $this->request->getHeaderLine('X-Browser-Session-Id')
        ));

        if (empty($browserSessionId) || strlen($browserSessionId) < 16) {
            return $this->failValidationErrors(['error' => 'A cryptographically random browser_session_id (min 16 chars) is required to bind time anchor.']);
        }

        $browserSessionHash = hash('sha256', $browserSessionId);
        $anchorId   = EmployeeModel::generateUuidV4();
        $serverUtc  = gmdate('Y-m-d H:i:s');
        $expiresAt  = gmdate('Y-m-d H:i:s', strtotime('+48 hours'));
        $now        = date('Y-m-d H:i:s');

        $db = Database::connect();
        $db->table('kiosk_time_anchors')->insert([
            'id'                   => $anchorId,
            'device_id'            => $device->id,
            'device_epoch_id'      => $device->device_epoch_id,
            'browser_session_hash' => $browserSessionHash,
            'server_utc_time'      => $serverUtc,
            'expires_at'           => $expiresAt,
            'created_at'           => $now,
        ]);

        return $this->respond([
            'status'             => 'success',
            'anchorId'           => $anchorId,
            'deviceId'           => $device->id,
            'deviceEpochId'      => $device->device_epoch_id,
            'browserSessionHash' => $browserSessionHash,
            'serverUtcTime'      => $serverUtc . 'Z',
            'expiresAt'          => $expiresAt . 'Z',
        ]);
    }

    /**
     * Credential Rotation Endpoint
     * Rotates device authentication token, immediately invalidating the old credential.
     */
    public function rotateToken()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $rawToken       = bin2hex(random_bytes(32));
        $tokenHash      = hash('sha256', $rawToken);
        $now            = date('Y-m-d H:i:s');
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $db = Database::connect();
        $db->table('kiosk_devices')->where('id', $device->id)->update([
            'device_token_hash' => $tokenHash,
            'token_rotated_at'  => $now,
            'token_expires_at'  => $tokenExpiresAt,
            'updated_at'        => $now,
        ]);

        $auditModel = new AdminAuditLogModel();
        $auditModel->log(
            'KIOSK_SECURITY',
            'KIOSK_TOKEN_ROTATED',
            json_encode([
                'device_id'         => $device->id,
                'device_identifier' => $device->device_identifier,
                'token_rotated_at'  => $now,
                'token_expires_at'  => $tokenExpiresAt,
            ]),
            'KIOSK_DEVICE',
            $device->id
        );

        $isSecure = (ENVIRONMENT === 'production') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->response->setCookie(
            'edifies_kiosk_token',
            $rawToken,
            2592000,
            '',
            '/api/kiosk',
            '',
            $isSecure,
            true, // HttpOnly
            'Strict'
        );

        return $this->respond([
            'status'         => 'success',
            'message'        => 'Device authentication token successfully rotated.',
            'tokenExpiresAt' => $tokenExpiresAt,
        ]);
    }

    /**
     * Authenticated Device Pairing Endpoint
     * Exchanges a high-entropy 128-bit pairing code for a high-entropy device token and epoch ID.
     */
    public function pairDevice()
    {
        // 0. Enforce HTTPS in production
        if ((ENVIRONMENT === 'production' || env('CI_ENVIRONMENT') === 'production') && !$this->request->isSecure()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'code'    => 'INSECURE_TRANSPORT',
                'message' => 'Pairing is prohibited over unencrypted HTTP in production.',
            ]);
        }

        $throttler = service('throttler');
        $ip = $this->request->getIPAddress();

        // 1. Dual-Tier Rate Limiting: IP-based (max 5/min) and Global aggregate endpoint (max 30/min)
        if ($throttler->check(md5('kiosk_pair_' . $ip), 5, MINUTE) === false) {
            return $this->fail('Too many pairing attempts from this IP address. Please wait before retrying.', 429);
        }
        if ($throttler->check('kiosk_pair_endpoint_global', 30, MINUTE) === false) {
            return $this->fail('Pairing service is currently busy. Please wait before retrying.', 429);
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $challengeId = trim((string) ($json['challenge_id'] ?? $json['pairing_code_id'] ?? ''));
        $rawPairingInput = trim((string) ($json['pairing_code'] ?? ''));
        // Cleanse input of separators
        $pairingCode = strtoupper(str_replace(['-', ' ', ':'], '', $rawPairingInput));

        if (empty($pairingCode)) {
            return $this->failValidationErrors(['error' => 'Pairing code is required.']);
        }

        $db         = Database::connect();
        $now        = date('Y-m-d H:i:s');
        $auditModel = new AdminAuditLogModel();

        // Validate code format and length (must have at least 128 bits of entropy: 32 hex chars)
        if (strlen($pairingCode) !== 32 || !ctype_xdigit($pairingCode)) {
            if (!empty($challengeId)) {
                // Count malformed attempt against the specific challenge
                $challengeRec = $db->query("SELECT * FROM kiosk_pairing_codes WHERE id = ? FOR UPDATE", [$challengeId])->getRowArray();
                if ($challengeRec) {
                    $newAttempts = (int) $challengeRec['failed_attempts'] + 1;
                    $newStatus = ($newAttempts >= (int) $challengeRec['max_attempts']) ? 'EXHAUSTED' : $challengeRec['status'];
                    $db->table('kiosk_pairing_codes')->where('id', $challengeRec['id'])->update([
                        'failed_attempts' => $newAttempts,
                        'status'          => $newStatus,
                    ]);
                }
            }

            $auditModel->log(
                'KIOSK_SECURITY',
                'KIOSK_PAIRING_FAILED',
                json_encode([
                    'ip'           => $ip,
                    'challenge_id' => $challengeId,
                    'reason'       => 'Invalid pairing code format or insufficient entropy length',
                ]),
                'KIOSK_PAIRING',
                $challengeId ?: null
            );
            return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
        }

        $codeHash = hash('sha256', $pairingCode);

        // Atomic Check under Database Transaction with row lock
        $db->transBegin();

        if (!empty($challengeId)) {
            // Per-Challenge Attempt Tracking and Attribution
            $pairingRecord = $db->query("
                SELECT * FROM kiosk_pairing_codes 
                WHERE id = ? OR paired_device_id = ? 
                FOR UPDATE
            ", [$challengeId, $challengeId])->getRowArray();

            if (!$pairingRecord) {
                $db->transRollback();
                $auditModel->log(
                    'KIOSK_SECURITY',
                    'KIOSK_PAIRING_FAILED',
                    json_encode([
                        'ip'           => $ip,
                        'challenge_id' => $challengeId,
                        'reason'       => 'Pairing challenge not found',
                    ]),
                    'KIOSK_PAIRING',
                    null
                );
                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing challenge.');
            }

            // Check if code has expired
            if ($pairingRecord['expires_at'] <= $now || $pairingRecord['status'] === 'EXPIRED') {
                $db->table('kiosk_pairing_codes')->where('id', $pairingRecord['id'])->update(['status' => 'EXPIRED']);
                $db->transCommit();
                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
            }

            // Check if already used
            if ($pairingRecord['status'] === 'USED') {
                $db->transRollback();
                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
            }

            // Check if attempt count is already exhausted
            if ((int) $pairingRecord['failed_attempts'] >= (int) $pairingRecord['max_attempts'] || $pairingRecord['status'] === 'EXHAUSTED') {
                $db->table('kiosk_pairing_codes')->where('id', $pairingRecord['id'])->update(['status' => 'EXHAUSTED']);
                $db->transCommit();
                return $this->response->setStatusCode(401)->setJSON([
                    'status'  => 'error',
                    'code'    => 'CHALLENGE_EXHAUSTED',
                    'message' => 'Pairing code attempt limit exhausted. Administrative regeneration required.',
                ]);
            }

            // Verify hash match
            if (!hash_equals($pairingRecord['pairing_code_hash'], $codeHash)) {
                $newAttempts = (int) $pairingRecord['failed_attempts'] + 1;
                $newStatus = ($newAttempts >= (int) $pairingRecord['max_attempts']) ? 'EXHAUSTED' : $pairingRecord['status'];
                $db->table('kiosk_pairing_codes')->where('id', $pairingRecord['id'])->update([
                    'failed_attempts' => $newAttempts,
                    'status'          => $newStatus,
                ]);
                $db->transCommit();

                $auditModel->log(
                    'KIOSK_SECURITY',
                    ($newStatus === 'EXHAUSTED') ? 'KIOSK_PAIRING_EXHAUSTED' : 'KIOSK_PAIRING_FAILED',
                    json_encode([
                        'ip'              => $ip,
                        'challenge_id'    => $challengeId,
                        'code_id'         => $pairingRecord['id'],
                        'failed_attempts' => $newAttempts,
                        'status'          => $newStatus,
                        'reason'          => 'Invalid pairing code for challenge',
                    ]),
                    'KIOSK_PAIRING',
                    $pairingRecord['id']
                );

                if ($newStatus === 'EXHAUSTED') {
                    return $this->response->setStatusCode(401)->setJSON([
                        'status'  => 'error',
                        'code'    => 'CHALLENGE_EXHAUSTED',
                        'message' => 'Pairing code attempt limit exhausted. Administrative regeneration required.',
                    ]);
                }

                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
            }
        } else {
            // Direct Code Hash Lookup (Backward-Compatible)
            $pairingRecord = $db->query("
                SELECT * FROM kiosk_pairing_codes 
                WHERE pairing_code_hash = ? 
                FOR UPDATE
            ", [$codeHash])->getRowArray();

            if (!$pairingRecord) {
                $db->transRollback();
                $auditModel->log(
                    'KIOSK_SECURITY',
                    'KIOSK_PAIRING_FAILED',
                    json_encode([
                        'ip'            => $ip,
                        'code_hash_pre' => substr($codeHash, 0, 12),
                        'reason'        => 'Pairing code not found',
                    ]),
                    'KIOSK_PAIRING',
                    null
                );
                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
            }

            // Check if code has expired
            if ($pairingRecord['expires_at'] <= $now) {
                $db->table('kiosk_pairing_codes')
                    ->where('id', $pairingRecord['id'])
                    ->update(['status' => 'EXPIRED']);
                $db->transCommit();

                $auditModel->log(
                    'KIOSK_SECURITY',
                    'KIOSK_PAIRING_EXPIRED',
                    json_encode([
                        'ip'         => $ip,
                        'code_id'    => $pairingRecord['id'],
                        'expires_at' => $pairingRecord['expires_at'],
                    ]),
                    'KIOSK_PAIRING',
                    $pairingRecord['id']
                );
                return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
            }

            // Check if attempt count is already exhausted
            if ((int) $pairingRecord['failed_attempts'] >= (int) $pairingRecord['max_attempts']) {
                $db->table('kiosk_pairing_codes')
                    ->where('id', $pairingRecord['id'])
                    ->update(['status' => 'EXHAUSTED']);
                $db->transCommit();

                $auditModel->log(
                    'KIOSK_SECURITY',
                    'KIOSK_PAIRING_EXHAUSTED',
                    json_encode([
                        'ip'              => $ip,
                        'code_id'         => $pairingRecord['id'],
                        'failed_attempts' => $pairingRecord['failed_attempts'],
                    ]),
                    'KIOSK_PAIRING',
                    $pairingRecord['id']
                );
                return $this->failUnauthorized('Pairing code attempt limit exhausted. Administrative regeneration required.');
            }
        }

        // Check status must be PENDING
        if ($pairingRecord['status'] !== 'PENDING') {
            $db->transRollback();
            $auditModel->log(
                'KIOSK_SECURITY',
                'KIOSK_PAIRING_FAILED',
                json_encode([
                    'ip'      => $ip,
                    'code_id' => $pairingRecord['id'],
                    'status'  => $pairingRecord['status'],
                    'reason'  => 'Pairing code already used or cancelled',
                ]),
                'KIOSK_PAIRING',
                $pairingRecord['id']
            );
            return $this->failUnauthorized('Invalid, expired, or previously redeemed pairing code.');
        }

        // Generate high-entropy 256-bit device token
        $rawToken       = bin2hex(random_bytes(32));
        $tokenHash      = hash('sha256', $rawToken);
        $tokenCreatedAt = $now;
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $deviceId      = $pairingRecord['paired_device_id'] ?: EmployeeModel::generateUuidV4();
        $deviceEpochId = EmployeeModel::generateUuidV4();
        $deviceIdent   = 'DEV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $deviceName   = trim((string) ($json['device_name'] ?? $pairingRecord['device_name'] ?? 'Authorized Kiosk'));
        $deviceType   = in_array($json['device_type'] ?? '', ['SMARTPHONE', 'TABLET', 'DESKTOP'], true) ? $json['device_type'] : 'TABLET';
        $hardwareTier = in_array($json['hardware_tier'] ?? '', ['ENTRY', 'MIDRANGE', 'PREMIUM'], true) ? $json['hardware_tier'] : 'UNKNOWN';

        // Check if device record already exists (pre-provisioned by admin)
        $existingDevice = $db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        $terminalMode   = $existingDevice['terminal_mode'] ?? 'ENTRY_ONLY';

        if ($existingDevice) {
            $db->table('kiosk_devices')->where('id', $deviceId)->update([
                'device_name'         => $deviceName,
                'device_type'         => $deviceType,
                'device_model'        => substr($json['device_model'] ?? 'Generic Kiosk Device', 0, 100),
                'hardware_tier'       => $hardwareTier,
                'device_epoch_id'     => $deviceEpochId,
                'last_event_sequence' => 0,
                'status'              => 'ACTIVE',
                'device_token_hash'   => $tokenHash,
                'token_created_at'    => $tokenCreatedAt,
                'token_expires_at'    => $tokenExpiresAt,
                'token_rotated_at'    => null,
                'paired_at'           => $now,
                'last_seen_at'        => $now,
                'updated_at'          => $now,
            ]);
            $deviceIdent = $existingDevice['device_identifier'];
        } else {
            $db->table('kiosk_devices')->insert([
                'id'                  => $deviceId,
                'device_name'         => $deviceName,
                'device_identifier'   => $deviceIdent,
                'device_type'         => $deviceType,
                'device_model'        => substr($json['device_model'] ?? 'Generic Kiosk Device', 0, 100),
                'hardware_tier'       => $hardwareTier,
                'terminal_mode'       => $terminalMode,
                'device_epoch_id'     => $deviceEpochId,
                'last_event_sequence' => 0,
                'status'              => 'ACTIVE',
                'device_token_hash'   => $tokenHash,
                'token_created_at'    => $tokenCreatedAt,
                'token_expires_at'    => $tokenExpiresAt,
                'token_rotated_at'    => null,
                'paired_at'           => $now,
                'last_seen_at'        => $now,
                'created_by'          => $pairingRecord['created_by'] ?? 'admin',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // Mark pairing code USED atomically
        $db->table('kiosk_pairing_codes')
            ->where('id', $pairingRecord['id'])
            ->update([
                'status'           => 'USED',
                'used_at'          => $now,
                'paired_device_id' => $deviceId,
            ]);

        // Issue initial server time anchor bound to browser session
        $anchorId   = EmployeeModel::generateUuidV4();
        $serverUtc  = gmdate('Y-m-d H:i:s');
        $anchorExp  = gmdate('Y-m-d H:i:s', strtotime('+48 hours'));

        $browserSessionId = trim((string) (
            $json['browser_session_id']
            ?? $this->request->getHeaderLine('X-Browser-Session-Id')
        ));
        if (empty($browserSessionId)) {
            $browserSessionId = EmployeeModel::generateUuidV4();
        }
        $browserSessionHash = hash('sha256', $browserSessionId);

        $db->table('kiosk_time_anchors')->insert([
            'id'                   => $anchorId,
            'device_id'            => $deviceId,
            'device_epoch_id'      => $deviceEpochId,
            'browser_session_hash' => $browserSessionHash,
            'server_utc_time'      => $serverUtc,
            'expires_at'           => $anchorExp,
            'created_at'           => $now,
        ]);

        $db->transCommit();

        // Audit Log Success (Never log raw token or raw pairing code)
        $auditModel->log(
            'KIOSK_SECURITY',
            'KIOSK_PAIRING_SUCCESS',
            json_encode([
                'device_id'         => $deviceId,
                'device_identifier' => $deviceIdent,
                'device_name'       => $deviceName,
                'device_type'       => $deviceType,
                'terminal_mode'     => $terminalMode,
                'device_epoch_id'   => $deviceEpochId,
                'pairing_code_id'   => $pairingRecord['id'],
                'anchor_id'         => $anchorId,
                'ip'                => $ip,
            ]),
            'KIOSK_DEVICE',
            $deviceId
        );

        // Issue HttpOnly; Secure; SameSite=Strict device authentication cookie scoped to /api/kiosk
        $isSecure = (ENVIRONMENT === 'production') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $this->response->setCookie(
            'edifies_kiosk_token',
            $rawToken,
            2592000, // 30 days
            '',
            '/api/kiosk',
            '',
            $isSecure,
            true, // HttpOnly
            'Strict'
        );

        // Raw token is strictly NEVER returned in the JSON response body
        return $this->respondCreated([
            'status'           => 'success',
            'message'          => 'Device successfully paired and authorized.',
            'deviceId'         => $deviceId,
            'deviceIdentifier' => $deviceIdent,
            'deviceName'       => $deviceName,
            'deviceType'       => $deviceType,
            'terminalMode'     => $terminalMode,
            'deviceEpochId'    => $deviceEpochId,
            'tokenExpiresAt'   => $tokenExpiresAt,
            'serverAnchor'     => [
                'anchor_id'       => $anchorId,
                'server_utc_time' => $serverUtc . 'Z',
                'expires_at'      => $anchorExp . 'Z',
            ],
        ]);
    }

    /**
     * Attendance Punch Sync Endpoint for Multi-Device Kiosks
     * Enforces strictly server-authoritative device settings, time anchoring, and audit logging.
     */
    public function syncAttendance()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();

        if (empty($json) || !isset($json['client_uuid'], $json['employee_id'], $json['timestamp'])) {
            return $this->failValidationErrors([
                'error' => 'Missing required sync payload: client_uuid, employee_id, and timestamp are required.',
            ]);
        }

        $auditModel = new AdminAuditLogModel();

        // 1. Detect and Audit Client Override Attempts
        if (isset($json['device_id']) && $json['device_id'] !== $device->id) {
            $auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_IDENTITY_SPOOF_ATTEMPT',
                json_encode([
                    'authenticated_device_id' => $device->id,
                    'submitted_device_id'     => $json['device_id'],
                    'client_uuid'             => $json['client_uuid'],
                ]),
                'KIOSK_DEVICE',
                $device->id
            );
        }

        if (isset($json['device_epoch_id']) && $json['device_epoch_id'] !== $device->device_epoch_id) {
            $auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_EPOCH_OVERRIDE_ATTEMPT',
                json_encode([
                    'device_id'              => $device->id,
                    'authoritative_epoch_id' => $device->device_epoch_id,
                    'submitted_epoch_id'     => $json['device_epoch_id'],
                    'client_uuid'            => $json['client_uuid'],
                ]),
                'KIOSK_DEVICE',
                $device->id
            );

            // Active online epoch spoofing is rejected with 409 Conflict
            if (($json['sync_source'] ?? 'DIRECT_ONLINE') === 'DIRECT_ONLINE') {
                return $this->fail('Client device epoch mismatch with server authoritative record.', 409);
            }
        }

        if (isset($json['terminal_mode']) && $json['terminal_mode'] !== $device->terminal_mode) {
            $auditModel->log(
                'SYSTEM_SECURITY',
                'DEVICE_TERMINAL_MODE_OVERRIDE_ATTEMPT',
                json_encode([
                    'device_id'                  => $device->id,
                    'authoritative_terminal_mode'=> $device->terminal_mode,
                    'submitted_terminal_mode'    => $json['terminal_mode'],
                    'client_uuid'                => $json['client_uuid'],
                ]),
                'KIOSK_DEVICE',
                $device->id
            );
        }

        // 2. Delegate to Sync Service with STRICT Server-Authoritative Device Identity
        $syncService = new AttendanceSyncService();
        $result = $syncService->processPunch([
            'client_uuid'           => (string) $json['client_uuid'],
            'employee_id'           => (string) $json['employee_id'],
            'device_id'             => $device->id,
            'device_epoch_id'       => $device->device_epoch_id,
            'submitted_epoch_id'    => $json['device_epoch_id'] ?? null,
            'terminal_mode'         => $device->terminal_mode,
            'timestamp'             => (string) $json['timestamp'],
            'client_synced_at'      => $json['client_synced_at'] ?? null,
            'confidence_score'      => isset($json['confidence_score']) ? (float) $json['confidence_score'] : null,
            'sync_source'           => $json['sync_source'] ?? 'OFFLINE_SYNC',
            'device_event_sequence' => isset($json['device_event_sequence']) ? (int) $json['device_event_sequence'] : null,
            'client_timezone'       => $json['client_timezone'] ?? 'Asia/Kolkata',
            'client_monotonic_ms'   => isset($json['client_monotonic_ms']) ? (int) $json['client_monotonic_ms'] : null,
            'server_anchor_id'      => $json['server_anchor_id'] ?? null,
            'browser_session_id'    => $json['browser_session_id'] ?? null,
            'device_wall_clock_at'  => $json['device_wall_clock_at'] ?? null,
            'anchor_elapsed_ms'     => isset($json['anchor_elapsed_ms']) ? (int) $json['anchor_elapsed_ms'] : null,
            'browser_restarted'     => !empty($json['browser_restarted']),
        ]);

        if (isset($result['error'])) {
            $httpCode = 400;
            if ($result['status'] === 'CONFLICT_PAYLOAD_MISMATCH') $httpCode = 409;
            if ($result['status'] === 'QUARANTINED') $httpCode = 422;
            if ($result['status'] === 'INVALID_FUTURE_TIMESTAMP') $httpCode = 422;
            if ($result['status'] === 'TRANSACTION_FAILED') $httpCode = 500;
            if ($result['status'] === 'DEVICE_REVOKED') $httpCode = 403;

            return $this->fail($result['error'], $httpCode);
        }

        return $this->respond([
            'status'         => 'success',
            'sync_status'    => $result['status'],
            'record_id'      => $result['record_id'],
            'work_date'      => $result['work_date'] ?? null,
            'check_in_time'  => $result['check_in_time'],
            'check_out_time' => $result['check_out_time'],
            'terminal_mode'  => $result['terminal_mode'] ?? 'ENTRY_ONLY',
            'event_type'     => $result['event_type'] ?? 'CHECK_IN',
            'message'        => $result['message'],
        ]);
    }

    /**
     * Kiosk Employee PIN Verification (Stage 3 Kiosk Manual Fallback)
     * Verifies Employee ID + 6-digit numeric PIN on an authorized kiosk device.
     * Enforces rate limiting, server-side bcrypt verification, and issues a 60s verification token.
     */
    public function verifyPin()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        // Retry rate limiting per device (Max 5 failed attempts per 60 seconds)
        $cache = service('cache');
        $failKey = 'kiosk_pin_fail_' . $device->id;
        $failedAttempts = (int) $cache->get($failKey);
        if ($failedAttempts >= 5) {
            return $this->response->setStatusCode(429)->setJSON([
                'status'  => 'error',
                'code'    => 'TOO_MANY_FAILED_ATTEMPTS',
                'message' => 'Too many failed verification attempts. Please wait 1 minute before trying again.',
            ]);
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $employeeCode = trim((string) ($json['employee_id'] ?? $json['employee_code'] ?? ''));
        $pin = trim((string) ($json['pin'] ?? ''));

        if (empty($employeeCode) || empty($pin) || !preg_match('/^\d{6}$/', $pin)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'code'    => 'INVALID_INPUT',
                'message' => 'Please provide a valid Employee ID and a 6-digit numeric PIN.',
            ]);
        }

        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->verifyEmployeePin($employeeCode, $pin);

        $auditModel = new AdminAuditLogModel();

        if (!$employee) {
            // Increment failure count
            $cache->save($failKey, $failedAttempts + 1, 60);

            $auditModel->log(
                'KIOSK_SECURITY',
                'KIOSK_PIN_VERIFICATION_FAILED',
                json_encode([
                    'device_id'   => $device->id,
                    'device_ident'=> $device->device_identifier,
                    'input_code'  => $employeeCode,
                    'failed_count'=> $failedAttempts + 1,
                ]),
                'KIOSK_DEVICE',
                $device->id
            );

            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'code'    => 'INVALID_CREDENTIALS',
                'message' => 'Invalid Employee ID or PIN.',
            ]);
        }

        // Verification successful: reset failed attempt counter
        $cache->delete($failKey);

        // Issue single-use verification token (UUIDv4) valid for 60 seconds
        $token = EmployeeModel::generateUuidV4();
        $cache->save('kiosk_verif_' . $token, [
            'employee_id' => $employee['id'],
            'device_id'   => $device->id,
            'verified_at' => time(),
        ], 60);

        $terminalMode = $device->terminal_mode ?? 'ENTRY_ONLY';
        $action = ($terminalMode === 'EXIT_ONLY') ? 'EXIT' : 'ENTRY';
        $actionLabel = ($terminalMode === 'EXIT_ONLY') ? 'Check Out (EXIT)' : 'Check In (ENTRY)';

        return $this->respond([
            'status'             => 'success',
            'employee'           => [
                'id'          => $employee['id'],
                'employee_id' => $employee['employee_id'],
                'full_name'   => $employee['full_name'],
                'department'  => $employee['department'] ?? 'General',
                'designation' => $employee['designation'] ?? 'Staff',
            ],
            'terminal_mode'      => $terminalMode,
            'action'             => $action,
            'action_label'       => $actionLabel,
            'verification_token' => $token,
            'server_time'        => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Confirms and records attendance punch following successful PIN verification.
     * Consumes verification token and invokes AttendanceSyncService for authoritative transaction.
     */
    public function confirmPunch()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $token = trim((string) ($json['verification_token'] ?? ''));
        $employeeId = trim((string) ($json['employee_id'] ?? ''));
        $clientUuid = trim((string) ($json['client_uuid'] ?? ''));

        if (empty($token) || empty($employeeId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'code'    => 'MISSING_PAYLOAD',
                'message' => 'Verification token and employee ID are required.',
            ]);
        }

        // Validate and atomically consume verification token (strictly enforced 60-second TTL)
        $cache = service('cache');
        $tokenData = $cache->get('kiosk_verif_' . $token);
        
        $isExpiredOrInvalid = false;
        if (!$tokenData || !is_array($tokenData)) {
            $isExpiredOrInvalid = true;
        } elseif (($tokenData['employee_id'] ?? '') !== $employeeId || ($tokenData['device_id'] ?? '') !== $device->id) {
            $isExpiredOrInvalid = true;
        } elseif (empty($tokenData['verified_at']) || (time() - (int)$tokenData['verified_at']) > 60) {
            $isExpiredOrInvalid = true;
        }

        if ($isExpiredOrInvalid) {
            if ($tokenData) {
                $cache->delete('kiosk_verif_' . $token);
            }
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'code'    => 'VERIFICATION_EXPIRED',
                'message' => 'PIN verification session expired or invalid. Please verify your PIN again.',
            ]);
        }

        // Immediately delete single-use token to prevent replay
        $cache->delete('kiosk_verif_' . $token);

        // Verify employee is active
        $employeeModel = new EmployeeModel();
        $employee = $employeeModel->find($employeeId);
        if (!$employee || $employee['status'] !== 'ACTIVE') {
            return $this->response->setStatusCode(401)->setJSON([
                'status'  => 'error',
                'code'    => 'EMPLOYEE_INACTIVE',
                'message' => 'Employee is inactive or not found.',
            ]);
        }

        if (empty($clientUuid)) {
            $clientUuid = EmployeeModel::generateUuidV4();
        }

        $clientTimestamp = trim((string) ($json['timestamp'] ?? ''));
        $punchTimestamp  = !empty($clientTimestamp) ? $clientTimestamp : date('Y-m-d H:i:s');

        // Extract and validate photo if submitted
        $photoService = new AttendancePhotoService();
        $photoFile = $this->request->getFile('photo');
        $photoRaw  = $this->request->getPost('photo') ?? $json['photo'] ?? $json['photo_base64'] ?? null;
        $binaryPhoto = $photoService->extractBinaryData($photoFile ?? $photoRaw);

        $storedPhoto = null;
        if (!empty($binaryPhoto)) {
            $storeResult = $photoService->storeValidatedPhoto($binaryPhoto);
            if (!$storeResult['success']) {
                return $this->response->setStatusCode(422)->setJSON([
                    'status'  => 'error',
                    'code'    => 'INVALID_PHOTO',
                    'message' => $storeResult['error'],
                ]);
            }
            $storedPhoto = $storeResult;
        }

        // Delegate to canonical AttendanceSyncService
        $syncService = new AttendanceSyncService();
        $punchParams = [
            'client_uuid'     => $clientUuid,
            'employee_id'     => $employeeId,
            'device_id'       => $device->id,
            'device_epoch_id' => $device->device_epoch_id,
            'terminal_mode'   => $device->terminal_mode,
            'timestamp'       => $punchTimestamp,
            'sync_source'     => 'DIRECT_ONLINE',
            'confidence_score'=> 1.00, // Verified PIN
        ];
        if ($storedPhoto) {
            $punchParams['photo_metadata'] = $storedPhoto;
        }

        try {
            $result = $syncService->processPunch($punchParams);
        } catch (\Throwable $e) {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'code'    => 'TRANSACTION_FAILED',
                'message' => 'Attendance transaction failed: ' . $e->getMessage(),
            ]);
        }

        if (isset($result['status']) && $result['status'] === 'REJECTED_NO_OPEN_RECORD') {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'code'    => 'REJECTED_NO_OPEN_RECORD',
                'message' => $result['message'] ?? 'Cannot record exit: No active check-in record exists for today.',
            ]);
        }

        if (isset($result['status']) && $result['status'] === 'QUARANTINED') {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'code'    => 'QUARANTINED',
                'message' => $result['message'] ?? 'Attendance event quarantined due to invalid transition or sequence anomaly.',
            ]);
        }

        if (isset($result['error'])) {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            $httpCode = 400;
            if ($result['status'] === 'CONFLICT_PAYLOAD_MISMATCH') $httpCode = 409;
            if ($result['status'] === 'DEVICE_REVOKED') $httpCode = 403;
            if (in_array($result['status'], ['DATABASE_ERROR', 'TRANSACTION_FAILED'], true)) $httpCode = 500;

            return $this->response->setStatusCode($httpCode)->setJSON([
                'status'  => 'error',
                'code'    => $result['status'],
                'message' => $result['error'],
            ]);
        }

        // Clean up redundant physical file on retry or repeated continuous presence scan
        if ($storedPhoto && in_array($result['status'] ?? '', ['IDEMPOTENT_CONFIRMED', 'DUPLICATE_IGNORED'], true)) {
            $photoService->deletePhysicalFile($storedPhoto['storage_path']);
        }

        $photoId = $result['photo_id'] ?? null;

        return $this->respond([
            'status'         => 'success',
            'sync_status'    => $result['status'],
            'record_id'      => $result['record_id'] ?? null,
            'event_id'       => $result['event_id'] ?? null,
            'employee_name'  => $employee['full_name'],
            'employee_id'    => $employee['employee_id'],
            'action'         => $result['event_type'] ?? ($device->terminal_mode === 'EXIT_ONLY' ? 'CHECK_OUT' : 'CHECK_IN'),
            'terminal_mode'  => $device->terminal_mode,
            'photo_id'       => $photoId,
            'photo_captured' => ($photoId !== null),
            'check_in_time'  => $result['check_in_time'] ?? null,
            'check_out_time' => $result['check_out_time'] ?? null,
            'message'        => $result['message'] ?? 'Attendance successfully recorded.',
        ]);
    }

    /**
     * Returns active enrolled face templates for real-time edge recognition
     */
    public function getFaceTemplates()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $templateModel = new \App\Models\EmployeeFaceTemplateModel();
        $templates = $templateModel->getActiveEnrolledTemplates();

        $formatted = [];
        foreach ($templates as $t) {
            $embedding = json_decode($t['embedding_data'], true);
            if (!is_array($embedding) || empty($embedding)) {
                continue;
            }

            $formatted[] = [
                'employee_id'    => $t['employee_id'],
                'employee_code'  => $t['employee_code'],
                'employee_name'  => $t['full_name'],
                'department'     => $t['department'],
                'embedding'      => $embedding,
            ];
        }

        return $this->respond([
            'status'    => 'success',
            'count'     => count($formatted),
            'templates' => $formatted,
        ]);
    }

    /**
     * Real-time automated face recognition attendance punch
     */
    public function facePunch()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $employeeId = trim((string) ($json['employee_id'] ?? ''));
        $confidence = (float) ($json['confidence'] ?? 0.0);
        $clientUuid = trim((string) ($json['client_uuid'] ?? ''));

        if (empty($employeeId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Employee ID is required.',
            ]);
        }

        if (empty($clientUuid)) {
            $clientUuid = (string) time() . '_' . bin2hex(random_bytes(8));
        }

        // Verify active employee
        $employeeModel = new \App\Models\EmployeeModel();
        $employee = $employeeModel->find($employeeId);
        if (!$employee) {
            $employee = $employeeModel->where('employee_id', $employeeId)->first();
        }

        if (!$employee || $employee['status'] !== 'ACTIVE') {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Active employee not found.',
            ]);
        }

        // Handle optional captured photo frame
        $storedPhoto = null;
        $photoService = new \App\Services\AttendancePhotoService();
        $photoPayload = $json['photo_payload'] ?? null;
        if (!empty($photoPayload)) {
            $imageBinary = $photoService->extractBinaryData($photoPayload);
            if (!empty($imageBinary)) {
                $storeResult = $photoService->storeValidatedPhoto($imageBinary);
                if (!empty($storeResult['success'])) {
                    $storedPhoto = $storeResult;
                }
            }
        }

        // Delegate to canonical AttendanceSyncService
        $syncService = new \App\Services\AttendanceSyncService();
        $syncPayload = [
            'client_uuid'       => $clientUuid,
            'employee_id'       => $employee['id'],
            'device_id'         => $device->id,
            'device_epoch_id'   => $device->device_epoch_id,
            'terminal_mode'     => $device->terminal_mode,
            'timestamp'         => date('Y-m-d H:i:s'),
            'sync_source'       => 'DIRECT_ONLINE',
            'confidence_score'  => $confidence ?: 0.98,
        ];
        if ($storedPhoto) {
            $syncPayload['photo_metadata'] = $storedPhoto;
        }

        try {
            $result = $syncService->processPunch($syncPayload);
        } catch (\Throwable $e) {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'code'    => 'TRANSACTION_FAILED',
                'message' => 'Attendance transaction failed: ' . $e->getMessage(),
            ]);
        }

        if (isset($result['status']) && in_array($result['status'], ['INVALID_STATE_TRANSITION', 'SEQUENCE_ANOMALY'], true)) {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'code'    => 'QUARANTINED',
                'message' => $result['message'] ?? 'Attendance event quarantined due to sequence anomaly.',
            ]);
        }

        if (isset($result['status']) && $result['status'] === 'REJECTED_NO_OPEN_RECORD') {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(422)->setJSON([
                'status'  => 'error',
                'code'    => 'REJECTED_NO_OPEN_RECORD',
                'message' => 'Check-out scan rejected: No open check-in shift record exists for today.',
            ]);
        }

        if (isset($result['error'])) {
            if ($storedPhoto) {
                $photoService->deletePhysicalFile($storedPhoto['storage_path']);
            }
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => $result['error'],
            ]);
        }

        $eventType = $result['event_type'] ?? ($device->terminal_mode === 'EXIT_ONLY' ? 'CHECK_OUT' : 'CHECK_IN');
        $displayAction = ($eventType === 'CHECK_OUT') ? 'Check-Out (Exit)' : 'Check-In (Entry)';

        return $this->respond([
            'status'         => 'success',
            'sync_status'    => $result['status'],
            'employee_id'    => $employee['employee_id'],
            'employee_name'  => $employee['full_name'],
            'action'         => $eventType,
            'action_label'   => $displayAction,
            'recorded_at'    => date('h:i:s A'),
            'work_date'      => date('d M Y'),
            'message'        => "Attendance recorded: {$employee['full_name']} ({$displayAction})",
        ]);
    }

    /**
     * Rapid Kiosk Face Enrollment Endpoint
     * Allows paired/authorized kiosk terminal to enroll face embedding for a staff member.
     */
    public function enrollFace()
    {
        $device = $this->request->device ?? null;
        if (!$device) {
            return $this->failUnauthorized('Device authorization context not established.');
        }

        $json = $this->request->getJSON(true) ?? $this->request->getPost();
        $employeeId = trim((string) ($json['employee_id'] ?? ''));
        $embedding = $json['embedding'] ?? null;

        if (empty($employeeId)) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Employee ID is required.',
            ]);
        }

        if (!is_array($embedding) || count($embedding) !== 128) {
            return $this->response->setStatusCode(400)->setJSON([
                'status'  => 'error',
                'message' => 'Invalid facial embedding. A 128-dimensional vector is required.',
            ]);
        }

        $employeeModel = new \App\Models\EmployeeModel();
        $employee = $employeeModel->find($employeeId);
        if (!$employee) {
            $employee = $employeeModel->where('employee_id', $employeeId)->first();
        }

        if (!$employee || $employee['status'] !== 'ACTIVE') {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Active employee not found.',
            ]);
        }

        $qualityScore = (float) ($json['quality_score'] ?? 0.98);
        $modelName = trim((string) ($json['model_name'] ?? 'face-api-resnet34'));

        $templateModel = new \App\Models\EmployeeFaceTemplateModel();
        $db = \Config\Database::connect();
        $db->table('employee_face_templates')->where('employee_id', $employee['id'])->delete();

        $templateId = (string) time() . '_' . bin2hex(random_bytes(6));
        $templateModel->insert([
            'id'                => $templateId,
            'employee_id'       => $employee['id'],
            'template_version'  => 'v1.0',
            'vector_dimensions' => 128,
            'embedding_data'    => json_encode($embedding),
            'model_name'        => $modelName,
            'quality_score'     => $qualityScore,
            'registered_by'     => 'KIOSK_' . ($device->device_identifier ?? 'TERMINAL'),
        ]);

        return $this->respond([
            'status'        => 'success',
            'employee_id'   => $employee['id'],
            'employee_name' => $employee['full_name'],
            'message'       => "Biometric template registered for {$employee['full_name']}.",
        ]);
    }
}
