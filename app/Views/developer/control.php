<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Developer Control Panel') ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.dev-body {
            background-color: #0b0f19;
            color: #e2e8f0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .dev-header {
            background: #111827;
            border-bottom: 1px solid #1f2937;
            padding: 0.85rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 50;
        }

        .dev-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dev-badge {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            color: #ffffff;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            padding: 0.25rem 0.55rem;
            border-radius: 6px;
            box-shadow: 0 0 12px rgba(99, 102, 241, 0.4);
        }

        .dev-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .dev-user-bar {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }

        .dev-user-info {
            font-size: 0.85rem;
            color: #94a3b8;
            text-align: right;
        }

        .dev-user-info strong {
            color: #f8fafc;
            display: block;
        }

        .dev-btn-logout {
            background: #1f2937;
            border: 1px solid #374151;
            color: #f87171;
            padding: 0.45rem 0.85rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }

        .dev-btn-logout:hover {
            background: #374151;
            color: #ef4444;
        }

        .dev-main {
            flex: 1;
            max-width: 1280px;
            width: 100%;
            margin: 0 auto;
            padding: 2rem 1.5rem 3rem;
        }

        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        @media (max-width: 900px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
        }

        .dev-card {
            background: #111827;
            border: 1px solid #1f2937;
            border-radius: 14px;
            padding: 1.5rem;
            box-shadow: 0 4px 20px -2px rgba(0, 0, 0, 0.4);
        }

        .dev-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #1f2937;
            padding-bottom: 1rem;
            margin-bottom: 1.25rem;
        }

        .dev-card-title {
            font-size: 1rem;
            font-weight: 700;
            color: #f1f5f9;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Killswitch Banner */
        .killswitch-card {
            border-radius: 16px;
            padding: 1.75rem;
            margin-bottom: 2rem;
            border: 1px solid;
            transition: all 0.3s ease;
        }

        .killswitch-active {
            background: linear-gradient(135deg, rgba(153, 27, 27, 0.25) 0%, rgba(17, 24, 39, 0.95) 100%);
            border-color: #ef4444;
            box-shadow: 0 0 35px rgba(239, 68, 68, 0.2);
        }

        .killswitch-normal {
            background: linear-gradient(135deg, rgba(6, 78, 59, 0.25) 0%, rgba(17, 24, 39, 0.95) 100%);
            border-color: #10b981;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.15);
        }

        .killswitch-status-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        .status-indicator {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .indicator-pulse {
            width: 14px;
            height: 14px;
            border-radius: 50%;
        }

        .pulse-red {
            background: #ef4444;
            box-shadow: 0 0 12px #ef4444;
            animation: pulse-red 1.5s infinite;
        }

        .pulse-green {
            background: #10b981;
            box-shadow: 0 0 12px #10b981;
            animation: pulse-green 2s infinite;
        }

        @keyframes pulse-red {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.4; transform: scale(0.9); }
        }

        @keyframes pulse-green {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.6; transform: scale(0.95); }
        }

        .status-text-title {
            font-size: 1.25rem;
            font-weight: 700;
        }

        .status-text-sub {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Buttons */
        .btn-ks-toggle {
            padding: 0.75rem 1.4rem;
            border-radius: 10px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s ease;
        }

        .btn-ks-danger {
            background: #ef4444;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        }

        .btn-ks-danger:hover {
            background: #dc2626;
            transform: translateY(-1px);
        }

        .btn-ks-restore {
            background: #10b981;
            color: #ffffff;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.4);
        }

        .btn-ks-restore:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        /* Tables */
        .dev-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
        }

        .dev-table th {
            text-align: left;
            padding: 0.75rem 1rem;
            color: #94a3b8;
            font-weight: 600;
            border-bottom: 1px solid #1f2937;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .dev-table td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #1f2937;
            color: #e2e8f0;
        }

        .dev-table tr:hover {
            background: rgba(31, 41, 55, 0.5);
        }

        .badge-role {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            letter-spacing: 0.03em;
        }

        .role-super {
            background: rgba(124, 58, 237, 0.2);
            color: #c084fc;
            border: 1px solid rgba(124, 58, 237, 0.4);
        }

        .role-admin {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.4);
        }

        .role-manager {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.4);
        }

        .role-scanner {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }

        .badge-status {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
        }

        .status-active {
            background: rgba(16, 185, 129, 0.15);
            color: #34d399;
        }

        .status-suspended {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
        }

        /* Action Buttons */
        .btn-action-sm {
            padding: 0.35rem 0.65rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #374151;
            background: #1f2937;
            color: #f1f5f9;
            transition: all 0.15s ease;
        }

        .btn-action-sm:hover {
            background: #374151;
            color: #ffffff;
        }

        .btn-action-reset {
            color: #38bdf8;
            border-color: rgba(56, 189, 248, 0.3);
        }

        .btn-action-reset:hover {
            background: rgba(56, 189, 248, 0.15);
        }

        .btn-action-toggle {
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.3);
        }

        .btn-action-toggle:hover {
            background: rgba(245, 158, 11, 0.15);
        }

        /* Forms */
        .dev-form-group {
            margin-bottom: 1.15rem;
        }

        .dev-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 600;
            color: #94a3b8;
            margin-bottom: 0.4rem;
        }

        .dev-input {
            width: 100%;
            background: #1f2937;
            border: 1px solid #374151;
            border-radius: 8px;
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
            color: #f8fafc;
            outline: none;
            box-sizing: border-box;
            transition: all 0.15s ease;
        }

        .dev-input:focus {
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }

        .btn-dev-submit {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            font-weight: 600;
            font-size: 0.875rem;
            padding: 0.7rem 1.25rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.3);
            transition: all 0.15s ease;
        }

        .btn-dev-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
        }

        /* Modal */
        .dev-modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(6px);
            z-index: 100;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }

        .dev-modal {
            background: #111827;
            border: 1px solid #374151;
            border-radius: 14px;
            max-width: 440px;
            width: 100%;
            padding: 1.75rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.2s ease-out;
        }

        /* Alerts */
        .dev-alert {
            padding: 0.85rem 1.25rem;
            border-radius: 10px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .dev-alert-success {
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .dev-alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
    </style>
</head>
<body class="dev-body">

    <!-- Top Developer Bar -->
    <header class="dev-header">
        <div class="dev-brand">
            <span class="dev-badge">SUPER_ADMIN</span>
            <span class="dev-title">Developer System Override &bull; Edifies School</span>
        </div>

        <div class="dev-user-bar">
            <div class="dev-user-info">
                <strong><?= esc($currentUser['full_name'] ?? 'Super Administrator') ?></strong>
                <span>@<?= esc($currentUser['username'] ?? 'superadmin') ?> &bull; Developer Tier</span>
            </div>

            <a href="/admin/dashboard" style="color: #38bdf8; font-size: 0.8rem; font-weight: 600; text-decoration: none; padding: 0.45rem 0.75rem; background: #1f2937; border-radius: 8px; border: 1px solid #374151;">
                Admin Portal &rarr;
            </a>

            <form action="/logout" method="POST" style="margin: 0;">
                <?= csrf_field() ?>
                <button type="submit" class="dev-btn-logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </header>

    <main class="dev-main">

        <!-- Flash Notifications -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="dev-alert dev-alert-success">
                <span>&#10003;</span>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="dev-alert dev-alert-error">
                <span>&#9888;</span>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <!-- 1. Emergency Application Killswitch Card -->
        <section class="killswitch-card <?= $isKillswitchActive ? 'killswitch-active' : 'killswitch-normal' ?>">
            <div class="killswitch-status-banner">
                <div class="status-indicator">
                    <div class="indicator-pulse <?= $isKillswitchActive ? 'pulse-red' : 'pulse-green' ?>"></div>
                    <div>
                        <div class="status-text-title" style="color: <?= $isKillswitchActive ? '#fca5a5' : '#6ee7b7' ?>;">
                            <?= $isKillswitchActive ? 'EMERGENCY KILLSWITCH ENGAGED (APPLICATION SUSPENDED)' : 'APPLICATION ACCESS IS ACTIVE (NORMAL OPERATION)' ?>
                        </div>
                        <div class="status-text-sub">
                            <?= $isKillswitchActive
                                ? 'All Admin, Manager, and Scanner endpoints are immediately halted with HTTP 503 maintenance response. Only Developer routes remain operational.'
                                : 'All administrative, manager, and biometric attendance scanner services are running normally.' ?>
                        </div>
                    </div>
                </div>

                <!-- Killswitch Toggle Form -->
                <form action="/developer/kill-switch/toggle" method="POST" onsubmit="return confirm('<?= $isKillswitchActive ? 'Restore full application access now?' : 'CRITICAL WARNING: Are you sure you want to engage the Emergency Killswitch? All user and scanner access will be suspended immediately!' ?>');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="state" value="<?= $isKillswitchActive ? '0' : '1' ?>">

                    <?php if ($isKillswitchActive): ?>
                        <button type="submit" class="btn-ks-toggle btn-ks-restore">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <polyline points="1 20 1 14 7 14"></polyline>
                                <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                            </svg>
                            <span>Disengage Kill-Switch &bull; Restore All Services</span>
                        </button>
                    <?php else: ?>
                        <button type="submit" class="btn-ks-toggle btn-ks-danger">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                            </svg>
                            <span>Engage Emergency Kill-Switch (Lockout)</span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Lockout Public Message Customizer (Shown when killswitch is active or for preview) -->
            <form action="/developer/kill-switch/toggle" method="POST" style="margin-top: 1rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="state" value="<?= $isKillswitchActive ? '1' : '0' ?>">
                <label style="font-size: 0.8rem; color: #94a3b8; font-weight: 600;">Lockout Banner Text:</label>
                <input type="text" name="message" class="dev-input" style="flex: 1; min-width: 280px;" value="<?= esc($killswitchMessage) ?>" placeholder="Lockout message shown to users">
                <button type="submit" class="btn-action-sm" style="padding: 0.65rem 1rem;">Update Lockout Text</button>
            </form>
        </section>

        <!-- 2. Two-Column Layout: Emergency User Override + Developer Credentials -->
        <div class="grid-2">

            <!-- Card A: User Credential Override (Admin & Manager Accounts) -->
            <div class="dev-card" style="grid-column: span 2;">
                <div class="dev-card-header">
                    <div class="dev-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Emergency Account Credential Override</span>
                    </div>
                    <span style="font-size: 0.8rem; color: #94a3b8;"><?= count($users) ?> Total System Users</span>
                </div>

                <div style="overflow-x: auto;">
                    <table class="dev-table">
                        <thead>
                            <tr>
                                <th>Account</th>
                                <th>Email</th>
                                <th>Assigned Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th style="text-align: right;">Emergency Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 700; color: #f8fafc;"><?= esc($u['full_name']) ?></div>
                                        <div style="font-size: 0.75rem; color: #94a3b8;">@<?= esc($u['username']) ?></div>
                                    </td>
                                    <td><?= esc($u['email'] ?? '—') ?></td>
                                    <td>
                                        <?php
                                            $roleClass = 'role-admin';
                                            if ($u['role'] === 'ROLE_SUPER_ADMIN') $roleClass = 'role-super';
                                            elseif ($u['role'] === 'ROLE_MANAGER') $roleClass = 'role-manager';
                                            elseif ($u['role'] === 'ROLE_SCANNER') $roleClass = 'role-scanner';
                                        ?>
                                        <span class="badge-role <?= $roleClass ?>"><?= esc($u['role']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge-status <?= $u['is_active'] ? 'status-active' : 'status-suspended' ?>">
                                            <?= $u['is_active'] ? 'ACTIVE' : 'SUSPENDED' ?>
                                        </span>
                                    </td>
                                    <td style="font-size: 0.8rem; color: #94a3b8;">
                                        <?= !empty($u['last_login_at']) ? esc(date('d M Y, h:i A', strtotime($u['last_login_at']))) : 'Never' ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: inline-flex; gap: 0.5rem;">
                                            <button type="button" class="btn-action-sm btn-action-reset" onclick="openResetModal('<?= esc($u['id']) ?>', '<?= esc($u['username']) ?>', '<?= esc($u['full_name']) ?>')">
                                                Reset Password
                                            </button>

                                            <?php if ($u['id'] !== session()->get('admin_user_id')): ?>
                                                <form action="/developer/users/<?= esc($u['id']) ?>/toggle-status" method="POST" style="margin: 0;" onsubmit="return confirm('Toggle status for <?= esc($u['username']) ?>?');">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn-action-sm btn-action-toggle">
                                                        <?= $u['is_active'] ? 'Suspend' : 'Activate' ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Card B: Self-Service Super-Admin Credential Update -->
            <div class="dev-card">
                <div class="dev-card-header">
                    <div class="dev-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#a78bfa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        <span>Super-Admin Self Credentials</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #a78bfa; font-weight: 600;">Personal Security</span>
                </div>

                <form action="/developer/self/update-credentials" method="POST">
                    <?= csrf_field() ?>

                    <div class="dev-form-group">
                        <label class="dev-label">Full Name</label>
                        <input type="text" name="full_name" class="dev-input" value="<?= esc($currentUser['full_name'] ?? '') ?>" required>
                    </div>

                    <div class="dev-form-group">
                        <label class="dev-label">Username</label>
                        <input type="text" name="username" class="dev-input" value="<?= esc($currentUser['username'] ?? '') ?>" required>
                    </div>

                    <div class="dev-form-group">
                        <label class="dev-label">Recovery Email</label>
                        <input type="email" name="email" class="dev-input" value="<?= esc($currentUser['email'] ?? '') ?>" placeholder="developer@edifies.school">
                    </div>

                    <div class="dev-form-group">
                        <label class="dev-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="new_password" class="dev-input" placeholder="Min. 8 characters" autocomplete="new-password">
                    </div>

                    <div class="dev-form-group">
                        <label class="dev-label" style="color: #f87171;">Current Password (Verification Required) *</label>
                        <input type="password" name="current_password" class="dev-input" required placeholder="Enter current password to authorize changes" autocomplete="current-password">
                    </div>

                    <button type="submit" class="btn-dev-submit" style="width: 100%;">
                        Save Developer Credentials
                    </button>
                </form>
            </div>

            <!-- Card C: System Infrastructure & Telemetry -->
            <div class="dev-card">
                <div class="dev-card-header">
                    <div class="dev-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                        </svg>
                        <span>System Telemetry &amp; Stats</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #34d399; font-weight: 600;">Healthy</span>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                    <div style="background: #1f2937; padding: 1rem; border-radius: 10px;">
                        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Employees</div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #f8fafc; margin-top: 0.25rem;"><?= esc($stats['total_employees']) ?></div>
                    </div>
                    <div style="background: #1f2937; padding: 1rem; border-radius: 10px;">
                        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Punches Logged</div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #38bdf8; margin-top: 0.25rem;"><?= esc($stats['total_punches']) ?></div>
                    </div>
                    <div style="background: #1f2937; padding: 1rem; border-radius: 10px;">
                        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Active Devices</div>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #10b981; margin-top: 0.25rem;"><?= esc($stats['active_devices']) ?></div>
                    </div>
                    <div style="background: #1f2937; padding: 1rem; border-radius: 10px;">
                        <div style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">PHP Engine</div>
                        <div style="font-size: 1.1rem; font-weight: 700; color: #c084fc; margin-top: 0.45rem;"><?= esc($stats['php_version']) ?></div>
                    </div>
                </div>

                <div style="font-size: 0.8rem; color: #94a3b8; line-height: 1.6; border-top: 1px solid #1f2937; padding-top: 1rem;">
                    <div><strong>Server Clock:</strong> <?= esc($stats['server_time_ist']) ?></div>
                    <div><strong>Database Driver:</strong> <?= esc($stats['db_driver']) ?> (Port 3307)</div>
                    <div><strong>Active Timezone:</strong> <?= esc($settings['system_timezone'] ?? 'Asia/Kolkata') ?></div>
                </div>
            </div>

            <!-- Card D: Security & Infrastructure Raw Audit Ledger (Developer / Super-Admin Exclusive) -->
            <div class="dev-card" style="grid-column: 1 / -1;">
                <div class="dev-card-header">
                    <div class="dev-card-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Raw Security &amp; Infrastructure Audit Ledger</span>
                    </div>
                    <span style="font-size: 0.75rem; color: #fbbf24; font-weight: 600;">Super-Admin Exclusive</span>
                </div>

                <div class="table-container">
                    <table class="dev-table">
                        <thead>
                            <tr>
                                <th style="width: 170px;">Timestamp</th>
                                <th style="width: 140px;">Actor</th>
                                <th style="width: 170px;">Event Action</th>
                                <th>Technical Payload &amp; Details</th>
                                <th style="width: 120px;">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentLogs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: #94a3b8; padding: 2rem;">
                                        No technical logs recorded.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td style="font-size: 0.75rem; color: #94a3b8; white-space: nowrap;">
                                            <?= esc($log['created_at']) ?>
                                        </td>
                                        <td>
                                            <span style="font-weight: 600; color: #f8fafc; font-size: 0.8rem;"><?= esc($log['actor_username']) ?></span>
                                        </td>
                                        <td>
                                            <span style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.7rem; background: #1f2937; padding: 0.2rem 0.45rem; border-radius: 4px; color: #38bdf8; border: 1px solid #374151;">
                                                <?= esc($log['action']) ?>
                                            </span>
                                        </td>
                                        <td style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.75rem; color: #cbd5e1; word-break: break-all;">
                                            <?= esc($log['details']) ?>
                                        </td>
                                        <td style="font-family: ui-monospace, SFMono-Regular, monospace; font-size: 0.75rem; color: #94a3b8; white-space: nowrap;">
                                            <?= esc($log['ip_address']) ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </main>

    <!-- Password Reset Override Modal -->
    <div class="dev-modal-overlay" id="resetModal">
        <div class="dev-modal">
            <h3 style="font-size: 1.1rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.5rem;">Override Account Password</h3>
            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 1.25rem;">
                Target Account: <strong id="resetTargetName" style="color: #38bdf8;"></strong> (@<span id="resetTargetUser"></span>)
            </p>

            <form action="" method="POST" id="formResetPassword">
                <?= csrf_field() ?>

                <div class="dev-form-group">
                    <label class="dev-label">New Password (Min 8 chars)</label>
                    <input type="password" name="new_password" class="dev-input" required minlength="8" placeholder="Enter new password">
                </div>

                <div class="dev-form-group">
                    <label class="dev-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="dev-input" required minlength="8" placeholder="Re-enter new password">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                    <button type="button" class="btn-action-sm" onclick="closeResetModal()">Cancel</button>
                    <button type="submit" class="btn-dev-submit">Force Reset Password</button>
                </div>
            </form>
        </div>
    </div>

    <script <?= csp_script_nonce() ?>>
        function openResetModal(userId, username, fullName) {
            document.getElementById('resetTargetName').textContent = fullName;
            document.getElementById('resetTargetUser').textContent = username;
            document.getElementById('formResetPassword').action = '/developer/users/' + encodeURIComponent(userId) + '/reset-password';
            document.getElementById('resetModal').style.display = 'flex';
        }

        function closeResetModal() {
            document.getElementById('resetModal').style.display = 'none';
        }

        document.getElementById('resetModal')?.addEventListener('click', function(e) {
            if (e.target === this) closeResetModal();
        });

        // Invalidate Back-Forward Cache (bfcache) when navigating with browser arrows
        window.addEventListener('pageshow', function (event) {
            if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
                window.location.reload();
            }
        });
    </script>
    <script <?= csp_script_nonce() ?> src="/js/security-shield.js?v=<?= file_exists(FCPATH . 'js/security-shield.js') ? filemtime(FCPATH . 'js/security-shield.js') : time() ?>"></script>
</body>
</html>

