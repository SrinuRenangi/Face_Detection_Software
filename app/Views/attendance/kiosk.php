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
    <style>
        body.kiosk-face-body {
            background-color: #090d16;
            color: #f8fafc;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            margin: 0;
            padding: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            user-select: none;
            -webkit-user-select: none;
        }

        .kiosk-navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            z-index: 20;
        }

        .kiosk-brand-title {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 700;
            font-size: 1.15rem;
            color: #ffffff;
            letter-spacing: -0.01em;
        }

        .kiosk-mode-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: rgba(16, 185, 129, 0.15);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
        }

        .kiosk-viewport-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            box-sizing: border-box;
        }

        .scanner-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            max-width: 580px;
            background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 1.75rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            box-sizing: border-box;
            position: relative;
        }

        .scanner-clock-header {
            text-align: center;
            margin-bottom: 1.25rem;
        }

        .scanner-clock-time {
            font-size: 2.25rem;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.02em;
            line-height: 1;
            margin-bottom: 0.35rem;
        }

        .scanner-clock-date {
            font-size: 0.875rem;
            color: #94a3b8;
            font-weight: 500;
        }

        .camera-viewport-frame {
            position: relative;
            width: 100%;
            max-width: 460px;
            aspect-ratio: 4 / 3;
            background: #020617;
            border-radius: 16px;
            overflow: hidden;
            border: 2px solid rgba(56, 189, 248, 0.3);
            box-shadow: 0 0 30px rgba(56, 189, 248, 0.15);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #scanner-video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform: scaleX(-1); /* Natural mirror orientation */
        }

        #scanner-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            transform: scaleX(-1);
        }

        /* Futuristic Face Guide Reticle Overlay */
        .face-scan-reticle {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 55%;
            height: 65%;
            border: 2px dashed rgba(56, 189, 248, 0.5);
            border-radius: 50%;
            pointer-events: none;
            box-shadow: 0 0 15px rgba(56, 189, 248, 0.2);
            animation: reticle-pulse 3s infinite ease-in-out;
        }

        @keyframes reticle-pulse {
            0%, 100% { border-color: rgba(56, 189, 248, 0.4); box-shadow: 0 0 10px rgba(56, 189, 248, 0.15); }
            50% { border-color: rgba(56, 189, 248, 0.9); box-shadow: 0 0 25px rgba(56, 189, 248, 0.35); }
        }

        /* Status Badge Underneath Camera */
        .scanner-status-badge {
            margin-top: 1.25rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 9999px;
            font-size: 0.875rem;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-align: center;
        }

        .badge-loading {
            background: rgba(245, 158, 11, 0.15);
            border: 1px solid rgba(245, 158, 11, 0.3);
            color: #fbbf24;
        }

        .badge-ready {
            background: rgba(56, 189, 248, 0.15);
            border: 1px solid rgba(56, 189, 248, 0.3);
            color: #38bdf8;
        }

        .badge-searching {
            background: rgba(168, 85, 247, 0.15);
            border: 1px solid rgba(168, 85, 247, 0.3);
            color: #c084fc;
        }

        .badge-processing {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            color: #60a5fa;
            animation: pulse-badge 1s infinite alternate;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
        }

        .badge-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #f87171;
        }

        .badge-info {
            background: rgba(148, 163, 184, 0.15);
            border: 1px solid rgba(148, 163, 184, 0.3);
            color: #cbd5e1;
        }

        @keyframes pulse-badge {
            0% { transform: scale(1); }
            100% { transform: scale(1.03); }
        }

        /* Celebratory Punch Success Card Overlay */
        .punch-success-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.95);
            border-radius: 20px;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            z-index: 30;
            box-sizing: border-box;
            animation: fade-in 0.25s ease-out;
        }

        @keyframes fade-in {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .success-icon-box {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            margin-bottom: 1rem;
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
        }

        .success-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 0.35rem;
            text-align: center;
        }

        .success-action-badge {
            padding: 0.4rem 1rem;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            color: #34d399;
            border-radius: 9999px;
            font-weight: 700;
            font-size: 0.9rem;
            margin-bottom: 0.75rem;
        }

        .success-time-text {
            font-size: 0.85rem;
            color: #94a3b8;
        }

        /* Inactivity Ambient Sleep Screen */
        .scanner-sleep-screen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #020617;
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 100;
            cursor: pointer;
            transition: opacity 0.5s ease-in-out;
            opacity: 0;
        }

        .scanner-sleep-screen.active {
            display: flex;
            opacity: 1;
        }

        .sleep-clock {
            font-size: 5rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.85);
            letter-spacing: -0.03em;
            margin-bottom: 1rem;
        }

        .sleep-subtitle {
            font-size: 1.15rem;
            color: #38bdf8;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.6rem;
        }

        .sleep-pulse {
            width: 10px;
            height: 10px;
            background: #38bdf8;
            border-radius: 50%;
            box-shadow: 0 0 10px #38bdf8;
            animation: pulse-dot 2s infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 0.3; transform: scale(0.9); }
            50% { opacity: 1; transform: scale(1.2); }
        }
    </style>
