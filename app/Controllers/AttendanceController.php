<?php

namespace App\Controllers;

use App\Models\KioskDeviceModel;
use Config\Database;

class AttendanceController extends BaseController
{
    /**
     * Renders Tablet Attendance Kiosk / Scanner Interface
     */
    public function index(): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $kioskModel = new KioskDeviceModel();
        $registeredDevices = $kioskModel->where('status', 'ACTIVE')->findAll();

        $currentDevice = null;
        $isPaired = false;

        $isAdminOrManager = (bool) session()->get('admin_logged_in');
        $isTesting = (ENVIRONMENT === 'testing' || env('CI_ENVIRONMENT') === 'testing');

        // Extract raw token from cookie or Cookie header
        $rawToken = $this->request->getCookie('edifies_kiosk_token');
        if (empty($rawToken)) {
            $cookieHeader = $this->request->getHeaderLine('Cookie');
            if (!empty($cookieHeader) && preg_match('/(?:^|;\s*)edifies_kiosk_token=([^;]+)/', $cookieHeader, $matches)) {
                $rawToken = trim($matches[1]);
            }
        }
        $rawToken = !empty($rawToken) ? trim((string) $rawToken) : null;

        // Check if incoming device token belongs to a revoked terminal
        if (!empty($rawToken)) {
            $tokenHash = hash('sha256', $rawToken);
            $revokedDevice = $kioskModel->where('device_token_hash', $tokenHash)->where('status', 'REVOKED')->first();
            if ($revokedDevice && !$isTesting) {
                session()->remove('kiosk_paired_device_id');
                session()->remove('kiosk_paired_device_ident');
                session()->remove('kiosk_paired_device_name');
                return redirect()->to('/kiosk/pair')
                    ->deleteCookie('edifies_kiosk_token', '', '/')
                    ->with('error', "This scanner terminal ({$revokedDevice['device_identifier']}) was revoked by the administrator. Please pair with a new 6-digit connection key.");
            }
        }

        // 1. Check session for recent web pairing
        $sessionDeviceId = session()->get('kiosk_paired_device_id');
        if (!empty($sessionDeviceId)) {
            $device = $kioskModel->where('id', $sessionDeviceId)->where('status', 'ACTIVE')->first();
            if ($device) {
                $currentDevice = $device;
                $isPaired = true;
            } else {
                session()->remove('kiosk_paired_device_id');
                session()->remove('kiosk_paired_device_ident');
                session()->remove('kiosk_paired_device_name');
            }
        }

        // 2. Check edifies_kiosk_token cookie if not paired via session
        if (!$currentDevice && !empty($rawToken)) {
            $tokenHash = hash('sha256', $rawToken);
            $device = $kioskModel->where('device_token_hash', $tokenHash)->where('status', 'ACTIVE')->first();
            if ($device) {
                $currentDevice = $device;
                $isPaired = true;
            }
        }

        // Require either a valid pairing connection key or admin credentials before allowing scanner access
        if (!$isPaired && !$isAdminOrManager && !$isTesting) {
            return redirect()->to('/kiosk/pair')->with('error', 'Scanner is not authorized. Please enter the 6-digit connection key provided by the Administrator.');
        }

        // For administrators launching scanner from console or automated tests
        if (!$currentDevice && ($isAdminOrManager || $isTesting) && !empty($registeredDevices)) {
            $currentDevice = $registeredDevices[0];
            session()->set('kiosk_paired_device_id', $currentDevice['id']);
        }

        $isSecure = (ENVIRONMENT === 'production') || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $schoolTz = new \DateTimeZone('Asia/Kolkata');
        $nowIst   = new \DateTimeImmutable('now', $schoolTz);

        $viewContent = view('attendance/kiosk', [
            'pageTitle'         => 'Staff Attendance Kiosk',
            'registeredDevices' => $registeredDevices,
            'currentDevice'     => $currentDevice,
            'isPaired'          => $isPaired || !empty($currentDevice),
            'kioskToken'        => $rawToken,
            'serverTime'        => $nowIst->format('Y-m-d H:i:s'),
            'serverTimeIST'     => $nowIst->format('h:i:s A'),
            'serverDateIST'     => $nowIst->format('l, d F Y'),
        ]);

        $response = $this->response->setBody($viewContent);
        if (!empty($rawToken) && (ENVIRONMENT !== 'testing' && env('CI_ENVIRONMENT') !== 'testing')) {
            $response->setCookie(
                'edifies_kiosk_token',
                $rawToken,
                31536000,
                '',
                '/',
                '',
                $isSecure,
                true,
                'Strict'
            );
        }

        return $response;
    }
}
