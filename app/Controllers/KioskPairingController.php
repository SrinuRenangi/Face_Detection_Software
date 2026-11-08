<?php

namespace App\Controllers;

use App\Models\KioskDeviceModel;
use App\Models\EmployeeModel;
use App\Models\AdminAuditLogModel;
use Config\Database;

class KioskPairingController extends BaseController
{
    /**
     * Renders Dedicated Scanner Device Pairing Interface
     */
    public function pair(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $db = Database::connect();
        $isPaired = false;
        $pairedDevice = null;

        // Check if device token cookie is present in cookies or header
        $rawToken = $this->request->getCookie('edifies_kiosk_token');
        if (empty($rawToken)) {
            $cookieHeader = $this->request->getHeaderLine('Cookie');
            if (!empty($cookieHeader) && preg_match('/(?:^|;\s*)edifies_kiosk_token=([^;]+)/', $cookieHeader, $matches)) {
                $rawToken = trim($matches[1]);
            }
        }

        if (!empty($rawToken)) {
            $tokenHash = hash('sha256', $rawToken);
            $device = $db->table('kiosk_devices')
                ->where('device_token_hash', $tokenHash)
                ->where('status', 'ACTIVE')
                ->get()
                ->getRowArray();

            if ($device) {
                $isPaired = true;
                $pairedDevice = $device;
            }
        }

        return view('attendance/pair', [
            'pageTitle'    => 'Pair Attendance Scanner Device',
            'isPaired'     => $isPaired,
            'pairedDevice' => $pairedDevice,
            'serverTime'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Processes Scanner Device Pairing via Admin-Generated Pairing Code
     */
    public function attemptPair(): \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
    {
        $ip = $this->request->getIPAddress();
        $throttler = service('throttler');

        // Rate limiting
        if ($throttler->check(md5('kiosk_pair_web_' . $ip), 5, MINUTE) === false) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Too many pairing attempts from this network. Please wait a minute before retrying.');
        }

        $rawInput = trim((string) $this->request->getPost('pairing_code'));
        $pairingCode = strtoupper(str_replace(['-', ' ', ':'], '', $rawInput));
        $deviceName = trim((string) ($this->request->getPost('device_name') ?: 'Authorized Kiosk Scanner'));
        $deviceType = (string) $this->request->getPost('device_type') ?: 'TABLET';
        if (!in_array($deviceType, ['SMARTPHONE', 'TABLET', 'DESKTOP'], true)) {
            $deviceType = 'TABLET';
        }

        if (empty($pairingCode)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Pairing code is required. Please obtain a code from the Administrator.');
        }

        $auditModel = new AdminAuditLogModel();
        $db = Database::connect();
        $now = date('Y-m-d H:i:s');

        // Entropy and length validation: 32 hex chars (128-bit)
        if (strlen($pairingCode) !== 32 || !ctype_xdigit($pairingCode)) {
            $auditModel->log(
                'KIOSK_SECURITY',
                'KIOSK_PAIRING_FAILED',
                json_encode([
                    'ip'     => $ip,
                    'reason' => 'Malformed pairing code format or length',
                ]),
                'KIOSK_PAIRING',
                null
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid pairing code format. Pairing codes must be 32 hexadecimal characters.');
        }

        $codeHash = hash('sha256', $pairingCode);

        $db->transBegin();

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
                    'ip'     => $ip,
                    'reason' => 'Pairing code not found',
                ]),
                'KIOSK_PAIRING',
                null
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid, expired, or previously redeemed pairing code.');
        }

        // Expiry check
        if ($pairingRecord['expires_at'] <= $now || $pairingRecord['status'] === 'EXPIRED') {
            $db->table('kiosk_pairing_codes')->where('id', $pairingRecord['id'])->update(['status' => 'EXPIRED']);
            $db->transCommit();

            return redirect()->back()
                ->withInput()
                ->with('error', 'This pairing code has expired. Please request a new pairing code from an Administrator.');
        }

        // Exhausted check
        if ((int) $pairingRecord['failed_attempts'] >= (int) $pairingRecord['max_attempts'] || $pairingRecord['status'] === 'EXHAUSTED') {
            $db->table('kiosk_pairing_codes')->where('id', $pairingRecord['id'])->update(['status' => 'EXHAUSTED']);
            $db->transCommit();

            return redirect()->back()
                ->withInput()
                ->with('error', 'Pairing code attempt limit exhausted. Administrative regeneration required.');
        }

        // Status check
        if ($pairingRecord['status'] !== 'PENDING') {
            $db->transRollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'This pairing code has already been used or revoked.');
        }

