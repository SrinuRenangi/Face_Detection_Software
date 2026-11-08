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

        /* Top Navigation Bar */
        .dash-navbar {
            background-color: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            position: sticky;
            top: 0;
            z-index: 50;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05);
        }

        .dash-nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            min-height: 4.25rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .dash-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #0f172a;
            font-weight: 700;
            font-size: 1.15rem;
        }

        .dash-brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: #ffffff;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .dash-brand-badge {
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border-radius: 9999px;
            background-color: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .dash-nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .dash-nav-item {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #475569;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.15s ease-in-out;
            border: 1px solid transparent;
        }

        .dash-nav-item:hover {
            color: #1e40af;
            background-color: #f1f5f9;
        }

        .dash-nav-item.active {
            color: #1e40af;
            background-color: #eff6ff;
            border-color: #dbeafe;
        }

        .dash-user-section {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding-left: 0.75rem;
            border-left: 1px solid #e2e8f0;
        }

        .dash-user-info {
            text-align: right;
            line-height: 1.25;
        }

        .dash-user-name {
            font-size: 0.85rem;
            font-weight: 700;
            color: #0f172a;
        }

        .dash-user-role {
            font-size: 0.7rem;
            color: #64748b;
            font-weight: 500;
        }

        .btn-signout {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 600;
            color: #dc2626;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 7px;
            cursor: pointer;
            transition: all 0.15s ease-in-out;
        }

        .btn-signout:hover {
            background-color: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        /* Main Container */
        .dash-content {
            flex: 1;
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem 3rem;
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
            line-height: 1;
            margin-bottom: 0.35rem;
            letter-spacing: -0.02em;
        }

        .kpi-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
        }

        /* Section Container */
        .dash-section {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 6px -1px rgba(15, 23, 42, 0.05);
        }

        .section-header {
            margin-bottom: 1.25rem;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.25rem;
        }

        .section-subtitle {
            font-size: 0.85rem;
            color: #64748b;
        }

        /* Module Action Cards Grid */
        .module-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.25rem;
        }

        .module-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.35rem;
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
            font-size: 0.7rem;
            font-weight: 600;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
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
    <header class="dash-navbar">
        <div class="dash-nav-container">
            <a href="/admin" class="dash-brand">
                <div class="dash-brand-icon">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <span>Edifies School</span>
                <span class="dash-brand-badge">Administrator</span>
            </a>

            <nav class="dash-nav-links">
                <a href="/admin" class="dash-nav-item active">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"></rect>
                        <rect x="14" y="3" width="7" height="7"></rect>
                        <rect x="14" y="14" width="7" height="7"></rect>
                        <rect x="3" y="14" width="7" height="7"></rect>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="/admin/employees" class="dash-nav-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Staff Directory</span>
                </a>

                <a href="/admin/kiosks" class="dash-nav-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Scanner Devices</span>
                </a>

                <a href="/admin/attendance/reports" class="dash-nav-item">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Attendance &amp; Reports</span>
                </a>

                <a href="/attendance" target="_blank" class="dash-nav-item" style="color: #059669;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polygon points="10 8 16 12 10 16 10 8"></polygon>
                    </svg>
                    <span>Launch Scanner</span>
                </a>

                <div class="dash-user-section">
                    <div class="dash-user-info">
                        <div class="dash-user-name"><?= esc($adminFullName ?? 'Administrator') ?></div>
                        <div class="dash-user-role"><?= esc($adminRole ?? 'ROLE_ADMIN') ?></div>
                    </div>

                    <form action="/admin/logout" method="POST" style="margin: 0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-signout" title="Sign Out">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </nav>
        </div>
    </header>

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
                <span>Server Time: <?= esc($serverTime) ?></span>
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
                        <p class="module-desc">Generate 32-character single-use pairing codes to authorize tablets, phones, or computers as dedicated face scanners.</p>
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

        <!-- Recent Audit Events Ledger -->
        <div class="dash-section">
            <div class="section-header">
                <h2 class="section-title">Recent Activity Ledger</h2>
                <p class="section-subtitle">Security log of recent administrative actions, device authentications, and system events</p>
            </div>

            <div class="table-responsive">
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAudits)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2rem;">
                                    No audit entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAudits as $audit): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 0.8rem; color: #64748b;"><?= esc($audit['created_at']) ?></td>
                                    <td><strong><?= esc($audit['actor_username']) ?></strong></td>
                                    <td><span class="audit-action-badge"><?= esc($audit['action']) ?></span></td>
                                    <td style="color: #334155;"><?= esc($audit['details']) ?></td>
                                    <td style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.8rem; color: #64748b;"><?= esc($audit['ip_address']) ?></td>
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
