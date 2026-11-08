<?php

namespace App\Controllers;

use App\Models\KioskDeviceModel;
use Config\Database;

class AttendanceController extends BaseController
{
    /**
     * Renders Tablet Attendance Kiosk / Scanner Interface
     */
    public function index(): string
    {
        $kioskModel = new KioskDeviceModel();
        $registeredDevices = $kioskModel->where('status', 'ACTIVE')->findAll();

        $currentDevice = null;
        $isPaired = false;

        // 1. Check session for recent web pairing
        $sessionDeviceId = session()->get('kiosk_paired_device_id');
        if (!empty($sessionDeviceId)) {
            $device = $kioskModel->where('id', $sessionDeviceId)->where('status', 'ACTIVE')->first();
            if ($device) {
                $currentDevice = $device;
                $isPaired = true;
            }
        }

        // 2. Check edifies_kiosk_token cookie
        if (!$currentDevice) {
            $rawToken = $this->request->getCookie('edifies_kiosk_token');
            if (empty($rawToken)) {
                $cookieHeader = $this->request->getHeaderLine('Cookie');
                if (!empty($cookieHeader) && preg_match('/(?:^|;\s*)edifies_kiosk_token=([^;]+)/', $cookieHeader, $matches)) {
                    $rawToken = trim($matches[1]);
                }
            }

            if (!empty($rawToken)) {
                $tokenHash = hash('sha256', $rawToken);
                $device = $kioskModel->where('device_token_hash', $tokenHash)->where('status', 'ACTIVE')->first();
                if ($device) {
                    $currentDevice = $device;
                    $isPaired = true;
                }
            }
        }

        // Fallback to primary registered device for backward-compatibility if active
        if (!$currentDevice && !empty($registeredDevices)) {
            $currentDevice = $registeredDevices[0];
        }

        if ($currentDevice) {
            session()->set('kiosk_paired_device_id', $currentDevice['id']);
        }

        return view('attendance/kiosk', [
            'pageTitle'         => 'Staff Attendance Kiosk',
            'registeredDevices' => $registeredDevices,
            'currentDevice'     => $currentDevice,
            'isPaired'          => $isPaired,
            'serverTime'        => date('Y-m-d H:i:s'),
            'serverTimeIST'     => date('h:i:s A'),
            'serverDateIST'     => date('l, d F Y'),
        ]);
    }
}