</head>
<body class="kiosk-face-body">

    <!-- Clean Kiosk Header (No extra buttons) -->
    <header class="kiosk-navbar">
        <div class="kiosk-brand-title">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2">
                <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
            </svg>
            <span>Edifies School</span>
            <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 500;">Attendance Kiosk &bull; Touchless Presence Verification System &bull; Terminal: <?= esc($currentDevice['device_identifier'] ?? 'Online') ?></span>
        </div>

        <div class="kiosk-mode-badge">
            <div style="width: 8px; height: 8px; border-radius: 50%; background: #34d399; box-shadow: 0 0 6px #34d399;"></div>
            <span><?= ($currentDevice['terminal_mode'] ?? 'ENTRY_ONLY') === 'EXIT_ONLY' ? 'CHECK-OUT SCANNER' : 'CHECK-IN SCANNER' ?></span>
        </div>
    </header>

    <!-- Center Scanner Viewport -->
    <main class="kiosk-viewport-container">
        <div class="scanner-card">
            
            <!-- Live Clock Display -->
            <div class="scanner-clock-header">
                <div class="scanner-clock-time" id="kiosk-live-clock"><?= esc($serverTimeIST) ?></div>
                <div class="scanner-clock-date" id="kiosk-live-date"><?= esc($serverDateIST) ?></div>
            </div>

            <!-- Camera Viewport with Facial Guide Reticle -->
            <div class="camera-viewport-frame">
                <video id="scanner-video" autoplay playsinline muted></video>
                <canvas id="scanner-canvas"></canvas>
                <div class="face-scan-reticle"></div>
            </div>

            <!-- Dynamic Biometric Status Pill -->
            <div id="scanner-status-pill" class="scanner-status-badge badge-loading">
                <span class="status-text">Starting Face Recognition Scanner...</span>
            </div>

            <!-- Celebratory Punch Success Card -->
            <div class="punch-success-overlay" id="punch-success-card">
                <div class="success-icon-box">
                    <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div class="success-name" id="success-emp-name">Katherine Johnson</div>
                <div class="success-action-badge" id="success-action-text">Check-In (Entry) Recorded</div>
                <div class="success-time-text" id="success-time-text">07:45 PM</div>
            </div>
        </div>
    </main>

    <!-- Inactivity Ambient Sleep Screen (Tap or Step in Front to Wake) -->
    <div class="scanner-sleep-screen" id="scanner-sleep-overlay">
        <div class="sleep-clock" id="sleep-clock-display"><?= esc($serverTimeIST) ?></div>
        <div class="sleep-subtitle">
            <div class="sleep-pulse"></div>
            <span>Scanner is sleeping &bull; Stand in front or touch screen to wake</span>
        </div>
    </div>

    <!-- Scripts: Face API & Dedicated Edge Scanner Engine -->
    <script src="/js/face-api.js"></script>
    <script src="/js/kiosk-face-attendance.js"></script>
    <script src="/js/kiosk-clock.js"></script>
</body>
</html>
