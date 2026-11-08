<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edifies School &bull; Staff Attendance Portal</title>
    <link rel="stylesheet" href="/css/style.css">
    <style <?= csp_style_nonce() ?>>
        /* View-specific polish with CSP Level 3 nonce */
        .portal-card {
            animation: fadeIn 0.25s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="portal-body">

    <div class="portal-card">
        <!-- School Brand Header -->
        <div class="brand-header">
            <div class="brand-logo-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <h1 class="brand-title">Edifies School</h1>
            <p class="brand-subtitle">Staff Attendance Management System</p>
        </div>

        <!-- Paired Device Recognition Banner -->
        <?php if ($isPairedDevice && $pairedDeviceInfo): ?>
            <div class="alert-paired" id="paired-device-banner">
                <div>
                    <strong>Authorized Scanner</strong>
                    <div style="font-size: 0.75rem; color: #047857;"><?= esc($pairedDeviceInfo['device_name'] ?? 'Kiosk Terminal') ?></div>
                </div>
                <a href="/attendance" class="btn-open-scanner">Launch Scanner &rarr;</a>
            </div>
        <?php endif; ?>

        <!-- Error & Flash Alerts -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert-success">
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Staff Sign In Form -->
        <h2 style="font-size: 1.05rem; font-weight: 600; color: #1e293b; margin: 0 0 1.25rem 0; text-align: center;">Administrator Sign In</h2>

        <form action="/login" method="POST">
            <?= csrf_field() ?>

            <div style="margin-bottom: 1.15rem;">
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="e.g. admin or manager" required autofocus autocomplete="username" value="<?= esc(old('username')) ?>">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label for="password" class="form-label">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-submit">
                <span>Staff Sign In</span>
                <span>&rarr;</span>
            </button>
        </form>

        <div class="divider">
            <span>or</span>
        </div>

        <!-- Dedicated Scanner Pairing Gateway Link -->
        <a href="/kiosk/pair" class="btn-kiosk-action">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span>Pair Scanner with 1-Time Code &rarr;</span>
        </a>
    </div>

    <div class="portal-footer">
        &copy; <?= date('Y') ?> Edifies School &bull; Staff Attendance System
    </div>

</body>
</html>