        // Generate 256-bit high-entropy device token
        $rawToken       = bin2hex(random_bytes(32));
        $tokenHash      = hash('sha256', $rawToken);
        $tokenExpiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $deviceId      = $pairingRecord['paired_device_id'] ?: EmployeeModel::generateUuidV4();
        $deviceEpochId = EmployeeModel::generateUuidV4();
        $deviceIdent   = 'DEV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $existingDevice = $db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        $terminalMode   = $existingDevice['terminal_mode'] ?? 'ENTRY_ONLY';

        if ($existingDevice) {
            $db->table('kiosk_devices')->where('id', $deviceId)->update([
                'device_name'         => $deviceName,
                'device_type'         => $deviceType,
                'device_model'        => 'Browser Scanner Terminal',
                'hardware_tier'       => 'MIDRANGE',
                'device_epoch_id'     => $deviceEpochId,
                'last_event_sequence' => 0,
                'status'              => 'ACTIVE',
                'device_token_hash'   => $tokenHash,
                'token_created_at'    => $now,
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
                'device_model'        => 'Browser Scanner Terminal',
                'hardware_tier'       => 'MIDRANGE',
                'terminal_mode'       => $terminalMode,
                'device_epoch_id'     => $deviceEpochId,
                'last_event_sequence' => 0,
                'status'              => 'ACTIVE',
                'device_token_hash'   => $tokenHash,
                'token_created_at'    => $now,
                'token_expires_at'    => $tokenExpiresAt,
                'token_rotated_at'    => null,
                'paired_at'           => $now,
                'last_seen_at'        => $now,
                'created_by'          => $pairingRecord['created_by'] ?? 'admin',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        // Mark pairing code USED
        $db->table('kiosk_pairing_codes')
            ->where('id', $pairingRecord['id'])
            ->update([
                'status'           => 'USED',
                'used_at'          => $now,
                'paired_device_id' => $deviceId,
            ]);

        // Issue initial server time anchor
        $anchorId           = EmployeeModel::generateUuidV4();
        $serverUtc          = gmdate('Y-m-d H:i:s');
        $anchorExp          = gmdate('Y-m-d H:i:s', strtotime('+48 hours'));
        $browserSessionId   = EmployeeModel::generateUuidV4();
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

        // Audit success
        $auditModel->log(
            'KIOSK_SECURITY',
            'KIOSK_PAIRING_SUCCESS',
            json_encode([
                'device_id'         => $deviceId,
                'device_identifier' => $deviceIdent,
                'device_name'       => $deviceName,
                'pairing_code_id'   => $pairingRecord['id'],
                'ip'                => $ip,
                'channel'           => 'WEB_PAIRING_GATEWAY',
            ]),
            'KIOSK_DEVICE',
            $deviceId
        );

        // Store device identifier in session for friendly web UX
        session()->set([
            'kiosk_paired_device_id'    => $deviceId,
            'kiosk_paired_device_ident' => $deviceIdent,
            'kiosk_paired_device_name'  => $deviceName,
        ]);

        // Issue HttpOnly, SameSite=Strict device cookie scoped to /api/kiosk on the redirect response
        $isSecure = (ENVIRONMENT === 'production') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        return redirect()->to('/attendance')
            ->setCookie(
                'edifies_kiosk_token',
                $rawToken,
                2592000,
                '',
                '/api/kiosk',
                '',
                $isSecure,
                true, // HttpOnly
                'Strict'
            )
            ->with('success', "Device successfully paired and authorized as {$deviceName} ({$deviceIdent})!");
    }
}
