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

        $recentAuditsRaw = $auditModel
            ->whereNotIn('action', ['CLIENT_UUID_PAYLOAD_MISMATCH', 'DEVICE_SEQUENCE_COLLISION', 'RETIRED_EPOCH_EVENT_HELD', 'DEVICE_SEQUENCE_OUT_OF_ORDER'])
            ->where('actor_username !=', 'superadmin')
            ->where('actor_username !=', 'ROLE_SUPER_ADMIN')
            ->notLike('details', 'superadmin', 'both')
            ->notLike('details', 'ROLE_SUPER_ADMIN', 'both')
            ->orderBy('created_at', 'DESC')
            ->findAll(8);

        $recentAudits = [];
        foreach ($recentAuditsRaw as $audit) {
            $recentAudits[] = $this->formatAuditEntry($audit);
        }

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

    /**
     * Sanitizes and humanizes raw technical audit entries for administrative review.
     * Prevents technical implementation leakage (internal UUIDs, raw JSON dumps, vector dimensions, pairing IDs).
     */
    protected function formatAuditEntry(array $audit): array
    {
        $action = $audit['action'] ?? '';
        $detailsRaw = trim((string) ($audit['details'] ?? ''));
        $humanDetails = $detailsRaw;
        $badgeClass = 'badge-neutral';
        $actionLabel = ucwords(strtolower(str_replace('_', ' ', $action)));

        $actor = $audit['actor_username'] ?? 'System';
        if ($actor === 'admin') {
            $actorDisplay = 'Administrator';
        } elseif (in_array($actor, ['KIOSK_SECURITY', 'KIOSK_ADMIN', 'SYSTEM_SECURITY'], true)) {
            $actorDisplay = 'Kiosk Terminal';
        } else {
            $actorDisplay = esc($actor);
        }

        // Format timestamp in Asia/Kolkata
        $tz = new \DateTimeZone('Asia/Kolkata');
        try {
            $dt = new \DateTimeImmutable($audit['created_at'], $tz);
            $formattedTime = $dt->format('d M Y, h:i A');
        } catch (\Throwable $e) {
            $formattedTime = $audit['created_at'];
        }

        // Try decoding JSON payload
        $json = json_decode($detailsRaw, true);
        if (is_array($json)) {
            switch ($action) {
                case 'EMPLOYEE_CREATED':
                    $name = $json['full_name'] ?? 'Staff Member';
                    $empId = $json['employee_id'] ?? '';
                    $dept = $json['department'] ?? '';
                    $desig = $json['designation'] ?? '';
                    $humanDetails = "Added staff member <strong>" . esc($name) . "</strong> (" . esc($empId) . ")";
                    if (!empty($dept)) {
                        $humanDetails .= " &bull; " . esc($dept) . (!empty($desig) ? " / " . esc($desig) : '');
                    }
                    $actionLabel = 'Staff Added';
                    $badgeClass = 'badge-success';
                    break;

                case 'EMPLOYEE_FACE_ENROLLED':
                    $name = $json['full_name'] ?? 'Staff Member';
                    $empId = $json['employee_id'] ?? '';
                    $humanDetails = "Enrolled facial recognition biometrics for <strong>" . esc($name) . "</strong> (" . esc($empId) . ")";
                    $actionLabel = 'Face Enrolled';
                    $badgeClass = 'badge-primary';
                    break;

                case 'EMPLOYEE_UPDATED':
                    $name = $json['full_name'] ?? ($json['changes']['full_name']['to'] ?? 'Staff Member');
                    $empId = $json['employee_id'] ?? '';
                    $humanDetails = "Updated profile details for <strong>" . esc($name) . "</strong>" . (!empty($empId) ? " (" . esc($empId) . ")" : '');
                    $actionLabel = 'Staff Updated';
                    $badgeClass = 'badge-info';
                    break;

                case 'EMPLOYEE_ACTIVATED':
                    $name = $json['full_name'] ?? 'Staff Member';
                    $empId = $json['employee_id'] ?? '';
                    $humanDetails = "Activated staff profile for <strong>" . esc($name) . "</strong> (" . esc($empId) . ")";
                    $actionLabel = 'Staff Activated';
                    $badgeClass = 'badge-success';
                    break;

                case 'EMPLOYEE_DEACTIVATED':
                    $name = $json['full_name'] ?? 'Staff Member';
                    $empId = $json['employee_id'] ?? '';
                    $humanDetails = "Deactivated staff profile for <strong>" . esc($name) . "</strong> (" . esc($empId) . ")";
                    $actionLabel = 'Staff Deactivated';
                    $badgeClass = 'badge-warning';
                    break;

                case 'KIOSK_DEVICE_CREATED':
                    $devName = $json['device_name'] ?? 'Scanner';
                    $devIdent = $json['device_identifier'] ?? '';
                    $humanDetails = "Registered attendance terminal <strong>" . esc($devName) . "</strong>" . (!empty($devIdent) ? " (" . esc($devIdent) . ")" : '');
                    $actionLabel = 'Scanner Registered';
                    $badgeClass = 'badge-info';
                    break;

                case 'KIOSK_PAIRING_SUCCESS':
                    $devName = $json['device_name'] ?? 'Authorized Kiosk Scanner';
                    $devIdent = $json['device_identifier'] ?? '';
                    $humanDetails = "Paired scanner terminal <strong>" . esc($devName) . "</strong>" . (!empty($devIdent) ? " (" . esc($devIdent) . ")" : '');
                    $actionLabel = 'Scanner Paired';
                    $badgeClass = 'badge-success';
                    break;

                case 'KIOSK_DEVICE_REVOKED':
                    $devName = $json['device_name'] ?? 'Scanner';
                    $humanDetails = "Revoked access for terminal <strong>" . esc($devName) . "</strong>";
                    $actionLabel = 'Scanner Revoked';
                    $badgeClass = 'badge-danger';
                    break;

                default:
                    if (isset($json['message'])) {
                        $humanDetails = esc($json['message']);
                    } elseif (isset($json['reason'])) {
                        $humanDetails = esc($json['reason']);
                    } else {
                        // Extract high-level key-values without sensitive UUIDs or internal IDs
                        $cleanParts = [];
                        foreach ($json as $k => $v) {
                            if (str_contains($k, 'id') || str_contains($k, 'token') || str_contains($k, 'hash') || str_contains($k, 'channel') || str_contains($k, 'dimensions')) {
                                continue;
                            }
                            if (is_scalar($v)) {
                                $cleanParts[] = ucwords(str_replace('_', ' ', $k)) . ': ' . esc($v);
                            }
                        }
                        $humanDetails = !empty($cleanParts) ? implode(' &bull; ', $cleanParts) : 'System event logged';
                    }
                    break;
            }
        } else {
            if ($action === 'ADMIN_LOGIN_SUCCESS') {
                $humanDetails = 'Administrator signed in';
                $actionLabel = 'Sign In';
                $badgeClass = 'badge-neutral';
            } elseif ($action === 'ADMIN_LOGOUT') {
                $humanDetails = 'Administrative session ended';
                $actionLabel = 'Sign Out';
                $badgeClass = 'badge-neutral';
            } elseif ($action === 'ADMIN_LOGIN_FAILED') {
                $humanDetails = 'Failed sign-in attempt';
                $actionLabel = 'Login Failed';
                $badgeClass = 'badge-danger';
            }
        }

        return [
            'created_at'    => $formattedTime,
            'actor_display' => $actorDisplay,
            'action'        => $action,
            'action_label'  => $actionLabel,
            'badge_class'   => $badgeClass,
            'details_html'  => $humanDetails,
        ];
    }
}

