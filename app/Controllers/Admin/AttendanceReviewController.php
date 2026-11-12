<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminAuditLogModel;
use App\Models\AttendanceEventModel;
use App\Models\AttendanceReviewDecisionModel;
use App\Services\AttendanceSyncService;
use Config\Database;

class AttendanceReviewController extends BaseController
{
    protected $db;
    protected AttendanceEventModel $eventModel;
    protected AttendanceReviewDecisionModel $decisionModel;
    protected AttendanceSyncService $syncService;

    public function __construct()
    {
        $this->db            = Database::connect();
        $this->eventModel    = new AttendanceEventModel();
        $this->decisionModel = new AttendanceReviewDecisionModel();
        $this->syncService   = new AttendanceSyncService();
    }

    /**
     * List held events pending review and review history
     */
    public function index()
    {
        $role = session()->get('admin_role');
        if ($role !== 'ROLE_ADMIN' && $role !== 'ADMIN') {
            return $this->response->setStatusCode(403)->setBody(view('errors/html/error_403', [
                'message' => 'Access denied. You do not have permission to access the Attendance Review Queue.',
            ]));
        }

        $session = session();
        $userRole = $session->get('admin_role') ?? 'ADMIN';
        $userName = $session->get('admin_user_name') ?? 'Administrator';

        $filter = strtolower(trim((string) ($this->request->getGet('filter') ?? 'pending')));
        if (!in_array($filter, ['pending', 'resolved', 'all'], true)) {
            $filter = 'pending';
        }

        // Base query with metadata
        $baseQuery = $this->db->table('attendance_events e')
            ->select('
                e.*, 
                emp.employee_id as emp_code, 
                emp.full_name as emp_name, 
                emp.department as emp_dept,
                kd.device_name,
                kd.device_identifier,
                ard.id as decision_id,
                ard.decision as review_decision,
                ard.reviewer_username,
                ard.review_reason,
                ard.decided_at,
                ard.applied_record_id
            ')
            ->join('employees emp', 'emp.id = e.employee_id', 'left')
            ->join('kiosk_devices kd', 'kd.id = e.device_id', 'left')
            ->join('attendance_review_decisions ard', 'ard.event_id = e.id', 'left')
            ->where('e.processing_status', 'REVIEW_REQUIRED');

        // Total count
        $totalCount = (clone $baseQuery)->countAllResults(false);

        // Pending count: REVIEW_REQUIRED with NO decision in attendance_review_decisions
        $pendingCount = (clone $baseQuery)->where('ard.id IS NULL')->countAllResults(false);

        // Resolved count: Has decision in attendance_review_decisions
        $resolvedCount = (clone $baseQuery)->where('ard.id IS NOT NULL')->countAllResults(false);

        // Apply active filter to results
        if ($filter === 'pending') {
            $baseQuery->where('ard.id IS NULL');
        } elseif ($filter === 'resolved') {
            $baseQuery->where('ard.id IS NOT NULL');
        }

        $events = $baseQuery->orderBy('e.server_received_at', 'DESC')
            ->get()
            ->getResultArray();

        $schoolTz = new \DateTimeZone('Asia/Kolkata');
        $utcTz    = new \DateTimeZone('UTC');

        // Calculate derived event time and local work date for each event
        foreach ($events as &$event) {
            $event['derived_time'] = $this->syncService->calculateAnchorDerivedUtc($event);
            $effectiveUtc = new \DateTimeImmutable($event['derived_time'], $utcTz);
            $localDt      = $effectiveUtc->setTimezone($schoolTz);
            $event['work_date']        = $localDt->format('Y-m-d');
            $event['local_punch_time'] = $localDt->format('Y-m-d H:i:s');
            // Effective status derived from review decision
            if (!empty($event['review_decision'])) {
                $event['effective_status'] = $event['review_decision']; // APPROVED or REJECTED
            } else {
                $event['effective_status'] = 'PENDING';
            }
        }

        return view('admin/attendance_review/index', [
            'pageTitle'     => 'Attendance Event Review Queue',
            'events'        => $events,
            'filter'        => $filter,
            'pendingCount'  => $pendingCount,
            'resolvedCount' => $resolvedCount,
            'totalCount'    => $totalCount,
            'userRole'      => $userRole,
            'userName'      => $userName,
        ]);
    }

    /**
     * Inspect individual event details, anchor telemetry, and audit trail
     */
    public function show(string $eventId)
    {
        $session = session();
        $userRole = $session->get('admin_role') ?? 'ADMIN';
        $userName = $session->get('admin_user_name') ?? 'Administrator';

        $event = $this->db->table('attendance_events e')
            ->select('
                e.*, 
                emp.employee_id as emp_code, 
                emp.full_name as emp_name, 
                emp.department as emp_dept,
                kd.device_name,
                kd.device_identifier,
                ard.id as decision_id,
                ard.decision as review_decision,
                ard.reviewer_username,
                ard.review_reason,
                ard.decided_at,
                ard.applied_record_id,
                kta.server_utc_time as anchor_server_utc,
                kta.expires_at as anchor_expires_at,
                kta.browser_session_hash as anchor_session_hash
            ')
            ->join('employees emp', 'emp.id = e.employee_id', 'left')
            ->join('kiosk_devices kd', 'kd.id = e.device_id', 'left')
            ->join('attendance_review_decisions ard', 'ard.event_id = e.id', 'left')
            ->join('kiosk_time_anchors kta', 'kta.id = e.server_anchor_id', 'left')
            ->where('e.id', $eventId)
            ->get()
            ->getRowArray();

        if (!$event) {
            return redirect()->to('/admin/attendance/review')->with('error', 'Attendance event not found.');
        }

        $event['derived_time'] = $this->syncService->calculateAnchorDerivedUtc($event);

        $schoolTz = new \DateTimeZone('Asia/Kolkata');
        $utcTz    = new \DateTimeZone('UTC');
        $effectiveUtc = new \DateTimeImmutable($event['derived_time'], $utcTz);
        $localDt      = $effectiveUtc->setTimezone($schoolTz);
        $event['work_date']        = $localDt->format('Y-m-d');
        $event['local_punch_time'] = $localDt->format('Y-m-d H:i:s');
        $event['effective_status'] = !empty($event['review_decision']) ? $event['review_decision'] : 'PENDING';

        // Fetch audit logs for this event (Ghost mode: superadmin traces excluded)
        $auditLogs = $this->db->table('admin_audit_logs')
            ->groupStart()
                ->where('target_id', $eventId)
                ->orWhere('details LIKE', '%' . $eventId . '%')
            ->groupEnd()
            ->where('actor_username !=', 'superadmin')
            ->where('actor_username !=', 'ROLE_SUPER_ADMIN')
            ->notLike('details', 'superadmin', 'both')
            ->orderBy('created_at', 'DESC')
            ->get()
            ->getResultArray();

        return view('admin/attendance_review/show', [
            'pageTitle' => 'Event Inspection & Decision',
            'event'     => $event,
            'auditLogs' => $auditLogs,
            'userRole'  => $userRole,
            'userName'  => $userName,
        ]);
    }

    /**
     * Approve a held event
     */
    public function approve(string $eventId)
    {
        $session  = session();
        $userId   = $session->get('admin_user_id') ?? 'usr-admin';
        $userName = $session->get('admin_user_name') ?? 'Administrator';
        $reason   = trim((string) $this->request->getPost('reason'));

        if (empty($reason) || strlen($reason) < 5) {
            return redirect()->back()->with('error', 'A substantive review reason (min 5 characters) is required for approval.');
        }

        $result = $this->syncService->approveHeldEvent($eventId, $userId, $userName, $reason);

        if ($result['status'] === 'APPROVED') {
            return redirect()->to('/admin/attendance/review/' . $eventId)->with('success', 'Event approved successfully. Daily aggregate updated.');
        }

        return redirect()->back()->with('error', $result['error'] ?? 'Failed to approve event.');
    }

    /**
     * Reject a held event
     */
    public function reject(string $eventId)
    {
        $session  = session();
        $userId   = $session->get('admin_user_id') ?? 'usr-admin';
        $userName = $session->get('admin_user_name') ?? 'Administrator';
        $reason   = trim((string) $this->request->getPost('reason'));

        if (empty($reason) || strlen($reason) < 5) {
            return redirect()->back()->with('error', 'A substantive review reason (min 5 characters) is required for rejection.');
        }

        $result = $this->syncService->rejectHeldEvent($eventId, $userId, $userName, $reason);

        if ($result['status'] === 'REJECTED') {
            return redirect()->to('/admin/attendance/review/' . $eventId)->with('success', 'Event rejected. Daily attendance aggregate remains unchanged.');
        }

        return redirect()->back()->with('error', $result['error'] ?? 'Failed to reject event.');
    }
}

