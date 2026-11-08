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

        // Time calculations in Asia/Kolkata
        $tz = new \DateTimeZone('Asia/Kolkata');
        $now = new \DateTime('now', $tz);
        $todayDate = $now->format('Y-m-d');
        $currentHour = (int) $now->format('H');
        $currentMinute = (int) $now->format('i');
        $currentMinutes = $currentHour * 60 + $currentMinute;

        // Cutoff thresholds (12:00 PM = 720 min, 7:00 PM = 1140 min)
        $isPast12PM = ($currentMinutes >= 720);
        $isPast7PM  = ($currentMinutes >= 1140);

        // 1. Missing Check-Ins: Active staff without any check-in record for today
        $missingCheckIns = $db->query("
            SELECT e.id, e.employee_id, e.full_name, e.department, e.designation
            FROM employees e
            LEFT JOIN attendance_records a ON a.employee_id = e.id AND a.work_date = ?
            WHERE e.status = 'ACTIVE' AND a.id IS NULL
            ORDER BY e.full_name ASC
        ", [$todayDate])->getResultArray();

        // 2. Missing Sign-Offs: Active staff who checked in today but have not checked out
        $missingSignOffs = $db->query("
            SELECT e.id, e.employee_id, e.full_name, e.department, e.designation, a.check_in_time
            FROM employees e
            INNER JOIN attendance_records a ON a.employee_id = e.id AND a.work_date = ?
            WHERE e.status = 'ACTIVE' AND (a.check_out_time IS NULL OR a.status = 'CHECKED_IN')
            ORDER BY a.check_in_time ASC
        ", [$todayDate])->getResultArray();

        return view('admin/dashboard', [
            'pageTitle'        => 'Administrator Dashboard',
            'activeEmployees'  => $activeEmployees,
            'activeDevices'    => $activeDevices,
            'todayPunches'     => $todayPunches,
            'recentAudits'     => $recentAudits,
            'missingCheckIns'  => $missingCheckIns,
            'missingSignOffs'  => $missingSignOffs,
            'isPast12PM'       => $isPast12PM,
            'isPast7PM'        => $isPast7PM,
            'serverTimeIST'    => $now->format('g:i A, d M Y'),
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

