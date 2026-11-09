<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle ?? 'Settings') ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.dash-body {
            background-color: #f8fafc;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
        }

        .settings-content {
            margin-left: 250px;
            flex: 1;
            padding: 2rem 2.5rem;
            max-width: 1100px;
        }

        @media (max-width: 900px) {
            .settings-content {
                margin-left: 0;
                padding: 1.5rem 1rem;
            }
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.02em;
            margin-bottom: 0.35rem;
        }

        .page-subtitle {
            font-size: 0.9rem;
            color: #64748b;
        }

        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 2rem;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.25rem;
            font-size: 0.9rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .tab-btn:hover {
            color: #0f172a;
        }

        .tab-btn.active {
            color: #1e40af;
            border-bottom-color: #1e40af;
        }

        .tab-panel {
            display: none;
            animation: fadeIn 0.2s ease-out;
        }

        .tab-panel.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .settings-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            padding: 1.75rem 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .card-heading {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.4rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-description {
            font-size: 0.85rem;
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }

        @media (max-width: 700px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group.full-width {
            grid-column: span 2;
        }

        @media (max-width: 700px) {
            .form-group.full-width {
                grid-column: span 1;
            }
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem 0.95rem;
            font-size: 0.9rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            color: #0f172a;
            outline: none;
            transition: all 0.15s ease;
            box-sizing: border-box;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .form-input:disabled {
            background: #f1f5f9;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-save {
            background: #1e40af;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.2);
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-save:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }

        /* Alerts */
        .alert {
            padding: 0.85rem 1.25rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .alert-success {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        /* Scope Cards */
        .perm-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.25rem;
        }

        .perm-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.25rem;
            background: #f8fafc;
        }

        .perm-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .perm-desc {
            font-size: 0.825rem;
            color: #64748b;
            line-height: 1.5;
        }
    </style>
</head>
<body class="dash-body">

    <!-- Admin & Manager Global Left Navigation -->
    <?= view('admin/common/navbar', ['activeNav' => 'settings']) ?>

    <!-- Main Content Area -->
    <main class="settings-content">

        <div class="page-header">
            <h1 class="page-title">Settings &amp; Security</h1>
            <p class="page-subtitle">Manage your personal credentials, session security, and institution preferences.</p>
        </div>

        <!-- Flash Alerts -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <span>&#10003;</span>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error">
                <span>&#9888;</span>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Tab Controls -->
        <div class="settings-tabs">
            <button type="button" class="tab-btn active" onclick="switchTab('tab-profile')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
                <span>Profile &amp; Password</span>
            </button>

            <button type="button" class="tab-btn" onclick="switchTab('tab-organization')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <span>Organization &amp; Tenant</span>
            </button>

            <button type="button" class="tab-btn" onclick="switchTab('tab-permissions')">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
                <span>Permissions &amp; Role Scope</span>
            </button>
        </div>

        <!-- TAB 1: Profile & Password -->
        <div id="tab-profile" class="tab-panel active">

            <!-- Card 1: Personal Details -->
            <div class="settings-card">
                <div class="card-heading">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>User Profile Information</span>
                </div>
                <p class="card-description">Update your display name, username, and contact email address.</p>

                <form action="/admin/settings/profile" method="POST">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-input" value="<?= esc($user['full_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="username">Username *</label>
                            <input type="text" id="username" name="username" class="form-input" value="<?= esc($user['username']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input type="email" id="email" name="email" class="form-input" value="<?= esc($user['email'] ?? '') ?>" placeholder="user@edifies.school">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Assigned Role</label>
                            <input type="text" class="form-input" value="<?= esc($user['role']) ?>" disabled>
                        </div>
                    </div>

                    <button type="submit" class="btn-save">
                        <span>Save Profile Changes</span>
                    </button>
                </form>
            </div>

            <!-- Card 2: Security & Password Change -->
            <div class="settings-card">
                <div class="card-heading">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <span>Change Password</span>
                </div>
                <p class="card-description">Update your password regularly to maintain high security standards.</p>

                <div class="alert alert-warning" style="margin-bottom: 1.5rem;">
                    <span>&#9888;</span>
                    <span><strong>Session Notice:</strong> Changing your password will immediately invalidate all active sessions and require you to sign in again with your new credentials.</span>
                </div>

                <form action="/admin/settings/password" method="POST">
                    <?= csrf_field() ?>

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password *</label>
                        <input type="password" id="current_password" name="current_password" class="form-input" required placeholder="Enter your existing password" autocomplete="current-password">
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" class="form-input" required minlength="8" placeholder="Minimum 8 characters" autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required minlength="8" placeholder="Re-enter new password" autocomplete="new-password">
                        </div>
                    </div>

                    <button type="submit" class="btn-save" style="background: #dc2626;">
                        <span>Update Password &bull; Invalidate Session</span>
                    </button>
                </form>
            </div>

        </div>

        <!-- TAB 2: Organization & Tenant -->
        <div id="tab-organization" class="tab-panel">
            <div class="settings-card">
                <div class="card-heading">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Institution &amp; Tenant Configuration</span>
                </div>
                <p class="card-description">Global school parameters, academic calendar, and biometric kiosk policies.</p>

                <?php if (!$isAdmin): ?>
                    <div class="alert alert-warning">
                        <span>&#9888;</span>
                        <span><strong>Read-Only Access:</strong> Organization parameters can only be altered by users with the <code>ROLE_ADMIN</code> privilege.</span>
                    </div>
                <?php endif; ?>

                <form action="/admin/settings/organization" method="POST">
                    <?= csrf_field() ?>

                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label" for="school_name">Institution / School Name *</label>
                            <input type="text" id="school_name" name="school_name" class="form-input" value="<?= esc($settings['school_name'] ?? 'Edifies School') ?>" <?= !$isAdmin ? 'disabled' : 'required' ?>>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="academic_year">Operational Academic Year</label>
                            <input type="text" id="academic_year" name="academic_year" class="form-input" value="<?= esc($settings['academic_year'] ?? '2026-2027') ?>" <?= !$isAdmin ? 'disabled' : '' ?>>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="system_timezone">Official System Timezone</label>
                            <select id="system_timezone" name="system_timezone" class="form-input" <?= !$isAdmin ? 'disabled' : '' ?>>
                                <option value="Asia/Kolkata" <?= (($settings['system_timezone'] ?? '') === 'Asia/Kolkata') ? 'selected' : '' ?>>Asia/Kolkata (IST, +05:30)</option>
                                <option value="UTC" <?= (($settings['system_timezone'] ?? '') === 'UTC') ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                                <option value="America/New_York" <?= (($settings['system_timezone'] ?? '') === 'America/New_York') ? 'selected' : '' ?>>America/New_York (EST)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="kiosk_cooldown_seconds">Kiosk Re-Punch Cooldown (Seconds)</label>
                            <input type="number" id="kiosk_cooldown_seconds" name="kiosk_cooldown_seconds" class="form-input" min="10" max="600" value="<?= esc($settings['kiosk_cooldown_seconds'] ?? '60') ?>" <?= !$isAdmin ? 'disabled' : '' ?>>
                            <span style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: block;">Prevents accidental rapid double punches on facial detection.</span>
                        </div>
                    </div>

                    <?php if ($isAdmin): ?>
                        <button type="submit" class="btn-save">
                            <span>Save Organization Settings</span>
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- TAB 3: Permissions & Role Scope -->
        <div id="tab-permissions" class="tab-panel">
            <div class="settings-card">
                <div class="card-heading">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <polyline points="12 6 12 12 14 14"></polyline>
                    </svg>
                    <span>Role Permissions Matrix: <?= esc($userRole) ?></span>
                </div>
                <p class="card-description">Overview of active system capabilities authorized for your account tier.</p>

                <div class="perm-grid">
                    <div class="perm-card">
                        <div class="perm-title">
                            <span style="color: #10b981;">&#10003;</span>
                            <span>Staff Directory &amp; Records</span>
                        </div>
                        <div class="perm-desc">
                            <?= ($isAdmin) ? 'Full read, create, update, biometric enrollment, and deletion authority.' : 'Read-only access to employee profiles, departments, and active statuses.' ?>
                        </div>
                    </div>

                    <div class="perm-card">
                        <div class="perm-title">
                            <span style="color: <?= ($isAdmin) ? '#10b981' : '#f87171' ?>;"><?= ($isAdmin) ? '&#10003;' : '&#10005;' ?></span>
                            <span>Scanner Device Pairing</span>
                        </div>
                        <div class="perm-desc">
                            <?= ($isAdmin) ? 'Generate one-time connection keys, register tablets, and revoke compromised terminals.' : 'Restricted. Only full Administrators can authorize physical terminals.' ?>
                        </div>
                    </div>

                    <div class="perm-card">
                        <div class="perm-title">
                            <span style="color: <?= ($isAdmin) ? '#10b981' : '#f87171' ?>;"><?= ($isAdmin) ? '&#10003;' : '&#10005;' ?></span>
                            <span>Attendance Reports &amp; Photo Audits</span>
                        </div>
                        <div class="perm-desc">
                            <?= ($isAdmin) ? 'Access daily logs, monthly aggregates, photo verification trail, and audit decision queues.' : 'Restricted to operational staff reviews.' ?>
                        </div>
                    </div>

                    <div class="perm-card">
                        <div class="perm-title">
                            <span style="color: #10b981;">&#10003;</span>
                            <span>Profile &amp; Credential Self-Service</span>
                        </div>
                        <div class="perm-desc">
                            Update your personal display profile and rotate password with automatic session revocation.
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </main>

    <script <?= csp_script_nonce() ?>>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));

            const panel = document.getElementById(tabId);
            if (panel) panel.classList.add('active');

            const activeBtn = Array.from(document.querySelectorAll('.tab-btn')).find(b => b.getAttribute('onclick')?.includes(tabId));
            if (activeBtn) activeBtn.classList.add('active');
        }
    </script>
</body>
</html>

