<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edifies School &bull; Staff Attendance System</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.portal-body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%);
            margin: 0;
            padding: 1.5rem;
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            color: #1e293b;
            box-sizing: border-box;
        }

        .portal-card {
            width: 100%;
            max-width: 420px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
            padding: 2.5rem 2.25rem 2rem;
            box-sizing: border-box;
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .brand-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .brand-logo-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: #ffffff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 0.85rem;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        .brand-title {
            font-size: 1.55rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.25rem 0;
            letter-spacing: -0.025em;
        }

        .brand-subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
            font-weight: 500;
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
            font-size: 0.95rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            transition: all 0.15s ease-in-out;
            box-sizing: border-box;
            background: #f8fafc;
            color: #0f172a;
        }

        .form-input:focus {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        .btn-submit {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff !important;
            background: #1e40af !important;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.15s ease-in-out, transform 0.05s ease;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-sizing: border-box;
        }

        .btn-submit:hover {
            background: #1d4ed8 !important;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 1.5rem 0;
            color: #94a3b8;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }

        .divider span {
            padding: 0 0.75rem;
        }

        .btn-kiosk-action {
            width: 100%;
            padding: 0.75rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #0284c7;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            transition: all 0.15s ease-in-out;
            box-sizing: border-box;
        }

        .btn-kiosk-action:hover {
            background: #e0f2fe;
            border-color: #7dd3fc;
            color: #0369a1;
        }

        .portal-footer {
            margin-top: 1.75rem;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .alert-error {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
        }

        .alert-paired {
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            color: #065f46;
            padding: 0.85rem 1rem;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .btn-open-scanner {
            font-size: 0.8rem;
            font-weight: 600;
            background: #059669;
            color: #ffffff;
            padding: 0.4rem 0.75rem;
            border-radius: 6px;
            text-decoration: none;
            transition: background 0.15s;
            white-space: nowrap;
        }

        .btn-open-scanner:hover {
            background: #047857;
        }
    </style>
</head>
<body class="portal-body" style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%); margin: 0; padding: 1.5rem; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #1e293b; box-sizing: border-box;">

    <div class="portal-card" style="width: 100%; max-width: 420px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04); border: 1px solid #e2e8f0; padding: 2.5rem 2.25rem 2rem; box-sizing: border-box;">

        <!-- Brand Header: School Icon, Name, Subtitle -->
        <div class="brand-header" style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-logo-icon" style="width: 52px; height: 52px; background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%); color: #ffffff; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 0.85rem; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <h1 class="brand-title" style="font-size: 1.55rem; font-weight: 700; color: #0f172a; margin: 0 0 0.25rem 0; letter-spacing: -0.025em;">Edifies School</h1>
            <p class="brand-subtitle" style="font-size: 0.875rem; color: #64748b; margin: 0; font-weight: 500;">Staff Attendance Management System</p>
        </div>

        <!-- Error & Flash Alerts -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.5rem;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?= esc(session()->getFlashdata('error')) ?></span>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert-success" style="background-color: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Administrator / Manager Sign In Form -->
        <h2 style="font-size: 1.05rem; font-weight: 600; color: #1e293b; margin: 0 0 1.25rem 0; text-align: center;">Administrator Sign In</h2>

        <form action="/login" method="POST">
            <?= csrf_field() ?>

            <div style="margin-bottom: 1.15rem;">
                <label for="username" class="form-label" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.45rem;">Username</label>
                <input type="text" id="username" name="username" class="form-input" placeholder="e.g. admin or manager" required autofocus autocomplete="username" value="<?= esc(old('username')) ?>" style="width: 100%; padding: 0.75rem 0.95rem; font-size: 0.95rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; box-sizing: border-box; background: #f8fafc; color: #0f172a;">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label for="password" class="form-label" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.45rem;">Password</label>
                <input type="password" id="password" name="password" class="form-input" placeholder="••••••••" required autocomplete="current-password" style="width: 100%; padding: 0.75rem 0.95rem; font-size: 0.95rem; border: 1px solid #cbd5e1; border-radius: 8px; outline: none; box-sizing: border-box; background: #f8fafc; color: #0f172a;">
            </div>

            <button type="submit" class="btn-submit" style="width: 100%; padding: 0.85rem 1rem; font-size: 0.95rem; font-weight: 600; color: #ffffff !important; background: #1e40af !important; border: none; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 2px 6px rgba(30, 64, 175, 0.25); box-sizing: border-box;">
                <span>Staff Sign In</span>
                <span>&rarr;</span>
            </button>
        </form>

        <div class="divider" style="display: flex; align-items: center; text-align: center; margin: 1.5rem 0; color: #94a3b8; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
            <span style="flex: 1; border-bottom: 1px solid #e2e8f0;"></span>
            <span style="padding: 0 0.75rem;">or</span>
            <span style="flex: 1; border-bottom: 1px solid #e2e8f0;"></span>
        </div>

        <!-- Dedicated Scanner Pairing Gateway Link -->
        <a href="/kiosk/pair" class="btn-kiosk-action" style="width: 100%; padding: 0.75rem 1rem; font-size: 0.875rem; font-weight: 600; color: #0284c7; background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; cursor: pointer; text-decoration: none; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-sizing: border-box;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
            <span>Pair Scanner with 1-Time Code &rarr;</span>
        </a>
    </div>

    <div class="portal-footer" style="margin-top: 1.75rem; text-align: center; font-size: 0.8rem; color: #94a3b8;">
        &copy; <?= date('Y') ?> Edifies School &bull; Staff Attendance System
    </div>

</body>
</html>
