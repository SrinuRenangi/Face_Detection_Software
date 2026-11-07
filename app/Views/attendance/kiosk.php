<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0f172a">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
</head>
<body class="kiosk-body">
    <div class="kiosk-layout">
        <!-- Multi-Device Kiosk Header -->
        <header class="kiosk-header">
            <div class="kiosk-brand">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;" id="kiosk-header-form-factor">Attendance Kiosk</span>
            </div>

            <!-- Terminal Device Authorization & Sync Status Badge -->
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <div class="wake-lock-pill" id="wake-lock-status" title="Screen Wake Lock holds display on while kiosk is active in foreground">
                    <span>💡 Screen Awake</span>
                </div>

                <div id="kiosk-device-badge" class="kiosk-status-pill">
                    <div class="pulse-dot"></div>
                    <span id="kiosk-device-name">Terminal: <?= esc($currentDevice['device_identifier'] ?? $registeredDevices[0]['device_identifier'] ?? 'Unpaired Terminal') ?></span>
                </div>

                <a href="/kiosk/pair" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border-color: rgba(255,255,255,0.2); color: #94a3b8;" title="Pair or Re-pair Scanner">
                    Pair
                </a>
                <a href="/" class="btn btn-secondary btn-sm" style="padding: 0.25rem 0.6rem; font-size: 0.75rem; border-color: rgba(255,255,255,0.2); color: #94a3b8;" title="Central Access Gateway">
                    Gateway
                </a>
            </div>
        </header>

        <!-- Kiosk Main Viewport (Adapts between Smartphone Portrait & Tablet Landscape) -->
        <main class="kiosk-main">
            <div class="kiosk-terminal-card" id="kiosk-card">
                <div class="kiosk-tablet-grid">
                    <!-- Left Column in Tablet Landscape / Top in Smartphone Portrait -->
                    <div class="kiosk-sidebar">
                        <!-- Live Clock (IST) -->
                        <div class="clock-display" id="kiosk-live-clock">
                            <?= esc($serverTimeIST) ?>
                        </div>
                        <div class="date-display" id="kiosk-live-date">
                            <?= esc($serverDateIST) ?>
                        </div>

                        <!-- Attendance Instructions & Guidance -->
                        <div class="kiosk-notice" style="margin-top: 1rem;">
                            <p style="font-weight: 600; color: #38bdf8; margin-bottom: 0.25rem;">
                                Touchless Presence Verification System
                            </p>
                            <p style="font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.5rem;">
                                Stand in front of the camera for touchless presence verification or use the fallback keypad.
                            </p>
                            <p style="font-weight: 600; color: #38bdf8; margin-bottom: 0.25rem;">
                                Staff PIN Attendance
                            </p>
                            <p style="font-size: 0.85rem; color: #94a3b8;">
                                Enter your Employee ID and 6-digit PIN on the keypad, then confirm your identity to record check-in or check-out.
                            </p>
                        </div>

                        <!-- Biometric Integration Boundary Notice (Part 3.3) -->
                        <div style="margin-top: 0.75rem; padding: 0.6rem 0.75rem; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px;">
                            <div style="display: flex; align-items: center; gap: 0.4rem; font-size: 0.75rem; font-weight: 700; color: #fbbf24;">
                                <span>🔒 Biometric Engine Staged</span>
                            </div>
                            <p style="font-size: 0.725rem; color: #d1d5db; margin: 0.25rem 0 0; line-height: 1.4;">
                                Automated face identification is disabled in production pending owner biometric acceptance. Verified Staff PIN attendance with camera photo audit is active.
                            </p>
                        </div>

                        <!-- Hardware Profiling Telemetry Pill -->
                        <div style="margin-top: 1.25rem; font-size: 0.75rem; color: #64748b; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <span id="telemetry-tier" style="background: rgba(255,255,255,0.05); padding: 0.2rem 0.4rem; border-radius: 4px;">Tier: Auto-Detecting</span>
                            <span id="telemetry-resolution" style="background: rgba(255,255,255,0.05); padding: 0.2rem 0.4rem; border-radius: 4px;">Resolution: 640x480</span>
                        </div>
                    </div>

                    <!-- Right Column: Interactive PIN Attendance Fallback Subsystem -->
                    <div>
                        <div class="kiosk-pin-panel">
                            <!-- Step 1: Employee ID & PIN Entry -->
                            <div id="pin-entry-step" class="kiosk-panel-step">
                                <div class="pin-panel-header">
                                    <h3 class="pin-panel-title">PIN Attendance</h3>
                                    <p class="pin-panel-subtitle">Enter your Employee ID and 6-digit numeric PIN</p>
                                </div>

                                <div id="pin-alert-box" class="kiosk-alert" style="display: none;"></div>

                                <div class="pin-form-group">
                                    <label for="kiosk-emp-id-input" class="pin-label">Employee ID or Code</label>
                                    <input type="text" id="kiosk-emp-id-input" class="kiosk-input" placeholder="e.g. EMP001" autocomplete="off" autocorrect="off" autocapitalize="characters" spellcheck="false" maxlength="36">
                                </div>

                                <div class="pin-form-group">
                                    <label class="pin-label">6-Digit PIN</label>
                                    <div class="pin-dots-container" id="pin-dots-indicator" aria-label="PIN Digits entered">
                                        <span class="pin-dot" data-idx="0"></span>
                                        <span class="pin-dot" data-idx="1"></span>
                                        <span class="pin-dot" data-idx="2"></span>
                                        <span class="pin-dot" data-idx="3"></span>
                                        <span class="pin-dot" data-idx="4"></span>
                                        <span class="pin-dot" data-idx="5"></span>
                                    </div>
                                </div>

                                <!-- 3x4 Touch Numeric Keypad -->
                                <div class="pin-keypad" id="pin-keypad">
                                    <button type="button" class="pin-key" data-digit="1">1</button>
                                    <button type="button" class="pin-key" data-digit="2">2</button>
                                    <button type="button" class="pin-key" data-digit="3">3</button>
                                    <button type="button" class="pin-key" data-digit="4">4</button>
                                    <button type="button" class="pin-key" data-digit="5">5</button>
                                    <button type="button" class="pin-key" data-digit="6">6</button>
                                    <button type="button" class="pin-key" data-digit="7">7</button>
                                    <button type="button" class="pin-key" data-digit="8">8</button>
                                    <button type="button" class="pin-key" data-digit="9">9</button>
                                    <button type="button" class="pin-key pin-key-action" data-action="clear" title="Clear">C</button>
                                    <button type="button" class="pin-key" data-digit="0">0</button>
                                    <button type="button" class="pin-key pin-key-action" data-action="backspace" title="Backspace">⌫</button>
                                </div>

                                <div class="pin-actions">
                                    <button type="button" id="btn-verify-pin" class="btn-kiosk-primary" disabled>
                                        <span class="btn-text">Verify PIN &amp; Proceed</span>
                                        <span class="btn-spinner" style="display: none;">Verifying...</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Step 2: Employee Confirmation Screen -->
                            <div id="pin-confirm-step" class="kiosk-panel-step" style="display: none;">
                                <div class="kiosk-confirm-card">
                                    <div class="confirm-badge-icon">
                                        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                    </div>
                                    <h3 class="confirm-title">Confirm Attendance</h3>
                                    
                                    <div class="confirm-details">
                                        <div class="confirm-name" id="confirm-emp-name">Employee Name</div>
                                        <div class="confirm-meta">
                                            <span id="confirm-emp-code">EMP001</span> &bull; <span id="confirm-emp-dept">Teaching</span>
                                        </div>
                                        <div class="confirm-action-box" id="confirm-action-box">
                                            <span class="confirm-action-label">TERMINAL ACTION:</span>
                                            <span class="confirm-action-badge badge-entry" id="confirm-action-badge">CHECK IN (ENTRY)</span>
                                        </div>
                                    </div>

                                    <!-- Milestone 3: Live Camera Photo Capture & Preview -->
                                    <div class="kiosk-camera-section" id="kiosk-camera-section">
                                        <div class="kiosk-camera-viewport-box" id="kiosk-camera-box">
                                            <video id="kiosk-camera-stream" autoplay playsinline muted></video>
                                            <img id="kiosk-photo-preview" alt="Snapshot Preview" style="display: none;">
                                            <canvas id="kiosk-camera-canvas" style="display: none;"></canvas>
                                            <div id="kiosk-camera-placeholder" class="camera-placeholder-msg" style="display: none;">
                                                <span>Camera preview unavailable</span>
                                            </div>
                                        </div>
                                        <div class="kiosk-camera-controls">
                                            <button type="button" id="btn-snap-photo" class="btn-kiosk-snap">
                                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <circle cx="12" cy="13" r="4"></circle>
                                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                                </svg>
                                                <span>Capture Photo</span>
                                            </button>
                                            <button type="button" id="btn-retake-photo" class="btn-kiosk-retake" style="display: none;">
                                                <span>Retake</span>
                                            </button>
                                            <label for="kiosk-photo-fallback-input" class="btn-kiosk-fallback" title="Upload Photo File (Fallback)">
                                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                                    <polyline points="17 8 12 3 7 8"></polyline>
                                                    <line x1="12" y1="3" x2="12" y2="15"></line>
                                                </svg>
                                                <span>Upload</span>
                                                <input type="file" id="kiosk-photo-fallback-input" accept="image/jpeg,image/png,image/*" capture="user" style="display: none;">
                                            </label>
                                        </div>
                                        <div id="kiosk-photo-status" class="kiosk-photo-status-indicator">
                                            <span class="photo-status-dot" id="photo-status-dot"></span>
                                            <span id="kiosk-photo-status-text">Photo capture required</span>
                                        </div>
                                    </div>

                                    <div id="confirm-alert-box" class="kiosk-alert" style="display: none;"></div>

                                    <div class="confirm-buttons">
                                        <button type="button" id="btn-confirm-punch" class="btn-kiosk-success">
                                            <span class="btn-text">Confirm Punch</span>
                                            <span class="btn-spinner" style="display: none;">Recording...</span>
                                        </button>
                                        <button type="button" id="btn-cancel-confirm" class="btn-kiosk-secondary">
                                            Cancel
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 3: Success Screen -->
                            <div id="pin-success-step" class="kiosk-panel-step" style="display: none;">
                                <div class="kiosk-success-card">
                                    <div class="success-icon-wrapper">
                                        <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2.5">
                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                        </svg>
                                    </div>
                                    <h3 class="success-title">Attendance Recorded!</h3>
                                    <p class="success-subtitle" id="success-message">Check-in confirmed successfully.</p>
                                    
                                    <!-- Photo Thumbnail Preview on Success -->
                                    <div id="success-photo-container" style="display: none;">
                                        <img id="success-photo-thumb" class="success-photo-thumb" alt="Captured attendance photo">
                                    </div>

                                    <div class="success-details-box" id="success-details">
                                        <!-- Injected by JS -->
                                    </div>
                                    <div class="success-countdown">
                                        Resetting screen in <span id="success-countdown-sec">3</span>s...
                                    </div>
                                    <button type="button" id="btn-reset-now" class="btn-kiosk-secondary" style="margin-top: 1rem;">
                                        Done (Next Staff)
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer style="text-align: center; padding: 1rem; font-size: 0.75rem; color: #64748b; border-top: 1px solid rgba(255, 255, 255, 0.05);">
            Edifies School Attendance Kiosk &bull; Multi-Device PWA (Smartphones & Tablets) &bull; Offline IndexedDB Enabled
        </footer>
    </div>

    <!-- Multi-Device Kiosk Engine & Offline Queue -->
    <script src="/js/edifies-multidevice-kiosk.js"></script>
    <!-- Employee PIN Attendance Fallback Subsystem (Task 2) -->
    <script src="/js/kiosk-pin-attendance.js"></script>
    <!-- External Clock Updater & Form-Factor Display (Zero Inline Nonce Required) -->
    <script src="/js/kiosk-clock.js"></script>
</body>
</html>
