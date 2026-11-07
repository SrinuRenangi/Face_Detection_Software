<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body style="background-color: var(--gray-100);">
    <header class="navbar">
        <div class="container nav-content">
            <a href="/" class="brand-link">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge">Scanner Pairing</span>
            </a>
            <div class="nav-actions">
                <a href="/attendance" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>Launch Scanner</span>
                </a>
                <a href="/login" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <span>Staff Sign In</span>
                </a>
            </div>
        </div>
    </header>

    <div class="auth-wrapper" style="max-width: 560px;">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-icon" style="background-color: #ccfbf1; color: var(--accent);">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--gray-900); margin-bottom: 0.35rem;">
                    Scanner Device Pairing
                </h2>
                <p style="font-size: 0.875rem; color: var(--gray-500);">
                    Pair this browser or mobile device as a dedicated attendance terminal
                </p>
            </div>

            <!-- Error Banner -->
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span><?= esc(session()->getFlashdata('error')) ?></span>
                </div>
            <?php endif; ?>

            <!-- Success Banner -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span><?= esc(session()->getFlashdata('success')) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($isPaired && $pairedDevice): ?>
                <div class="alert alert-success" style="display: block; margin-bottom: 1.5rem;">
                    <div style="font-weight: 700; margin-bottom: 0.25rem;">
                        Device Already Paired &amp; Active
                    </div>
                    <div style="font-size: 0.85rem;">
                        This browser is currently authorized as: <strong><?= esc($pairedDevice['device_name']) ?></strong> (<?= esc($pairedDevice['device_identifier']) ?>). Mode: <strong><?= esc($pairedDevice['terminal_mode']) ?></strong>.
                    </div>
                    <div style="margin-top: 0.75rem;">
                        <a href="/attendance" class="btn btn-accent btn-sm">Open Scanner Interface &rarr;</a>
                    </div>
                </div>
            <?php endif; ?>

            <form action="/kiosk/pair" method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label for="pairing_code" class="form-label">
                        Single-Use Pairing Code
                        <span style="font-weight: 400; color: var(--gray-500);">(from Admin &gt; Kiosks)</span>
                    </label>
                    <input type="text" id="pairing_code" name="pairing_code" class="form-input"
                           value="<?= esc(old('pairing_code')) ?>"
                           placeholder="e.g. A1B2C3D4E5F60718A1B2C3D4E5F60718"
                           maxlength="40" required autofocus autocomplete="off" autocorrect="off" autocapitalize="characters"
                           style="letter-spacing: 0.1em; font-family: monospace; font-size: 1rem; text-transform: uppercase;">
                    <small style="display: block; margin-top: 0.35rem; color: var(--gray-500); font-size: 0.8rem;">
                        Paste or type the 32-character pairing code generated by the School Administrator.
                    </small>
                </div>

                <div class="form-group">
                    <label for="device_name" class="form-label">Device Label (Optional)</label>
                    <input type="text" id="device_name" name="device_name" class="form-input"
                           value="<?= esc(old('device_name')) ?>" placeholder="e.g. Front Gate Android Tablet" maxlength="64">
                </div>

                <div class="form-group">
                    <label for="device_type" class="form-label">Device Type</label>
                    <select id="device_type" name="device_type" class="form-input">
                        <option value="TABLET" selected>Tablet (Mounted Wall Display)</option>
                        <option value="SMARTPHONE">Smartphone (Mobile Scanner)</option>
                        <option value="DESKTOP">Desktop / Laptop (Webcam)</option>
                    </select>
                </div>

                <div style="margin-top: 1.75rem;">
                    <button type="submit" class="btn btn-accent btn-block">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        <span>Authorize &amp; Pair Scanner</span>
                    </button>
                </div>
            </form>

            <div style="margin-top: 1.5rem; text-align: center; border-top: 1px solid var(--gray-200); padding-top: 1rem;">
                <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.5rem;">
                    Human staff or manager?
                </p>
                <a href="/login" class="btn btn-secondary btn-sm">Administrator / Manager Sign In &rarr;</a>
            </div>
        </div>
    </div>
</body>
</html>
