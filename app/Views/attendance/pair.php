<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner Device Pairing &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style <?= csp_style_nonce() ?>>
        .pair-card {
            animation: fadeIn 0.25s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="pair-body">

    <div class="pair-card">
        <!-- Brand / Terminal Icon -->
        <div class="pair-icon">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
        </div>

        <h1 class="pair-title">Scanner Device Pairing</h1>
        <p class="pair-subtitle">Enter the single-use 32-character pairing code from the School Administrator</p>

        <!-- Active Paired Device Status -->
        <?php if ($isPaired && $pairedDevice): ?>
            <div class="alert-paired" style="margin-bottom: 1.5rem; text-align: left;">
                <div>
                    <strong>Device Already Paired</strong>
                    <div style="font-size: 0.75rem; color: #047857;"><?= esc($pairedDevice['device_name'] ?? 'Kiosk Terminal') ?></div>
                </div>
                <a href="/attendance" class="btn-open-scanner">Open Scanner &rarr;</a>
            </div>
        <?php endif; ?>

        <!-- Error Banner -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error" style="text-align: left;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Minimalist Single-Field Pairing Form -->
        <form action="/kiosk/pair" method="POST">
            <?= csrf_field() ?>

            <div class="form-group" style="text-align: left; margin-bottom: 1.5rem;">
                <label for="pairing_code" class="form-label">Single-Use Pairing Code</label>
                <input
                    type="text"
                    id="pairing_code"
                    name="pairing_code"
                    class="pairing-input"
                    value="<?= esc(old('pairing_code')) ?>"
                    placeholder="ENTER 32-CHAR CODE"
                    maxlength="40"
                    required
                    autofocus
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="characters"
                    spellcheck="false"
                >
            </div>

            <button type="submit" class="btn-pair">
                <span>Authorize &amp; Pair Scanner</span>
                <span>&rarr;</span>
            </button>
        </form>

        <a href="/" class="link-back">
            <span>&larr; Return to Sign In</span>
        </a>
    </div>

    <div class="portal-footer" style="margin-top: 2rem;">
        &copy; <?= date('Y') ?> Edifies School &bull; Dedicated Attendance Scanner Terminal
    </div>

</body>
</html>
