<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Models\AttendanceRecordModel;
use App\Models\AdminAuditLogModel;
use Config\Database;

class DashboardController extends BaseController
{
    public function index(): string|\CodeIgniter\HTTP\ResponseInterface
    {
        $role = session()->get('admin_role');
        if ($role !== 'ROLE_ADMIN' && $role !== 'ADMIN') {
            return $this->response->setStatusCode(403)->setBody(view('errors/html/error_403', [
                'message' => 'Access denied. You do not have permission to access the Administrator Dashboard.',
            ]));
        }

        $employeeModel   = new EmployeeModel();
        $kioskModel      = new KioskDeviceModel();
        $attendanceModel = new AttendanceRecordModel();
        $auditModel      = new AdminAuditLogModel();

        $activeEmployees = $employeeModel->countActive();
        $activeDevices   = $kioskModel->countActive();
        $todayPunches    = $attendanceModel->countTodayPunches();

        $recentAudits = $auditModel->orderBy('created_at', 'DESC')->findAll(6);

        $db = Database::connect();

        return view('admin/dashboard', [
            'pageTitle'        => 'Administrator Dashboard',
            'activeEmployees'  => $activeEmployees,
            'activeDevices'    => $activeDevices,
            'todayPunches'     => $todayPunches,
            'recentAudits'     => $recentAudits,
            'adminFullName'    => session()->get('admin_full_name'),
            'adminUsername'    => session()->get('admin_username'),
            'adminRole'        => session()->get('admin_role'),
            'dbPlatform'       => $db->getPlatform() . ' ' . $db->getVersion(),
            'phpVersion'       => PHP_VERSION,
            'ciVersion'        => \CodeIgniter\CodeIgniter::CI_VERSION,
            'serverTime'       => date('Y-m-d H:i:s T'),
        ]);
    }
}

