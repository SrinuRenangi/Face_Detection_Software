<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Central Access Gateway</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body style="background-color: var(--gray-100);">
    <header class="navbar">
        <div class="container nav-content">
            <a href="/" class="brand-link">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge">Central Access Gateway</span>
            </a>
            <div class="nav-actions">
                <a href="/attendance" class="btn btn-accent">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Launch Tablet Kiosk</span>
                </a>
                <a href="/kiosk/pair" class="btn btn-secondary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                    <span>Pair Device</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container" style="flex: 1; padding-top: 2.5rem; padding-bottom: 3rem;">
        <div style="text-align: center; max-width: 760px; margin: 0 auto 2.5rem;">
            <h1 style="font-size: 2.25rem; font-weight: 800; color: var(--gray-900); margin-bottom: 0.75rem;">
                Staff Attendance Management System
            </h1>
            <p style="font-size: 1.05rem; color: var(--gray-600); line-height: 1.6;">
                Unified Access Gateway &bull; One deployable PHP application, one website, and one database serving Administrator, Manager, and registered Kiosk Scanner terminals.
            </p>
        </div>

        <!-- Detected Paired Device Banner (Dynamically populated via backend / JS telemetry) -->
        <div id="paired-device-banner" class="alert alert-success" style="max-width: 860px; margin: 0 auto 2rem; display: <?= ($isPairedDevice && $pairedDeviceInfo) ? 'flex' : 'none' ?>; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="padding: 0.5rem; background: rgba(16, 185, 129, 0.15); border-radius: 6px;">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <div>
                    <div style="font-weight: 700; color: #065f46;" id="paired-device-title">
                        Paired Kiosk Terminal Recognized: <?= esc($pairedDeviceInfo['device_name'] ?? 'Authorized Scanner') ?>
                    </div>
                    <div style="font-size: 0.85rem; color: #047857;" id="paired-device-meta">
                        Identifier: <?= esc($pairedDeviceInfo['device_identifier'] ?? '') ?> &bull; Mode: <?= esc($pairedDeviceInfo['terminal_mode'] ?? 'ENTRY_ONLY') ?>
                    </div>
                </div>
            </div>
            <a href="/attendance" class="btn btn-accent btn-sm">Open Scanner Interface &rarr;</a>
        </div>

        <!-- Global Flash Messages -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" style="max-width: 860px; margin: 0 auto 1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" style="max-width: 860px; margin: 0 auto 1.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Central Unified Gateway Grid: Human Sign-In vs Scanner Access -->
        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); max-width: 900px; margin: 0 auto 3rem; gap: 2rem;">
            
            <!-- Left Card: Human Administrative Sign In (Admin & Manager) -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--primary);">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                        <div style="display: inline-flex; padding: 0.65rem; background-color: #dbeafe; color: var(--primary); border-radius: var(--radius-md);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--gray-900); margin: 0;">
                                Staff Sign In
                            </h3>
                            <span style="font-size: 0.8rem; color: var(--gray-500);">Administrator Sign In &amp; Duty Manager</span>
                        </div>
                    </div>

                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1.5rem; line-height: 1.5;">
                        Enter your administrative credentials. The server authenticates your role securely and automatically opens the authorized dashboard.
                    </p>

                    <form action="/login" method="POST">
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" id="username" name="username" class="form-input"
                                   value="<?= esc(old('username')) ?>" placeholder="e.g. admin or manager" required autofocus autocomplete="username">
                        </div>

                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" id="password" name="password" class="form-input"
                                   placeholder="••••••••••••" required autocomplete="current-password">
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <button type="submit" class="btn btn-primary btn-block">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <polyline points="10 17 15 12 10 7"></polyline>
                                    <line x1="15" y1="12" x2="3" y2="12"></line>
                                </svg>
                                <span>Sign In &rarr;</span>
                            </button>
                        </div>
                    </form>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.8rem; color: var(--gray-500);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="16" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12.01" y2="8"></line>
                    </svg>
                    <span>Role is determined server-side: <strong>ADMIN</strong> opens complete control center; <strong>MANAGER</strong> opens staff directory.</span>
                </div>
            </div>

            <!-- Right Card: Scanner Kiosk Access & Device Pairing -->
            <div class="card" style="display: flex; flex-direction: column; justify-content: space-between; border-top: 4px solid var(--accent);">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem;">
                        <div style="display: inline-flex; padding: 0.65rem; background-color: #ccfbf1; color: var(--accent); border-radius: var(--radius-md);">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                        </div>
                        <div>
                            <h3 style="font-size: 1.25rem; font-weight: 800; color: var(--gray-900); margin: 0;">
                                Scanner Kiosk Access
                            </h3>
                            <span style="font-size: 0.8rem; color: var(--gray-500);">Device Principal &bull; Browser / PWA Terminal</span>
                        </div>
                    </div>

                    <p style="font-size: 0.875rem; color: var(--gray-600); margin-bottom: 1.5rem; line-height: 1.5;">
                        A scanner is a registered physical browser or wall tablet, authenticated using an Admin-generated one-time pairing code and cryptographic device cookie.
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <a href="/attendance" class="btn btn-accent btn-block" style="padding: 0.85rem; font-size: 1rem;">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                                <line x1="8" y1="21" x2="16" y2="21"></line>
                                <line x1="12" y1="17" x2="12" y2="21"></line>
                            </svg>
                            <span>Launch Tablet Kiosk &rarr;</span>
                        </a>

                        <div style="text-align: center; font-size: 0.8rem; color: var(--gray-400); text-transform: uppercase; letter-spacing: 0.05em; margin: 0.25rem 0;">
                            &mdash; OR PAIR NEW SCANNER &mdash;
                        </div>

                        <a href="/kiosk/pair" class="btn btn-secondary btn-block" style="padding: 0.75rem;">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                            </svg>
                            <span>Pair Scanner with 1-Time Code &rarr;</span>
                        </a>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--gray-200); font-size: 0.8rem; color: var(--gray-500);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align: -2px;">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Paired devices remain authorized across ordinary browser sessions via secure <code>SameSite=Strict</code> cookie.</span>
                </div>
            </div>
        </div>

        <!-- System Foundation Diagnostics Card -->
        <div class="card" style="max-width: 900px; margin: 0 auto;">
            <h4 class="card-title" style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="16" x2="12" y2="12"></line>
                    <line x1="12" y1="8" x2="12.01" y2="8"></line>
                </svg>
                <span>Backend Foundation Status</span>
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div style="padding: 0.75rem; background: var(--gray-100); border-radius: 6px;">
                    <div style="font-size: 0.75rem; color: var(--gray-500); text-transform: uppercase;">PHP Runtime</div>
                    <div style="font-weight: 700; color: var(--gray-900);">v<?= esc($phpVersion) ?></div>
                </div>
                <div style="padding: 0.75rem; background: var(--gray-100); border-radius: 6px;">
                    <div style="font-size: 0.75rem; color: var(--gray-500); text-transform: uppercase;">Database</div>
                    <div style="font-weight: 700; color: <?= $dbConnected ? '#059669' : '#dc2626' ?>;">
                        <?= $dbConnected ? 'Connected (' . esc($dbPlatform) . ')' : 'Disconnected' ?>
                    </div>
                </div>
                <div style="padding: 0.75rem; background: var(--gray-100); border-radius: 6px;">
                    <div style="font-size: 0.75rem; color: var(--gray-500); text-transform: uppercase;">Server Clock</div>
                    <div style="font-weight: 700; color: var(--gray-900);"><?= esc($serverTime) ?></div>
                </div>
            </div>
        </div>
    </main>

    <footer style="text-align: center; padding: 2rem 1rem; font-size: 0.85rem; color: var(--gray-500); border-top: 1px solid var(--gray-200);">
        Edifies School Staff Attendance Management System &bull; Unified Single-App Architecture &bull; CodeIgniter 4 + MariaDB 11.4
    </footer>

    <!-- Asynchronous Paired Device Detection Telemetry -->
    <script>
        (function() {
            // Check if device is paired via same-origin API status check
            fetch('/api/kiosk/status', {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) {
                if (res.ok) return res.json();
                return null;
            })
            .then(function(data) {
                if (data && data.status === 'success' && data.authorized) {
                    var banner = document.getElementById('paired-device-banner');
                    var title = document.getElementById('paired-device-title');
                    var meta = document.getElementById('paired-device-meta');
                    if (banner && title && meta) {
                        title.textContent = 'Paired Kiosk Terminal Recognized: ' + (data.deviceName || 'Authorized Scanner');
                        meta.textContent = 'Identifier: ' + data.deviceIdentifier + ' • Mode: ' + (data.terminalMode || 'ENTRY_ONLY');
                        banner.style.display = 'flex';
                    }
                }
            })
            .catch(function(err) {
                // Not paired or offline; keep default display
            });
        })();
    </script>
</body>
</html>
