<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AttendancePhotoModel;
use App\Models\EmployeeModel;
use App\Services\AttendancePhotoService;
use Config\Database;

class AttendanceReportController extends BaseController
{
    protected EmployeeModel $employeeModel;
    protected AttendancePhotoModel $photoModel;
    protected AttendancePhotoService $photoService;
    protected $db;

    public function __construct()
    {
        $this->employeeModel = new EmployeeModel();
        $this->photoModel    = new AttendancePhotoModel();
        $this->photoService  = new AttendancePhotoService();
        $this->db            = Database::connect();
    }

    /**
     * Defense-in-depth: Enforce Administrator role on controller level.
     */
    protected function requireAdminRole(): bool
    {
        $role = session()->get('admin_role');
        return in_array($role, ['ADMIN', 'ROLE_ADMIN'], true);
    }

    protected function failForbiddenResponse(string $message = 'Access denied. Administrator privileges required.')
    {
        if ($this->request->isAJAX()) {
            return $this->response->setStatusCode(403)->setJSON([
                'status'  => 'error',
                'code'    => 'FORBIDDEN',
                'message' => $message,
            ]);
        }
        return $this->response->setStatusCode(403)->setBody(
            view('errors/html/error_403', ['message' => $message])
        );
    }

    /**
     * Main Attendance History & Reports View (Unified Dashboard).
     */
    public function index()
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Managers cannot view attendance history or reports.');
        }

        $tab = trim((string) ($this->request->getGet('tab') ?? 'daily'));
        if (!in_array($tab, ['daily', 'monthly', 'employee'], true)) {
            $tab = 'daily';
        }

        if ($tab === 'monthly') {
            return $this->monthly();
        } elseif ($tab === 'employee') {
            return $this->employee();
        }

        return $this->daily();
    }

    /**
     * Day-wise Attendance Report.
     */
    public function daily()
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Managers cannot view attendance history or reports.');
        }

        $schoolTz = new \DateTimeZone('Asia/Kolkata');
        $todayStr = (new \DateTimeImmutable('now', $schoolTz))->format('Y-m-d');

        $date       = trim((string) ($this->request->getGet('date') ?? $todayStr));
        $search     = trim((string) ($this->request->getGet('search') ?? ''));
        $department = trim((string) ($this->request->getGet('department') ?? ''));
        $status     = strtoupper(trim((string) ($this->request->getGet('status') ?? 'ALL')));

        // Validate date format Y-m-d
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = $todayStr;
        }

        // Build efficient query fetching active staff and matching records for the day
        $builder = $this->db->table('employees e')
            ->select('
                e.id AS employee_uuid,
                e.employee_id,
                e.full_name,
                e.department,
                e.designation,
                e.status AS employee_status,
                r.id AS record_id,
                r.work_date,
                r.check_in_time,
                r.check_out_time,
                r.status AS attendance_status,
                r.confidence_score,
                r.is_corrected,
                r.created_at AS record_created_at
            ')
            ->join('attendance_records r', "r.employee_id = e.id AND r.work_date = '{$date}'", 'left');

        if (!empty($search)) {
            $builder->groupStart()
                ->like('e.full_name', $search)
                ->orLike('e.employee_id', $search)
                ->groupEnd();
        }

        if (!empty($department) && $department !== 'ALL') {
            $builder->where('e.department', $department);
        }

        // Only show active employees OR employees with an attendance record for this day
        $builder->groupStart()
            ->where('e.status', 'ACTIVE')
            ->orWhere('r.id IS NOT NULL', null, false)
            ->groupEnd();

        $builder->orderBy('e.employee_id', 'ASC');
        $rows = $builder->get()->getResultArray();

        // Collect record IDs to batch-load photos
        $recordIds = [];
        foreach ($rows as $row) {
            if (!empty($row['record_id'])) {
                $recordIds[] = $row['record_id'];
            }
        }

        $photoMap = $this->photoModel->getPhotosByRecordIds($recordIds);

        // Process rows, compute status and irregular indicators
        $processedRecords = [];
        $totalStaff = count($rows);
        $metrics = [
            'total_staff'     => $totalStaff,
            'total_employees' => $totalStaff,
            'present'         => 0,
            'checked_in'      => 0,
            'checked_out'     => 0,
            'incomplete'      => 0,
            'absent'          => 0,
            'photo_count'     => 0,
        ];

        $isPastDate = ($date < $todayStr);

        foreach ($rows as $row) {
            $recId = $row['record_id'];
            $checkInPhoto  = $recId ? ($photoMap[$recId]['CHECK_IN'] ?? null) : null;
            $checkOutPhoto = $recId ? ($photoMap[$recId]['CHECK_OUT'] ?? null) : null;

            if ($checkInPhoto)  $metrics['photo_count']++;
            if ($checkOutPhoto) $metrics['photo_count']++;

            $displayStatus = 'ABSENT';
            $isIncomplete  = false;

            if (!empty($row['check_in_time'])) {
                $metrics['present']++;
                if (!empty($row['check_out_time'])) {
                    $displayStatus = 'CHECKED_OUT';
                    $metrics['checked_out']++;
                } else {
                    $displayStatus = 'CHECKED_IN';
                    $metrics['checked_in']++;
                    // If work date is in the past and check-out is missing, flag as INCOMPLETE
                    if ($isPastDate) {
                        $isIncomplete = true;
                        $metrics['incomplete']++;
                    }
                }
            } else {
                $metrics['absent']++;
            }

            if ($row['attendance_status'] === 'AUTO_CLOSED') {
                $displayStatus = 'AUTO_CLOSED';
                $isIncomplete  = true;
            }

            // Status filter matching
            if ($status !== 'ALL') {
                if ($status === 'PRESENT' && $displayStatus === 'ABSENT') continue;
                if ($status === 'CHECKED_IN' && $displayStatus !== 'CHECKED_IN') continue;
                if ($status === 'CHECKED_OUT' && $displayStatus !== 'CHECKED_OUT') continue;
                if ($status === 'INCOMPLETE' && !$isIncomplete) continue;
                if ($status === 'ABSENT' && $displayStatus !== 'ABSENT') continue;
            }

            $processedRecords[] = array_merge($row, [
                'display_status'  => $displayStatus,
                'is_incomplete'   => $isIncomplete,
                'check_in_photo'  => $checkInPhoto,
                'check_out_photo' => $checkOutPhoto,
            ]);
        }

        // Fetch distinct departments for filter dropdown
        $departments = $this->db->table('employees')
            ->select('department')
            ->distinct()
            ->where('department IS NOT NULL')
            ->orderBy('department', 'ASC')
            ->get()->getResultArray();
        $departmentList = array_filter(array_column($departments, 'department'));

        $data = [
            'tab'             => 'daily',
            'date'            => $date,
            'todayStr'        => $todayStr,
            'search'          => $search,
            'department'      => $department,
            'departmentList'  => $departmentList,
            'status'          => $status,
            'metrics'         => $metrics,
            'records'         => $processedRecords,
            'userRole'        => session()->get('admin_role'),
            'adminFullName'   => session()->get('admin_full_name') ?: 'Administrator',
        ];

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'  => 'success',
                'data'    => $data,
            ]);
        }

        return view('admin/attendance_reports/index', $data);
    }

    /**
     * Month-wise Attendance Report.
     */
    public function monthly()
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Managers cannot view attendance history or reports.');
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata'));
        $month  = (int) ($this->request->getGet('month') ?? $now->format('m'));
        $year   = (int) ($this->request->getGet('year') ?? $now->format('Y'));
        $search = trim((string) ($this->request->getGet('search') ?? ''));

        if ($month < 1 || $month > 12) $month = (int) $now->format('m');
        if ($year < 2020 || $year > 2035) $year = (int) $now->format('Y');

        $startDate = sprintf('%04d-%02d-01', $year, $month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $builder = $this->db->table('attendance_records r')
            ->select('
                r.id AS record_id,
                r.employee_id AS employee_uuid,
                r.work_date,
                r.check_in_time,
                r.check_out_time,
                r.status AS attendance_status,
                r.is_corrected,
                e.employee_id,
                e.full_name,
                e.department,
                e.designation
            ')
            ->join('employees e', 'e.id = r.employee_id')
            ->where('r.work_date >=', $startDate)
            ->where('r.work_date <=', $endDate);

        if (!empty($search)) {
            $builder->groupStart()
                ->like('e.full_name', $search)
                ->orLike('e.employee_id', $search)
                ->groupEnd();
        }

        $builder->orderBy('r.work_date', 'DESC')
                ->orderBy('e.employee_id', 'ASC');

        $records = $builder->get()->getResultArray();

        $recordIds = array_column($records, 'record_id');
        $photoMap  = $this->photoModel->getPhotosByRecordIds($recordIds);

        $daysInMonth = (int) date('t', strtotime($startDate));
        $monthName   = date('F', mktime(0, 0, 0, $month, 10));

        // Fetch distinct departments for filter dropdown
        $departments = $this->db->table('employees')
            ->select('department')
            ->distinct()
            ->where('department IS NOT NULL')
            ->orderBy('department', 'ASC')
            ->get()->getResultArray();
        $departmentList = array_filter(array_column($departments, 'department'));

        $processed = [];
        $metrics = [
            'days_in_month'           => $daysInMonth,
            'total_punches'           => count($records),
            'total_present_days'      => count($records),
            'total_completed_shifts'  => 0,
            'total_incomplete_shifts' => 0,
            'checked_out'             => 0,
            'incomplete'              => 0,
            'photo_count'             => 0,
            'photos_available'        => 0,
        ];

        foreach ($records as $r) {
            $recId = $r['record_id'];
            $cinPhoto  = $photoMap[$recId]['CHECK_IN'] ?? null;
            $coutPhoto = $photoMap[$recId]['CHECK_OUT'] ?? null;

            if ($cinPhoto)  $metrics['photos_available']++;
            if ($coutPhoto) $metrics['photos_available']++;

            $isIncomplete = false;
            if (empty($r['check_out_time']) || $r['attendance_status'] === 'AUTO_CLOSED') {
                $isIncomplete = true;
                $metrics['incomplete']++;
            } else {
                $metrics['checked_out']++;
            }

            $processed[] = array_merge($r, [
                'is_incomplete'   => $isIncomplete,
                'check_in_photo'  => $cinPhoto,
                'check_out_photo' => $coutPhoto,
            ]);
        }

        $metrics['total_completed_shifts']  = $metrics['checked_out'];
        $metrics['total_incomplete_shifts'] = $metrics['incomplete'];
        $metrics['photo_count']             = $metrics['photos_available'];

        $data = [
            'tab'            => 'monthly',
            'month'          => $month,
            'monthName'      => $monthName,
            'daysInMonth'    => $daysInMonth,
            'year'           => $year,
            'search'         => $search,
            'department'     => $department ?? '',
            'departmentList' => $departmentList,
            'startDate'      => $startDate,
            'endDate'        => $endDate,
            'metrics'        => $metrics,
            'records'        => $processed,
            'userRole'       => session()->get('admin_role'),
            'adminFullName'  => session()->get('admin_full_name') ?: 'Administrator',
        ];

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $data,
            ]);
        }

        return view('admin/attendance_reports/index', $data);
    }

    /**
     * Employee Chronological Attendance History.
     */
    public function employee(?string $id = null)
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Managers cannot view attendance history or reports.');
        }

        $empUuid = $id ?? trim((string) ($this->request->getGet('employee_id') ?? ''));

        $selectedEmployee = null;
        if (!empty($empUuid)) {
            // Find by UUID or employee_id string
            $selectedEmployee = $this->employeeModel->where('id', $empUuid)
                ->orWhere('employee_id', $empUuid)
                ->first();
        }

        $fromDate = trim((string) ($this->request->getGet('from_date') ?? date('Y-m-d', strtotime('-30 days'))));
        $toDate   = trim((string) ($this->request->getGet('to_date') ?? date('Y-m-d')));

        $records = [];
        $metrics = [
            'total_days'       => 0,
            'completed_shifts' => 0,
            'incomplete'       => 0,
            'photo_count'      => 0,
        ];

        if ($selectedEmployee) {
            $selectedEmployee['is_active'] = ($selectedEmployee['status'] === 'ACTIVE');
            $builder = $this->db->table('attendance_records r')
                ->select('
                    r.id AS record_id,
                    r.employee_id AS employee_uuid,
                    r.work_date,
                    r.check_in_time,
                    r.check_out_time,
                    r.status AS attendance_status,
                    r.is_corrected,
                    e.employee_id,
                    e.full_name,
                    e.department,
                    e.designation
                ')
                ->join('employees e', 'e.id = r.employee_id')
                ->where('r.employee_id', $selectedEmployee['id'])
                ->where('r.work_date >=', $fromDate)
                ->where('r.work_date <=', $toDate)
                ->orderBy('r.work_date', 'DESC');

            $records = $builder->get()->getResultArray();
            $recordIds = array_column($records, 'record_id');
            $photoMap = $this->photoModel->getPhotosByRecordIds($recordIds);

            foreach ($records as &$rec) {
                $recId = $rec['record_id'];
                $rec['check_in_photo']  = $photoMap[$recId]['CHECK_IN'] ?? null;
                $rec['check_out_photo'] = $photoMap[$recId]['CHECK_OUT'] ?? null;

                if ($rec['check_in_photo'])  $metrics['photo_count']++;
                if ($rec['check_out_photo']) $metrics['photo_count']++;

                $rec['is_incomplete'] = empty($rec['check_out_time']) || $rec['attendance_status'] === 'AUTO_CLOSED';
                if ($rec['is_incomplete']) {
                    $metrics['incomplete']++;
                } else {
                    $metrics['completed_shifts']++;
                }
                $metrics['total_days']++;

                // Set display_status
                if (!empty($rec['check_in_time']) && !empty($rec['check_out_time'])) {
                    $rec['display_status'] = 'CHECKED_OUT';
                } elseif (!empty($rec['check_in_time'])) {
                    $rec['display_status'] = 'CHECKED_IN';
                } else {
                    $rec['display_status'] = 'ABSENT';
                }

                // Compute total_work_minutes
                if (!empty($rec['check_in_time']) && !empty($rec['check_out_time'])) {
                    $inTs  = strtotime($rec['check_in_time']);
                    $outTs = strtotime($rec['check_out_time']);
                    $rec['total_work_minutes'] = ($outTs > $inTs) ? (int) round(($outTs - $inTs) / 60) : 0;
                } else {
                    $rec['total_work_minutes'] = 0;
                }
            }
            unset($rec);
        }

        // Staff list for selector
        $allStaff = $this->employeeModel
            ->select('id, employee_id, full_name, department, status')
            ->orderBy('employee_id', 'ASC')
            ->findAll();

        foreach ($allStaff as &$s) {
            $s['is_active'] = (($s['status'] ?? '') === 'ACTIVE');
        }
        unset($s);

        $data = [
            'tab'              => 'employee',
            'selectedEmployee' => $selectedEmployee,
            'allStaff'         => $allStaff,
            'allEmployees'     => $allStaff,
            'fromDate'         => $fromDate,
            'toDate'           => $toDate,
            'metrics'          => $metrics,
            'records'          => $records,
            'userRole'         => session()->get('admin_role'),
            'adminFullName'    => session()->get('admin_full_name') ?: 'Administrator',
        ];

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status' => 'success',
                'data'   => $data,
            ]);
        }

        return view('admin/attendance_reports/index', $data);
    }

    /**
     * Protected Photo Retrieval Endpoint: GET /admin/attendance/photos/(:segment)
     * Admin only. Validates record, validates physical file, enforces anti-traversal security.
     */
    public function viewPhoto(string $id)
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Administrator privileges required to inspect attendance photos.');
        }

        $photo = $this->photoModel->find($id);
        if (!$photo) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Attendance photograph record not found.',
            ]);
        }

        $realPath = $this->photoService->resolveSecurePath($photo['storage_path']);
        if (!$realPath || !file_exists($realPath)) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'Photograph file is missing or has been purged from private storage.',
            ]);
        }

        $fileContent = file_get_contents($realPath);
        if ($fileContent === false) {
            return $this->response->setStatusCode(500)->setJSON([
                'status'  => 'error',
                'message' => 'Failed to read photograph file from private storage.',
            ]);
        }

        return $this->response
            ->setHeader('Content-Type', 'image/jpeg')
            ->setHeader('Content-Length', (string) strlen($fileContent))
            ->setHeader('Content-Disposition', 'inline; filename="attendance-photo-' . esc($id) . '.jpg"')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->setBody($fileContent);
    }

    /**
     * Protected Event Photo Retrieval Endpoint: GET /admin/attendance/events/(:segment)/photo
     * Looks up photo associated with a specific attendance event UUID.
     */
    public function viewEventPhoto(string $eventId)
    {
        if (!$this->requireAdminRole()) {
            return $this->failForbiddenResponse('Access denied. Administrator privileges required to inspect attendance photos.');
        }

        $photo = $this->photoModel->findByEventId($eventId);
        if (!$photo) {
            return $this->response->setStatusCode(404)->setJSON([
                'status'  => 'error',
                'message' => 'No photograph is associated with the requested attendance event.',
            ]);
        }

        return $this->viewPhoto($photo['id']);
    }
}
