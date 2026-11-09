<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Main Container */
        .dash-content {
            flex: 1;
            max-width: 1400px;
            width: 100%;
            margin: 0;
            padding: 2rem 2.5rem 3rem;
            box-sizing: border-box;
        }

        /* Page Header */
        .dash-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .dash-title {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.25rem;
        }

        .dash-subtitle {
            font-size: 0.9rem;
            color: #64748b;
        }

        .dash-time-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #334155;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        /* KPI Metric Cards Grid */
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
            margin-bottom: 2rem;
        }

        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.05);
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px -3px rgba(15, 23, 42, 0.08);
        }

        .kpi-icon-box {
            width: 54px;
            height: 54px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .kpi-icon-blue {
            background: #eff6ff;
            color: #2563eb;
        }

        .kpi-icon-teal {
            background: #f0fdfa;
            color: #0d9488;
        }

        .kpi-icon-green {
            background: #f0fdf4;
            color: #16a34a;
        }

        .kpi-value {
            font-size: 2rem;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 0.2rem;
        }

        .kpi-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        /* Section Layout */
        .dash-section {
            margin-bottom: 2.25rem;
        }

        .section-header {
            margin-bottom: 1rem;
        }

        .section-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .section-subtitle {
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Quick Modules Grid */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
        }

        .module-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.15s ease-in-out;
        }

        .module-card:hover {
            background: #ffffff;
            border-color: #3b82f6;
            box-shadow: 0 8px 16px -3px rgba(37, 99, 235, 0.12);
            transform: translateY(-2px);
        }

        .module-title {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.45rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .module-desc {
            font-size: 0.825rem;
            color: #64748b;
            line-height: 1.45;
            margin-bottom: 1rem;
        }

        .module-link {
            font-size: 0.825rem;
            font-weight: 600;
            color: #2563eb;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        /* Table Styles */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid #e2e8f0;
        }

        .audit-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            text-align: left;
        }

        .audit-table th {
            background-color: #f8fafc;
            color: #475569;
            font-weight: 600;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .audit-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }

        .audit-table tbody tr:last-child td {
            border-bottom: none;
        }

        .audit-table tbody tr:hover {
            background-color: #f8fafc;
        }

        .audit-action-badge {
            display: inline-block;
            font-size: 0.72rem;
            font-weight: 600;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            white-space: nowrap;
        }

        .badge-success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .badge-primary {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .badge-info {
            background: #f0f9ff;
            color: #0284c7;
            border: 1px solid #bae6fd;
        }

        .badge-warning {
            background: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-danger {
            background: #fef2f2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .badge-neutral {
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        /* Footer */
        .dash-footer {
            background: #ffffff;
            border-top: 1px solid #e2e8f0;
            padding: 1.25rem 0;
            text-align: center;
            font-size: 0.8rem;
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- Unified Dashboard Navbar -->
    <?= view('admin/common/navbar', [
        'activeNav'     => 'dashboard',
        'userRole'      => $adminRole ?? 'ROLE_ADMIN',
        'adminFullName' => $adminFullName ?? 'Administrator'
    ]) ?>

    <!-- Main Content Area -->
    <main class="dash-content">

        <!-- Flash Success Alerts -->
        <?php if (session()->getFlashdata('success')): ?>
            <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.85rem 1.25rem; border-radius: 10px; font-size: 0.9rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="dash-header">
            <div>
                <h1 class="dash-title">Administrator Dashboard</h1>
                <p class="dash-subtitle">Staff Attendance &bull; Central Control Center</p>
            </div>
            <div class="dash-time-badge">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <polyline points="12 6 12 12 16 14"></polyline>
                </svg>
                <span>Server Time: <?= esc($serverTimeIST ?? $serverTime) ?></span>
            </div>
        </div>

        <!-- 3 Primary Metrics -->
        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-icon-box kpi-icon-blue">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div>
                    <div class="kpi-value"><?= esc($activeEmployees) ?></div>
                    <div class="kpi-label">Active Staff Members</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon-box kpi-icon-teal">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <div>
                    <div class="kpi-value"><?= esc($activeDevices) ?></div>
                    <div class="kpi-label">Authorized Scanner Terminals</div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-icon-box kpi-icon-green">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="kpi-value"><?= esc($todayPunches) ?></div>
                    <div class="kpi-label">Today's Attendance Punches</div>
                </div>
            </div>
        </div>

        <!-- Operational Attendance Cutoff Notifications & Alerts -->
        <?php if ($isPast12PM && !empty($missingCheckIns)): ?>
            <div style="background: #fff7ed; border: 1px solid #fed7aa; border-left: 4px solid #ea580c; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 6px rgba(234, 88, 12, 0.08);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #ea580c; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">!</div>
                        <div>
                            <h3 style="font-size: 1.05rem; font-weight: 700; color: #9a3412; margin: 0;">12:00 PM Attendance Cutoff Alert</h3>
                            <span style="font-size: 0.8rem; color: #c2410c;">Staff member(s) who have not marked attendance before 12:00 PM today</span>
                        </div>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 700; background: #ffedd5; color: #9a3412; padding: 0.25rem 0.65rem; border-radius: 9999px; border: 1px solid #fdba74;">
                        <?= count($missingCheckIns) ?> Pending Check-In<?= count($missingCheckIns) > 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #fed7aa; text-align: left; color: #9a3412;">
                                <th style="padding: 0.4rem 0.5rem;">Staff ID</th>
                                <th style="padding: 0.4rem 0.5rem;">Full Name</th>
                                <th style="padding: 0.4rem 0.5rem;">Department</th>
                                <th style="padding: 0.4rem 0.5rem;">Designation</th>
                                <th style="padding: 0.4rem 0.5rem; text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missingCheckIns as $mEmp): ?>
                                <tr style="border-bottom: 1px solid #ffedd5;">
                                    <td style="padding: 0.5rem; font-family: monospace; font-weight: 600; color: #9a3412;"><?= esc($mEmp['employee_id']) ?></td>
                                    <td style="padding: 0.5rem; font-weight: 600; color: #1e293b;"><?= esc($mEmp['full_name']) ?></td>
                                    <td style="padding: 0.5rem; color: #475569;"><?= esc($mEmp['department']) ?></td>
                                    <td style="padding: 0.5rem; color: #64748b;"><?= esc($mEmp['designation']) ?></td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <span style="font-size: 0.72rem; font-weight: 700; background: #fee2e2; color: #b91c1c; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid #fecaca;">
                                            NO CHECK-IN
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($isPast7PM && !empty($missingSignOffs)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #dc2626; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 2rem; box-shadow: 0 2px 6px rgba(220, 38, 38, 0.08);">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.6rem;">
                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #dc2626; color: #ffffff; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem;">!</div>
                        <div>
                            <h3 style="font-size: 1.05rem; font-weight: 700; color: #991b1b; margin: 0;">7:00 PM Sign-Off Cutoff Alert</h3>
                            <span style="font-size: 0.8rem; color: #b91c1c;">Staff member(s) who checked in today but have not signed off (checked out) till 7:00 PM</span>
                        </div>
                    </div>
                    <span style="font-size: 0.8rem; font-weight: 700; background: #fee2e2; color: #991b1b; padding: 0.25rem 0.65rem; border-radius: 9999px; border: 1px solid #fca5a5;">
                        <?= count($missingSignOffs) ?> Missing Sign-Off<?= count($missingSignOffs) > 1 ? 's' : '' ?>
                    </span>
                </div>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; margin-top: 0.5rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #fecaca; text-align: left; color: #991b1b;">
                                <th style="padding: 0.4rem 0.5rem;">Staff ID</th>
                                <th style="padding: 0.4rem 0.5rem;">Full Name</th>
                                <th style="padding: 0.4rem 0.5rem;">Department</th>
                                <th style="padding: 0.4rem 0.5rem;">Check-In Time</th>
                                <th style="padding: 0.4rem 0.5rem; text-align: right;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($missingSignOffs as $sEmp): ?>
                                <tr style="border-bottom: 1px solid #fee2e2;">
                                    <td style="padding: 0.5rem; font-family: monospace; font-weight: 600; color: #991b1b;"><?= esc($sEmp['employee_id']) ?></td>
                                    <td style="padding: 0.5rem; font-weight: 600; color: #1e293b;"><?= esc($sEmp['full_name']) ?></td>
                                    <td style="padding: 0.5rem; color: #475569;"><?= esc($sEmp['department']) ?></td>
                                    <td style="padding: 0.5rem; color: #334155; font-weight: 500;"><?= esc(date('h:i A', strtotime($sEmp['check_in_time']))) ?></td>
                                    <td style="padding: 0.5rem; text-align: right;">
                                        <span style="font-size: 0.72rem; font-weight: 700; background: #fee2e2; color: #b91c1c; padding: 0.2rem 0.5rem; border-radius: 6px; border: 1px solid #fca5a5;">
                                            PENDING SIGN-OFF
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ((!$isPast12PM || empty($missingCheckIns)) && (!$isPast7PM || empty($missingSignOffs))): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 0.85rem 1.25rem; margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem;">
                    <div style="width: 26px; height: 26px; border-radius: 50%; background: #16a34a; color: white; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 700;">✓</div>
                    <div>
                        <span style="font-weight: 700; color: #166534; font-size: 0.875rem;">Attendance Cutoffs In Order</span>
                        <span style="font-size: 0.8rem; color: #15803d; margin-left: 0.5rem;">All active staff have marked attendance on schedule (12:00 PM Check-In &bull; 7:00 PM Sign-Off monitored).</span>
                    </div>
                </div>
                <span style="font-size: 0.75rem; font-weight: 600; color: #166534; background: #dcfce7; padding: 0.2rem 0.55rem; border-radius: 9999px;">Cutoff Engine Active</span>
            </div>
        <?php endif; ?>

        <!-- Functional Management Modules -->
        <div class="dash-section">
            <div class="section-header">
                <h2 class="section-title">Management Modules</h2>
                <p class="section-subtitle">Quick access to staff management, terminal pairing, and attendance operations</p>
            </div>

            <div class="module-grid">
                <!-- Staff Directory & Face Enrollment -->
                <a href="/admin/employees" class="module-card">
                    <div>
                        <div class="module-title">
                            <span>Staff Management &amp; Faces</span>
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; background: #eff6ff; color: #1d4ed8; border-radius: 6px;">Manage</span>
                        </div>
                        <p class="module-desc">Add and manage employee profiles, register facial recognition data for automated attendance, and toggle staff status.</p>
                    </div>
                    <div class="module-link">
                        <span>Open Staff Directory</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- Scanner Devices & 1-Time Pairing Codes -->
                <a href="/admin/kiosks" class="module-card">
                    <div>
                        <div class="module-title">
                            <span>Scanner Terminals &amp; Pairing</span>
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; background: #f0fdfa; color: #0f766e; border-radius: 6px;">Devices</span>
                        </div>
                        <p class="module-desc">Generate single-use 6-digit pairing codes to authorize tablets, phones, or computers as dedicated face scanners.</p>
                    </div>
                    <div class="module-link" style="color: #0f766e;">
                        <span>Manage Scanner Devices</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- Attendance Logs & Reports -->
                <a href="/admin/attendance/reports" class="module-card">
                    <div>
                        <div class="module-title">
                            <span>Attendance &amp; Shift Reports</span>
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; background: #f0fdf4; color: #15803d; border-radius: 6px;">Reports</span>
                        </div>
                        <p class="module-desc">Review shift entries and exits, monitor daily presence summaries, and inspect automated punch audit logs.</p>
                    </div>
                    <div class="module-link" style="color: #15803d;">
                        <span>View Attendance Reports</span>
                        <span>&rarr;</span>
                    </div>
                </a>

                <!-- Live Touchless Face Recognition Scanner -->
                <a href="/attendance" target="_blank" class="module-card">
                    <div>
                        <div class="module-title">
                            <span>Live Scanner Terminal</span>
                            <span style="font-size: 0.7rem; font-weight: 600; padding: 0.15rem 0.5rem; background: #fef3c7; color: #b45309; border-radius: 6px;">Touchless</span>
                        </div>
                        <p class="module-desc">Launch the 24/7 automated face-recognition scanner interface with sleep/wake functionality on this device.</p>
                    </div>
                    <div class="module-link" style="color: #b45309;">
                        <span>Open Scanner Window</span>
                        <span>&rarr;</span>
                    </div>
                </a>
            </div>
        </div>

        <!-- Recent Administrative Activity -->
        <div class="dash-section">
            <div class="section-header">
                <h2 class="section-title">Recent Administrative Activity</h2>
                <p class="section-subtitle">Summary of staff directory operations, biometric enrollments, and system sign-ins</p>
            </div>

            <div class="table-responsive">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th style="width: 180px;">Date &amp; Time</th>
                            <th style="width: 150px;">User</th>
                            <th style="width: 160px;">Activity</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAudits)): ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #94a3b8; padding: 2rem;">
                                    No administrative activity recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAudits as $audit): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 0.8rem; color: #64748b; font-weight: 500;"><?= esc($audit['created_at']) ?></td>
                                    <td><strong style="color: #0f172a; font-size: 0.85rem;"><?= esc($audit['actor_display']) ?></strong></td>
                                    <td><span class="audit-action-badge <?= esc($audit['badge_class']) ?>"><?= esc($audit['action_label']) ?></span></td>
                                    <td style="color: #334155; line-height: 1.5; font-size: 0.85rem;"><?= $audit['details_html'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Clean Footer -->
    <footer class="dash-footer">
        <div class="container">
            &copy; <?= date('Y') ?> Edifies School &bull; Staff Attendance Management System
        </div>
    </footer>

</body>
</html>