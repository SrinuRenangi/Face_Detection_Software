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
        $userRole = $session->get('admin_role') ?? 'ADMIN';
        $userName = $session->get('admin_user_name') ?? 'Administrator';

        return view('admin/kiosks/index', [
            'pageTitle'       => 'Kiosk Terminal Management',
            'devices'         => $devices,
            'codesByDeviceId' => $codesByDeviceId,
            'userRole'        => $userRole,
            'userName'        => $userName,
        ]);
    }

    /**
     * Create a new kiosk device and generate an initial one-time pairing code
     */
    public function create()
    {
        $session = session();
        $adminName = $session->get('admin_user_name') ?? 'admin';

        $rules = [
            'device_name'   => 'required|min_length[3]|max_length[100]',
            'device_type'   => 'required|in_list[SMARTPHONE,TABLET]',
            'terminal_mode' => 'required|in_list[ENTRY_ONLY,EXIT_ONLY]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Validation error: please check device details.');
        }

        $deviceName   = trim($this->request->getPost('device_name'));
        $deviceType   = $this->request->getPost('device_type');
        $terminalMode = $this->request->getPost('terminal_mode');

        $deviceId      = EmployeeModel::generateUuidV4();
        $deviceIdent   = 'DEV-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $deviceEpochId = EmployeeModel::generateUuidV4();
        $now           = date('Y-m-d H:i:s');

        // Generate 32-character high-entropy uppercase pairing code (16 bytes = 128 bits entropy)
        $rawCode    = strtoupper(bin2hex(random_bytes(16)));
        $codeHash   = hash('sha256', $rawCode);
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+10 minutes'));
        $codeId     = EmployeeModel::generateUuidV4();

        $this->db->transStart();

        $this->db->table('kiosk_devices')->insert([
            'id'                  => $deviceId,
            'device_name'         => $deviceName,
            'device_identifier'   => $deviceIdent,
            'device_type'         => $deviceType,
            'device_model'        => 'Pending Hardware Registration',
            'hardware_tier'       => 'UNKNOWN',
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

        return redirect()->to('/admin/kiosks')->with('success', "Kiosk device '{$deviceIdent}' created. One-time pairing code: {$rawCode} (Expires in 10 minutes).");
    }

    /**
     * Generate or renew a pairing code for an existing kiosk device
     */
    public function generatePairingCode(string $deviceId)
    {
        $session = session();
        $adminName = $session->get('admin_user_name') ?? 'admin';

        $device = $this->db->table('kiosk_devices')->where('id', $deviceId)->get()->getRowArray();
        if (!$device) {
            return redirect()->to('/admin/kiosks')->with('error', 'Kiosk device not found.');
        }

        if ($device['status'] === 'REVOKED') {
            return redirect()->to('/admin/kiosks')->with('error', 'Cannot generate pairing code for a revoked terminal.');
        }

        $now       = date('Y-m-d H:i:s');
        $rawCode   = strtoupper(bin2hex(random_bytes(16)));
        $codeHash  = hash('sha256', $rawCode);
        $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));
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

        return redirect()->to('/admin/kiosks')->with('success', "Fresh pairing code for '{$device['device_identifier']}': {$rawCode} (Expires in 10 minutes).");
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

