<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <header class="navbar">
        <div class="container nav-content">
            <a href="/admin" class="brand-link">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge">Admin</span>
            </a>
            <div class="nav-actions">
                <a href="/admin/employees" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    <span>Staff Directory</span>
                </a>

                <a href="/admin/attendance/reports" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Attendance &amp; Reports</span>
                </a>

                <a href="/admin/kiosks" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="2" width="16" height="20" rx="2" ry="2"></rect>
                        <line x1="12" y1="18" x2="12.01" y2="18"></line>
                    </svg>
                    <span>Kiosk Devices</span>
                </a>

                <a href="/face-lab" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem; border-color: var(--accent); color: var(--accent);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                    <span>Face Lab Prototype</span>
                </a>

                <a href="/attendance" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>View Kiosk</span>
                </a>

                <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--gray-200);">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--gray-900);"><?= esc($adminFullName) ?></div>
                        <span class="badge <?= $adminRole === 'ROLE_ADMIN' ? 'badge-info' : 'badge-warning' ?>" style="font-size: 0.65rem;">
                            <?= esc($adminRole) ?>
                        </span>
                    </div>

                    <form action="/admin/logout" method="POST" style="margin: 0; display: inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; color: var(--danger); border-color: #fca5a5; cursor: pointer; background: transparent;" title="Sign Out">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="flex: 1; padding-top: 2rem; padding-bottom: 3rem;">
        <!-- Success Alert -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div style="margin-bottom: 2rem;">
            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--gray-900); margin-bottom: 0.25rem;">
                Control Center Dashboard
            </h1>
            <p style="color: var(--gray-500); font-size: 0.95rem;">
                Staff Attendance Management &bull; Server Time (IST): <strong><?= esc($serverTime) ?></strong>
            </p>
        </div>

        <!-- Metric KPI Cards -->
        <div class="dashboard-grid">
            <div class="stat-widget">
                <div class="stat-icon stat-icon-blue">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($activeEmployees) ?></div>
                    <div class="stat-label">Active Staff Members</div>
                </div>
            </div>

            <div class="stat-widget">
                <div class="stat-icon stat-icon-teal">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($activeDevices) ?></div>
                    <div class="stat-label">Authorized Kiosk Devices</div>
                </div>
            </div>

            <div class="stat-widget">
                <div class="stat-icon stat-icon-green">
                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($todayPunches) ?></div>
                    <div class="stat-label">Today's Shift Punches</div>
                </div>
            </div>
        </div>

        <!-- Module Navigation & Placeholders (Structured Roadmap) -->
        <div class="card">
            <h2 class="card-title">Management Modules</h2>
            <p class="card-subtitle">
                Core foundation active. Implementation stages for biometrics and records are scheduled sequentially.
            </p>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem;">
                <a href="/admin/employees" style="text-decoration: none; color: inherit; display: block; padding: 1.25rem; border: 1px solid var(--primary-light); border-radius: var(--radius-md); background-color: var(--white); transition: box-shadow 0.2s;" onmouseover="this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.boxShadow='none';">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                        <div style="font-weight: 700; color: var(--primary-dark);">Staff Directory</div>
                        <span class="badge badge-success">Active &bull; Stage 2</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">Manage employee profiles, search & filter staff, update departments, and toggle employment status.</div>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--primary); display: inline-flex; align-items: center; gap: 0.25rem;">
                        Open Staff Directory &rarr;
                    </span>
                </a>

                <a href="/face-lab" style="text-decoration: none; color: inherit; display: block; padding: 1.25rem; border: 1px solid var(--accent); border-radius: var(--radius-md); background-color: var(--white); transition: box-shadow 0.2s;" onmouseover="this.style.boxShadow='var(--shadow-md)';" onmouseout="this.style.boxShadow='none';">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.35rem;">
                        <div style="font-weight: 700; color: var(--accent);">Biometric Face Lab</div>
                        <span class="badge" style="background-color: #ccfbf1; color: var(--accent);">Stage 3 Prototype</span>
                    </div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">Evaluate browser-based face recognition feasibility, camera pipeline, 128-d embeddings, and matching stability.</div>
                    <span style="font-size: 0.8rem; font-weight: 600; color: var(--accent); display: inline-flex; align-items: center; gap: 0.25rem;">
                        Launch Feasibility Lab &rarr;
                    </span>
                </a>

                <div style="padding: 1.25rem; border: 1px solid var(--gray-200); border-radius: var(--radius-md); background-color: var(--white);">
                    <div style="font-weight: 700; color: var(--gray-900); margin-bottom: 0.35rem;">Attendance & Corrections</div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">Daily/monthly attendance log, supervisor overrides, and audit trails.</div>
                    <span class="badge badge-warning">Stage 7 Scheduled</span>
                </div>

                <div style="padding: 1.25rem; border: 1px solid var(--gray-200); border-radius: var(--radius-md); background-color: var(--white);">
                    <div style="font-weight: 700; color: var(--gray-900); margin-bottom: 0.35rem;">Tablet Pairing & Security</div>
                    <div style="font-size: 0.8rem; color: var(--gray-500); margin-bottom: 0.75rem;">Authorize Android tablets, generate pairing codes, and revoke devices.</div>
                    <span class="badge badge-warning">Stage 4 Scheduled</span>
                </div>
            </div>
        </div>

        <!-- Security & Audit Trail -->
        <div class="card">
            <h2 class="card-title">Recent Administrative Audit Events</h2>
            <p class="card-subtitle">Immutable security ledger tracking logins, status changes, and administrative actions.</p>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Actor</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentAudits)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--gray-500); padding: 1.5rem;">
                                    No audit entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentAudits as $audit): ?>
                                <tr>
                                    <td style="white-space: nowrap; font-size: 0.8rem; color: var(--gray-500);"><?= esc($audit['created_at']) ?></td>
                                    <td><strong><?= esc($audit['actor_username']) ?></strong></td>
                                    <td><span class="badge badge-info"><?= esc($audit['action']) ?></span></td>
                                    <td><?= esc($audit['details']) ?></td>
                                    <td style="font-family: monospace; font-size: 0.8rem;"><?= esc($audit['ip_address']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Technical Environment Diagnostics -->
        <div class="card" style="background-color: var(--gray-50);">
            <h3 class="card-title" style="font-size: 0.95rem;">Technical Environment Foundation</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-top: 0.5rem; font-size: 0.8rem;">
                <div><span style="color: var(--gray-500);">PHP Runtime:</span> <strong><?= esc($phpVersion) ?></strong></div>
                <div><span style="color: var(--gray-500);">Framework:</span> <strong>CodeIgniter <?= esc($ciVersion) ?></strong></div>
                <div><span style="color: var(--gray-500);">Database:</span> <strong><?= esc($dbPlatform) ?></strong></div>
                <div><span style="color: var(--gray-500);">Session:</span> <strong>FileHandler (Secure)</strong></div>
            </div>
        </div>
    </main>

    <footer style="background-color: var(--white); border-top: 1px solid var(--gray-200); padding: 1.25rem 0; text-align: center; font-size: 0.8rem; color: var(--gray-500);">
        <div class="container">
            Edifies School Staff Attendance Management System &bull; Admin Portal Foundation
        </div>
    </footer>
</body>
</html>

