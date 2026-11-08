<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\AdminAuditLogModel;
use Config\Database;

class KioskDeviceController extends BaseController
{
    protected $db;
    protected AdminAuditLogModel $auditModel;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->auditModel = new AdminAuditLogModel();
    }

    /**
     * List all registered kiosk terminals and active pairing codes
     */
    public function index()
    {
        $role = session()->get('admin_role');
        if ($role !== 'ROLE_ADMIN' && $role !== 'ADMIN') {
            return $this->response->setStatusCode(403)->setBody(view('errors/html/error_403', [
                'message' => 'Access denied. You do not have permission to manage kiosk devices.',
            ]));
        }

        $devices = $this->db->table('kiosk_devices')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        $activeCodes = $this->db->table('kiosk_pairing_codes')
            ->where('status', 'PENDING')
            ->where('expires_at >', date('Y-m-d H:i:s'))
            ->get()
            ->getResultArray();

        $codesByDeviceId = [];
        foreach ($activeCodes as $code) {
            if (!empty($code['paired_device_id'])) {
                $codesByDeviceId[$code['paired_device_id']] = $code;
            }
        }

        $session = session();
        $userRole = $session->get('admin_role') ?? 'ROLE_ADMIN';
        $adminFullName = $session->get('admin_full_name') ?: ($session->get('admin_username') ?: 'Administrator');

        return view('admin/kiosks/index', [
            'pageTitle'       => 'Kiosk Terminal Management',
            'devices'         => $devices,
            'codesByDeviceId' => $codesByDeviceId,
            'userRole'        => $userRole,
            'adminFullName'   => $adminFullName,
            'userName'        => $adminFullName,
        ]);
    }

    /**
     * Create a new kiosk device and generate an initial one-time pairing code
     */
    public function create()
    {
        $session = session();
        $adminName = $session->get('admin_user_name') ?? ($session->get('admin_username') ?? 'admin');

        $deviceName = trim((string) $this->request->getPost('device_name'));
        if (empty($deviceName)) {
            $existingCount = $this->db->table('kiosk_devices')->countAllResults();
            $deviceName = 'Scanner Device ' . ($existingCount + 1);
        }

        $deviceType   = 'TABLET';
        $terminalMode = 'DIRECTIONAL_AUTO';

        $deviceId      = EmployeeModel::generateUuidV4();
        $deviceIdent   = 'DEV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $deviceEpochId = EmployeeModel::generateUuidV4();
        $now           = date('Y-m-d H:i:s');

        // Human-friendly 6-digit pairing code (e.g. 748291)
        $rawCode    = sprintf('%06d', random_int(100000, 999999));
        $codeHash   = hash('sha256', $rawCode);
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $codeId     = EmployeeModel::generateUuidV4();

        $this->db->transStart();

        $this->db->table('kiosk_devices')->insert([
            'id'                  => $deviceId,
            'device_name'         => $deviceName,
            'device_identifier'   => $deviceIdent,
            'device_type'         => $deviceType,
            'device_model'        => 'Browser Scanner Terminal',
            'hardware_tier'       => 'MIDRANGE',
            'terminal_mode'       => $terminalMode,
            'device_epoch_id'     => $deviceEpochId,
            'last_event_sequence' => 0,
            'status'              => 'PENDING_PAIRING',
            'created_by'          => $adminName,
            'created_at'          => $now,
            'updated_at'          => $now,
        ]);

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => $deviceName,
            'pairing_code_hash' => $codeHash,
            'created_by'        => $adminName,
            'expires_at'        => $expiresAt,
            'status'            => 'PENDING',
            'failed_attempts'   => 0,
            'max_attempts'      => 5,
            'paired_device_id'  => $deviceId,
            'created_at'        => $now,
        ]);

        $this->db->transComplete();

        if ($this->db->transStatus() === false) {
            return redirect()->back()->with('error', 'Failed to register kiosk device in database.');
        }

        $this->auditModel->log(
            'KIOSK_ADMIN',
            'KIOSK_DEVICE_CREATED',
            json_encode([
                'device_id'         => $deviceId,
                'device_identifier' => $deviceIdent,
                'device_name'       => $deviceName,
                'terminal_mode'     => $terminalMode,
                'pairing_code_id'   => $codeId,
                'expires_at'        => $expiresAt,
            ]),
            'KIOSK_DEVICE',
            $deviceId
        );

        $formattedCode = substr($rawCode, 0, 3) . ' ' . substr($rawCode, 3, 3);
        session()->setFlashdata('active_pairing_code', $rawCode);
        session()->setFlashdata('active_pairing_device', $deviceName);
        session()->setFlashdata('active_pairing_expires', date('h:i A', strtotime($expiresAt)));

        return redirect()->to('/admin/kiosks')->with('success', "Scanner terminal '{$deviceName}' created. Pairing Code: {$formattedCode} (Valid for 15 minutes).");
    }

    /**
     * Generate or renew a pairing code for an existing kiosk device
     */
    public function generatePairingCode(string $deviceId)
    {
        $session = session();
        $adminName = $session->get('admin_user_name') ?? ($session->get('admin_username') ?? 'admin');

        $device = $this->db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        if (!$device) {
            return redirect()->to('/admin/kiosks')->with('error', 'Kiosk device not found.');
        }

        if ($device['status'] === 'REVOKED') {
            return redirect()->to('/admin/kiosks')->with('error', 'Cannot generate pairing code for a revoked terminal.');
        }

        $now       = date('Y-m-d H:i:s');
        $rawCode   = sprintf('%06d', random_int(100000, 999999));
        $codeHash  = hash('sha256', $rawCode);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        $codeId    = EmployeeModel::generateUuidV4();

        $this->db->transStart();

        // Invalidate prior pending codes for this device
        $this->db->table('kiosk_pairing_codes')
            ->where('paired_device_id', $deviceId)
            ->where('status', 'PENDING')
            ->update([
                'status'       => 'CANCELLED',
                'cancelled_at' => $now,
            ]);

        $this->db->table('kiosk_pairing_codes')->insert([
            'id'                => $codeId,
            'device_name'       => $device['device_name'],
            'pairing_code_hash' => $codeHash,
            'created_by'        => $adminName,
            'expires_at'        => $expiresAt,
            'status'            => 'PENDING',
            'failed_attempts'   => 0,
            'max_attempts'      => 5,
            'paired_device_id'  => $deviceId,
            'created_at'        => $now,
        ]);

        $this->db->transComplete();

        $this->auditModel->log(
            'KIOSK_ADMIN',
            'KIOSK_PAIRING_CODE_RENEWED',
            json_encode([
                'device_id'         => $deviceId,
                'device_identifier' => $device['device_identifier'],
                'pairing_code_id'   => $codeId,
                'expires_at'        => $expiresAt,
            ]),
            'KIOSK_DEVICE',
            $deviceId
        );

        $formattedCode = substr($rawCode, 0, 3) . ' ' . substr($rawCode, 3, 3);
        session()->setFlashdata('active_pairing_code', $rawCode);
        session()->setFlashdata('active_pairing_device', $device['device_name']);
        session()->setFlashdata('active_pairing_expires', date('h:i A', strtotime($expiresAt)));

        return redirect()->to('/admin/kiosks')->with('success', "Fresh 6-digit pairing code for '{$device['device_name']}': {$formattedCode} (Valid for 15 minutes).");
    }

    /**
     * Revoke an active or pending kiosk terminal
     */
    public function revoke(string $deviceId)
    {
        $session = session();
        $adminName = $session->get('admin_user_name') ?? 'admin';
        $reason = trim($this->request->getPost('reason') ?: 'Administrative revocation');

        $device = $this->db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        if (!$device) {
            return redirect()->to('/admin/kiosks')->with('error', 'Kiosk device not found.');
        }

        $now = date('Y-m-d H:i:s');

        $this->db->transStart();

        $this->db->table('kiosk_devices')
            ->where('id', $deviceId)
            ->update([
                'status'            => 'REVOKED',
                'revoked_at'        => $now,
                'revocation_reason' => $reason,
                'updated_at'        => $now,
            ]);

        // Invalidate any pending codes
        $this->db->table('kiosk_pairing_codes')
            ->where('paired_device_id', $deviceId)
            ->where('status', 'PENDING')
            ->update([
                'status'       => 'CANCELLED',
                'cancelled_at' => $now,
            ]);

        $this->db->transComplete();

        $this->auditModel->log(
            'KIOSK_ADMIN',
            'KIOSK_DEVICE_REVOKED',
            json_encode([
                'device_id'         => $deviceId,
                'device_identifier' => $device['device_identifier'],
                'reason'            => $reason,
                'revoked_by'        => $adminName,
            ]),
            'KIOSK_DEVICE',
            $deviceId
        );

        return redirect()->to('/admin/kiosks')->with('success', "Terminal '{$device['device_identifier']}' has been permanently revoked.");
    }
}

