<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance History &amp; Reports &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .report-header-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid var(--border-color, #e2e8f0);
            padding-bottom: 0.5rem;
            flex-wrap: wrap;
        }
        .report-tab-btn {
            padding: 0.6rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-sm, 6px);
            text-decoration: none;
            color: var(--text-muted, #64748b);
            background: transparent;
            border: 1px solid transparent;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .report-tab-btn:hover {
            color: var(--primary, #2563eb);
            background: #f1f5f9;
        }
        .report-tab-btn.active {
            color: var(--primary, #2563eb);
            background: #eff6ff;
            border-color: #bfdbfe;
        }
        .filter-card {
            background: var(--surface, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: var(--radius-md, 8px);
            padding: 1.25rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .filter-form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            align-items: flex-end;
        }
        .summary-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        .summary-kpi-card {
            background: var(--surface, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: var(--radius-md, 8px);
            padding: 1rem;
            text-align: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        .summary-kpi-val {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-main, #1e293b);
            line-height: 1.2;
        }
        .summary-kpi-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted, #64748b);
            margin-top: 0.25rem;
            font-weight: 600;
        }
        .report-table-wrapper {
            background: var(--surface, #ffffff);
            border: 1px solid var(--border-color, #e2e8f0);
            border-radius: var(--radius-md, 8px);
            overflow-x: auto;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
            text-align: left;
        }
        .report-table th {
            background: #f8fafc;
            color: var(--text-muted, #475569);
            font-weight: 700;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color, #e2e8f0);
            white-space: nowrap;
        }
        .report-table td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }
        .report-table tr:hover {
            background: #f8fafc;
        }
        .report-table tr.row-incomplete {
            background: #fffbeb;
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-checked-in { background: #dbeafe; color: #1e40af; }
        .badge-checked-out { background: #dcfce7; color: #166534; }
        .badge-incomplete { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
        .badge-absent { background: #f1f5f9; color: #64748b; }
        .photo-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.6rem;
            border-radius: var(--radius-sm, 4px);
            font-size: 0.8rem;
            font-weight: 600;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1d4ed8;
            cursor: pointer;
            transition: all 0.15s;
        }
        .photo-btn:hover {
            background: #dbeafe;
            color: #1e40af;
        }
        .photo-btn-muted {
            color: #94a3b8;
            border-color: transparent;
            background: transparent;
            cursor: default;
            font-size: 0.8rem;
        }
        /* Photo Inspector Modal */
        .photo-modal-backdrop {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(4px);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .photo-modal-card {
            background: #ffffff;
            border-radius: 12px;
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
            animation: modalPop 0.2s ease-out;
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .photo-modal-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .photo-modal-body {
            padding: 1.25rem;
            text-align: center;
            background: #0f172a;
        }
        .photo-modal-img {
            max-width: 100%;
            max-height: 400px;
            border-radius: 8px;
            object-fit: contain;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
        }
        .photo-modal-footer {
            padding: 0.75rem 1.25rem;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.85rem;
            color: #64748b;
        }
        /* Responsive Card Views for Mobile */
        @media (max-width: 768px) {
            .hide-mobile { display: none !important; }
            .report-table th, .report-table td { padding: 0.5rem 0.6rem; font-size: 0.82rem; }
        }
    </style>
</head>
<body>
    <?= view('admin/common/navbar', [
        'activeNav'     => 'reports',
        'userRole'      => session()->get('admin_role') ?? 'ROLE_ADMIN',
        'adminFullName' => session()->get('admin_full_name') ?? 'Administrator'
    ]) ?>

    <main class="container" style="flex: 1; padding: 1.5rem 1rem 3rem;">
        <!-- Page Title & Intro -->
        <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--text-main); margin: 0;">
                    Attendance History &amp; Reports
                </h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    Day-wise verification, monthly summary, employee search, and audit photographs.
                </p>
            </div>
            <div style="font-size: 0.85rem; color: var(--text-muted);">
                Logged in as <strong><?= esc($adminFullName) ?></strong> (<?= esc($userRole) ?>)
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="report-header-tabs">
            <a href="/admin/attendance/reports?tab=daily<?= !empty($date) ? '&date='.esc($date) : '' ?>" class="report-tab-btn <?= ($tab === 'daily') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Day-Wise Attendance</span>
            </a>
            <a href="/admin/attendance/reports?tab=monthly" class="report-tab-btn <?= ($tab === 'monthly') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                </svg>
                <span>Month-Wise Summary</span>
            </a>
            <a href="/admin/attendance/reports?tab=employee" class="report-tab-btn <?= ($tab === 'employee') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Employee Chronology</span>
            </a>
        </div>

        <?php if ($tab === 'daily'): ?>
            <!-- ========================================== -->
            <!-- TAB 1: DAY-WISE ATTENDANCE VIEW           -->
            <!-- ========================================== -->
            <div class="filter-card">
                <form method="GET" action="/admin/attendance/reports" class="filter-form-grid">
                    <input type="hidden" name="tab" value="daily">
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Date
                        </label>
                        <div style="display: flex; gap: 0.35rem;">
                            <a href="/admin/attendance/reports?tab=daily&date=<?= date('Y-m-d', strtotime($date . ' -1 day')) ?>" class="btn btn-secondary" style="padding: 0.4rem 0.6rem;" title="Previous Day">&laquo;</a>
                            <input type="date" name="date" value="<?= esc($date) ?>" class="form-control" style="flex: 1;" onchange="this.form.submit()">
                            <a href="/admin/attendance/reports?tab=daily&date=<?= date('Y-m-d', strtotime($date . ' +1 day')) ?>" class="btn btn-secondary" style="padding: 0.4rem 0.6rem;" title="Next Day">&raquo;</a>
                        </div>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Staff Search
                        </label>
                        <input type="text" name="search" value="<?= esc($search) ?>" placeholder="Name or Employee ID..." class="form-control">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Department
                        </label>
                        <select name="department" class="form-control" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentList as $dept): ?>
                                <option value="<?= esc($dept) ?>" <?= ($department === $dept) ? 'selected' : '' ?>><?= esc($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Status Filter
                        </label>
                        <select name="status" class="form-control" onchange="this.form.submit()">
                            <option value="ALL" <?= ($status === 'ALL') ? 'selected' : '' ?>>All Staff Statuses</option>
                            <option value="PRESENT" <?= ($status === 'PRESENT') ? 'selected' : '' ?>>Present (Any)</option>
                            <option value="CHECKED_IN" <?= ($status === 'CHECKED_IN') ? 'selected' : '' ?>>Checked In (Shift Open)</option>
                            <option value="CHECKED_OUT" <?= ($status === 'CHECKED_OUT') ? 'selected' : '' ?>>Completed Shift</option>
                            <option value="INCOMPLETE" <?= ($status === 'INCOMPLETE') ? 'selected' : '' ?>>Missing Exit / Incomplete</option>
                            <option value="ABSENT" <?= ($status === 'ABSENT') ? 'selected' : '' ?>>Absent</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Filter</button>
                        <a href="/admin/attendance/reports?tab=daily&date=<?= esc($todayStr) ?>" class="btn btn-secondary" title="Reset to Today">Today</a>
                    </div>
                </form>
            </div>

            <!-- Daily Summary KPI Cards -->
            <div class="summary-kpi-grid">
                <div class="summary-kpi-card">
                    <div class="summary-kpi-val"><?= esc($metrics['total_employees']) ?></div>
                    <div class="summary-kpi-label">Active Staff</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #10b981;">
                    <div class="summary-kpi-val" style="color: #059669;"><?= esc($metrics['present']) ?></div>
                    <div class="summary-kpi-label">Present</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #2563eb;">
                    <div class="summary-kpi-val" style="color: #2563eb;"><?= esc($metrics['checked_out']) ?></div>
                    <div class="summary-kpi-label">Completed Shifts</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #f59e0b;">
                    <div class="summary-kpi-val" style="color: #d97706;"><?= esc($metrics['incomplete']) ?></div>
                    <div class="summary-kpi-label">Missing Exit</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #94a3b8;">
                    <div class="summary-kpi-val" style="color: #64748b;"><?= esc($metrics['absent']) ?></div>
                    <div class="summary-kpi-label">Absent</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="summary-kpi-val" style="color: #7c3aed;"><?= esc($metrics['photo_count']) ?></div>
                    <div class="summary-kpi-label">Audit Photos</div>
                </div>
            </div>

            <!-- Daily Records Table -->
            <div class="report-table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Staff ID</th>
                            <th>Staff Name</th>
                            <th class="hide-mobile">Department</th>
                            <th>Check-In</th>
                            <th>In Photo</th>
                            <th>Check-Out</th>
                            <th>Out Photo</th>
                            <th class="hide-mobile">Duration</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                    No staff records match the current filter criteria for <strong><?= esc($date) ?></strong>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr class="<?= $r['is_incomplete'] ? 'row-incomplete' : '' ?>">
                                    <td style="font-family: monospace; font-weight: 700;">
                                        <a href="/admin/attendance/reports?tab=employee&employee_id=<?= esc($r['employee_uuid']) ?>" style="color: var(--primary); text-decoration: underline;">
                                            <?= esc($r['employee_id']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-main);"><?= esc($r['full_name']) ?></div>
                                        <div class="hide-desktop" style="font-size: 0.75rem; color: var(--text-muted);"><?= esc($r['department'] ?? 'General') ?></div>
                                    </td>
                                    <td class="hide-mobile"><?= esc($r['department'] ?? '—') ?></td>
                                    <td>
                                        <?php if (!empty($r['check_in_time'])): ?>
                                            <strong><?= date('h:i A', strtotime($r['check_in_time'])) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_in_photo'])): ?>
                                            <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_in_photo']['id']) ?>', '<?= esc(addslashes($r['full_name'])) ?>', 'Check-In', '<?= esc($r['check_in_time']) ?>')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="13" r="4"></circle>
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <span>View</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="photo-btn-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_out_time'])): ?>
                                            <strong><?= date('h:i A', strtotime($r['check_out_time'])) ?></strong>
                                        <?php elseif ($r['display_status'] === 'CHECKED_IN'): ?>
                                            <span style="color: #d97706; font-style: italic;">Open</span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_out_photo'])): ?>
                                            <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_out_photo']['id']) ?>', '<?= esc(addslashes($r['full_name'])) ?>', 'Check-Out', '<?= esc($r['check_out_time']) ?>')">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="13" r="4"></circle>
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <span>View</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="photo-btn-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="hide-mobile">
                                        <?php if (!empty($r['total_work_minutes'])): ?>
                                            <?= sprintf('%dh %02dm', intdiv($r['total_work_minutes'], 60), $r['total_work_minutes'] % 60) ?>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['is_incomplete']): ?>
                                            <span class="badge-status badge-incomplete" title="No check-out recorded before shift end">
                                                Missing Exit
                                            </span>
                                        <?php elseif ($r['display_status'] === 'CHECKED_OUT'): ?>
                                            <span class="badge-status badge-checked-out">Completed</span>
                                        <?php elseif ($r['display_status'] === 'CHECKED_IN'): ?>
                                            <span class="badge-status badge-checked-in">On Duty</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-absent">Absent</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'monthly'): ?>
            <!-- ========================================== -->
            <!-- TAB 2: MONTH-WISE ATTENDANCE VIEW         -->
            <!-- ========================================== -->
            <div class="filter-card">
                <form method="GET" action="/admin/attendance/reports" class="filter-form-grid">
                    <input type="hidden" name="tab" value="monthly">
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Month
                        </label>
                        <select name="month" class="form-control" onchange="this.form.submit()">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= ($month == $m) ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 10)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Year
                        </label>
                        <select name="year" class="form-control" onchange="this.form.submit()">
                            <?php 
                            $currentY = (int)date('Y');
                            for ($y = $currentY; $y >= $currentY - 3; $y--): ?>
                                <option value="<?= $y ?>" <?= ($year == $y) ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Staff Search
                        </label>
                        <input type="text" name="search" value="<?= esc($search) ?>" placeholder="Name or ID..." class="form-control">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Department
                        </label>
                        <select name="department" class="form-control" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php foreach ($departmentList as $dept): ?>
                                <option value="<?= esc($dept) ?>" <?= ($department === $dept) ? 'selected' : '' ?>><?= esc($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button type="submit" class="btn btn-primary" style="flex: 1;">Filter</button>
                        <a href="/admin/attendance/reports?tab=monthly" class="btn btn-secondary">Current Month</a>
                    </div>
                </form>
            </div>

            <!-- Monthly Summary KPI Cards -->
            <div class="summary-kpi-grid">
                <div class="summary-kpi-card">
                    <div class="summary-kpi-val"><?= esc($metrics['days_in_month']) ?></div>
                    <div class="summary-kpi-label">Days in Month</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #10b981;">
                    <div class="summary-kpi-val" style="color: #059669;"><?= esc($metrics['total_present_days']) ?></div>
                    <div class="summary-kpi-label">Total Present Days</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #2563eb;">
                    <div class="summary-kpi-val" style="color: #2563eb;"><?= esc($metrics['total_completed_shifts']) ?></div>
                    <div class="summary-kpi-label">Completed Shifts</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #f59e0b;">
                    <div class="summary-kpi-val" style="color: #d97706;"><?= esc($metrics['total_incomplete_shifts']) ?></div>
                    <div class="summary-kpi-label">Missing Exits</div>
                </div>
                <div class="summary-kpi-card" style="border-left: 4px solid #8b5cf6;">
                    <div class="summary-kpi-val" style="color: #7c3aed;"><?= esc($metrics['photo_count']) ?></div>
                    <div class="summary-kpi-label">Audit Photos</div>
                </div>
            </div>

            <!-- Monthly Aggregates Table -->
            <div class="report-table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Staff ID</th>
                            <th>Staff Name</th>
                            <th class="hide-mobile">Department</th>
                            <th>Check-In</th>
                            <th>In Photo</th>
                            <th>Check-Out</th>
                            <th>Out Photo</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($records)): ?>
                            <tr>
                                <td colspan="10" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                    No staff attendance activity recorded for <strong><?= esc($monthName) ?> <?= esc($year) ?></strong>.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($records as $r): ?>
                                <tr class="<?= $r['is_incomplete'] ? 'row-incomplete' : '' ?>">
                                    <td style="font-weight: 600;">
                                        <?= esc($r['work_date']) ?>
                                    </td>
                                    <td style="font-family: monospace; font-weight: 700;">
                                        <a href="/admin/attendance/reports?tab=employee&employee_id=<?= esc($r['employee_uuid']) ?>" style="color: var(--primary); text-decoration: underline;">
                                            <?= esc($r['employee_id']) ?>
                                        </a>
                                    </td>
                                    <td style="font-weight: 600; color: var(--text-main);">
                                        <?= esc($r['full_name']) ?>
                                    </td>
                                    <td class="hide-mobile"><?= esc($r['department'] ?? '—') ?></td>
                                    <td>
                                        <?php if (!empty($r['check_in_time'])): ?>
                                            <strong><?= date('h:i A', strtotime($r['check_in_time'])) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_in_photo'])): ?>
                                            <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_in_photo']['id']) ?>', '<?= esc(addslashes($r['full_name'])) ?>', 'Check-In', '<?= esc($r['check_in_time']) ?>')">
                                                <span>View</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="photo-btn-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_out_time'])): ?>
                                            <strong><?= date('h:i A', strtotime($r['check_out_time'])) ?></strong>
                                        <?php else: ?>
                                            <span style="color: #d97706; font-style: italic;">Open</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($r['check_out_photo'])): ?>
                                            <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_out_photo']['id']) ?>', '<?= esc(addslashes($r['full_name'])) ?>', 'Check-Out', '<?= esc($r['check_out_time']) ?>')">
                                                <span>View</span>
                                            </button>
                                        <?php else: ?>
                                            <span class="photo-btn-muted">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($r['is_incomplete']): ?>
                                            <span class="badge-status badge-incomplete">Incomplete</span>
                                        <?php else: ?>
                                            <span class="badge-status badge-checked-out">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/admin/attendance/reports?tab=employee&employee_id=<?= esc($r['employee_uuid']) ?>&from_date=<?= esc($startDate) ?>&to_date=<?= esc($endDate) ?>" class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.25rem 0.5rem;">
                                            Details &raquo;
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($tab === 'employee'): ?>
            <!-- ========================================== -->
            <!-- TAB 3: EMPLOYEE CHRONOLOGICAL HISTORY     -->
            <!-- ========================================== -->
            <div class="filter-card">
                <form method="GET" action="/admin/attendance/reports" class="filter-form-grid">
                    <input type="hidden" name="tab" value="employee">
                    <div style="grid-column: span 2;">
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            Select Staff Member
                        </label>
                        <select name="employee_id" class="form-control" onchange="this.form.submit()">
                            <option value="">-- Choose Employee to Inspect --</option>
                            <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= esc($emp['id']) ?>" <?= ($selectedEmployee && $selectedEmployee['id'] === $emp['id']) ? 'selected' : '' ?>>
                                    <?= esc($emp['employee_id']) ?> &bull; <?= esc($emp['full_name']) ?> (<?= esc($emp['department'] ?? 'General') ?>)
                                    <?= $emp['is_active'] ? '' : ' [INACTIVE]' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            From Date
                        </label>
                        <input type="date" name="from_date" value="<?= esc($fromDate) ?>" class="form-control">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.8rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem;">
                            To Date
                        </label>
                        <input type="date" name="to_date" value="<?= esc($toDate) ?>" class="form-control">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">Inspect History</button>
                    </div>
                </form>
            </div>

            <?php if (!$selectedEmployee): ?>
                <div class="filter-card" style="text-align: center; padding: 3rem 1.5rem; color: var(--text-muted);">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin-bottom: 0.75rem; color: #94a3b8;">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <h3 style="margin: 0; color: var(--text-main);">No Staff Member Selected</h3>
                    <p style="margin-top: 0.5rem; font-size: 0.9rem;">
                        Select an employee from the dropdown above to view their complete attendance chronology, timestamps, and audit photos.
                    </p>
                </div>
            <?php else: ?>
                <!-- Selected Employee Summary Header -->
                <div class="filter-card" style="background: linear-gradient(to right, #f8fafc, #eff6ff); border-left: 4px solid var(--primary);">
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                        <div>
                            <div style="font-size: 1.25rem; font-weight: 800; color: var(--text-main);">
                                <?= esc($selectedEmployee['full_name']) ?>
                            </div>
                            <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">
                                Staff ID: <strong style="font-family: monospace; color: var(--primary);"><?= esc($selectedEmployee['employee_id']) ?></strong> &bull;
                                Dept: <strong><?= esc($selectedEmployee['department'] ?? 'General') ?></strong> &bull;
                                Status: <span class="badge <?= $selectedEmployee['is_active'] ? 'badge-success' : 'badge-danger' ?>"><?= $selectedEmployee['is_active'] ? 'Active' : 'Inactive' ?></span>
                            </div>
                        </div>
                        <div style="display: flex; gap: 1rem;">
                            <div style="text-align: right;">
                                <div style="font-size: 1.4rem; font-weight: 800; color: #059669;"><?= esc($metrics['total_days']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Days Present</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.4rem; font-weight: 800; color: #2563eb;"><?= esc($metrics['completed_shifts']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Completed</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.4rem; font-weight: 800; color: #d97706;"><?= esc($metrics['incomplete']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Incomplete</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-size: 1.4rem; font-weight: 800; color: #7c3aed;"><?= esc($metrics['photo_count']) ?></div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Photos</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chronological Records Table -->
                <div class="report-table-wrapper">
                    <table class="report-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Check-In</th>
                                <th>In Photo</th>
                                <th>Check-Out</th>
                                <th>Out Photo</th>
                                <th>Duration</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($records)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 2.5rem; color: var(--text-muted);">
                                        No attendance entries found for this employee between <strong><?= esc($fromDate) ?></strong> and <strong><?= esc($toDate) ?></strong>.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($records as $r): ?>
                                    <tr class="<?= $r['is_incomplete'] ? 'row-incomplete' : '' ?>">
                                        <td style="font-weight: 600;">
                                            <?= esc($r['work_date']) ?>
                                            <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: normal;">(<?= date('D', strtotime($r['work_date'])) ?>)</span>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['check_in_time'])): ?>
                                                <strong><?= date('h:i A', strtotime($r['check_in_time'])) ?></strong>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['check_in_photo'])): ?>
                                                <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_in_photo']['id']) ?>', '<?= esc(addslashes($selectedEmployee['full_name'])) ?>', 'Check-In', '<?= esc($r['check_in_time']) ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="13" r="4"></circle>
                                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                    </svg>
                                                    <span>View</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="photo-btn-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['check_out_time'])): ?>
                                                <strong><?= date('h:i A', strtotime($r['check_out_time'])) ?></strong>
                                            <?php elseif ($r['display_status'] === 'CHECKED_IN'): ?>
                                                <span style="color: #d97706; font-style: italic;">Open</span>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['check_out_photo'])): ?>
                                                <button type="button" class="photo-btn" onclick="openPhotoModal('<?= esc($r['check_out_photo']['id']) ?>', '<?= esc(addslashes($selectedEmployee['full_name'])) ?>', 'Check-Out', '<?= esc($r['check_out_time']) ?>')">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="13" r="4"></circle>
                                                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                    </svg>
                                                    <span>View</span>
                                                </button>
                                            <?php else: ?>
                                                <span class="photo-btn-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($r['total_work_minutes'])): ?>
                                                <?= sprintf('%dh %02dm', intdiv($r['total_work_minutes'], 60), $r['total_work_minutes'] % 60) ?>
                                            <?php else: ?>
                                                <span style="color: #94a3b8;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($r['is_incomplete']): ?>
                                                <span class="badge-status badge-incomplete">Missing Exit</span>
                                            <?php elseif ($r['display_status'] === 'CHECKED_OUT'): ?>
                                                <span class="badge-status badge-checked-out">Completed</span>
                                            <?php elseif ($r['display_status'] === 'CHECKED_IN'): ?>
                                                <span class="badge-status badge-checked-in">On Duty</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-absent"><?= esc($r['display_status']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </main>

    <!-- Photo Inspector Modal Dialog -->
    <div id="photoModalBackdrop" class="photo-modal-backdrop" onclick="closePhotoModal(event)">
        <div class="photo-modal-card" onclick="event.stopPropagation()">
            <div class="photo-modal-header">
                <div>
                    <h3 id="photoModalTitle" style="margin: 0; font-size: 1.1rem; font-weight: 700; color: var(--text-main);">
                        Attendance Photograph
                    </h3>
                    <div id="photoModalSubtitle" style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.15rem;">
                        Audit record
                    </div>
                </div>
                <button type="button" onclick="closePhotoModal()" style="background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; line-height: 1;" title="Close">&times;</button>
            </div>
            <div class="photo-modal-body">
                <img id="photoModalImage" src="" alt="Attendance Photograph" class="photo-modal-img">
                <div id="photoModalSpinner" style="display: none; padding: 2rem; color: #cbd5e1;">Loading photo...</div>
                <div id="photoModalError" style="display: none; padding: 2rem; color: #f87171;">Failed to load photograph.</div>
            </div>
            <div class="photo-modal-footer">
                <span id="photoModalMeta">Audit Record ID</span>
                <button type="button" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.35rem 0.75rem;" onclick="closePhotoModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        function openPhotoModal(photoId, staffName, eventType, timestamp) {
            var backdrop = document.getElementById('photoModalBackdrop');
            var img = document.getElementById('photoModalImage');
            var title = document.getElementById('photoModalTitle');
            var sub = document.getElementById('photoModalSubtitle');
            var meta = document.getElementById('photoModalMeta');
            var spinner = document.getElementById('photoModalSpinner');
            var error = document.getElementById('photoModalError');

            title.textContent = staffName + ' — ' + eventType;
            sub.textContent = 'Captured at: ' + timestamp;
            meta.textContent = 'Photo ID: ' + photoId;

            img.style.display = 'none';
            error.style.display = 'none';
            spinner.style.display = 'block';
            backdrop.style.display = 'flex';

            img.onload = function() {
                spinner.style.display = 'none';
                img.style.display = 'inline-block';
            };
            img.onerror = function() {
                spinner.style.display = 'none';
                error.style.display = 'block';
            };

            img.src = '/admin/attendance/photos/' + encodeURIComponent(photoId) + '?t=' + Date.now();
        }

        function closePhotoModal(e) {
            var backdrop = document.getElementById('photoModalBackdrop');
            backdrop.style.display = 'none';
            document.getElementById('photoModalImage').src = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePhotoModal();
            }
        });
    </script>
</body>
</html>
