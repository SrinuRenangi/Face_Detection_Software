<?php

namespace App\Controllers;

use Config\Database;

class Home extends BaseController
{
    /**
     * Central Access Gateway and Role Dispatch Entry Point
     */
    public function index(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        $session = session();

        // 1. Automatic role-based redirect for active human administrative sessions
        if ($session->get('admin_logged_in')) {
            $role = (string) $session->get('admin_role');
            if (in_array($role, ['ROLE_SUPER_ADMIN', 'SUPER_ADMIN'], true)) {
                return redirect()->to('/developer/control');
            }
            if (in_array($role, ['ROLE_MANAGER', 'MANAGER'], true)) {
                return redirect()->to('/manager/dashboard');
            }
            if (in_array($role, ['ROLE_SCANNER', 'SCANNER'], true)) {
                return redirect()->to('/scanner');
            }
            return redirect()->to('/admin/dashboard');
        }

        // 2. Database connection status check for diagnostic display
        $db = Database::connect();
        $dbConnected = false;
        $dbPlatform = 'MySQL/MariaDB';

        try {
            $db->initialize();
            if ($db->connID) {
                $dbConnected = true;
                $dbPlatform = $db->getPlatform() . ' ' . $db->getVersion();
            }
        } catch (\Throwable $e) {
            $dbConnected = false;
        }

        // 3. Inspect if client has a paired kiosk device cookie
        $isPairedDevice = false;
        $pairedDeviceInfo = null;
        $request = $this->request ?? service('request');
        $rawDeviceToken = $request ? $request->getCookie('edifies_kiosk_token') : null;

        if (empty($rawDeviceToken) && $request) {
            $cookieHeader = $request->getHeaderLine('Cookie');
            if (!empty($cookieHeader) && preg_match('/(?:^|;\s*)edifies_kiosk_token=([^;]+)/', $cookieHeader, $matches)) {
                $rawDeviceToken = trim($matches[1]);
            }
        }

        if (!empty($rawDeviceToken) && $dbConnected) {
            $tokenHash = hash('sha256', $rawDeviceToken);
            $deviceRow = $db->table('kiosk_devices')
                ->where('device_token_hash', $tokenHash)
                ->where('status', 'ACTIVE')
                ->get()
                ->getRowArray();

            if ($deviceRow) {
                $isPairedDevice = true;
                $pairedDeviceInfo = $deviceRow;
            }
        }

        return view('home', [
            'pageTitle'        => 'Staff Attendance Management System',
            'dbConnected'      => $dbConnected,
            'dbPlatform'       => $dbPlatform,
            'phpVersion'       => PHP_VERSION,
            'serverTime'       => date('Y-m-d H:i:s T'),
            'isPairedDevice'   => $isPairedDevice,
            'pairedDeviceInfo' => $pairedDeviceInfo,
        ]);
    }
}
