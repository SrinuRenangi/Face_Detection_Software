<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner Device Pairing &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body.pair-body {
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

        .pair-card {
            width: 100%;
            max-width: 440px;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            border: 1px solid #e2e8f0;
            padding: 2.5rem 2.25rem 2.25rem;
            box-sizing: border-box;
            text-align: center;
            animation: fadeIn 0.25s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pair-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%);
            color: #ffffff;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);
        }

        .pair-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 0.35rem 0;
            letter-spacing: -0.02em;
        }

        .pair-subtitle {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0 0 1.75rem 0;
            line-height: 1.4;
        }

        .pairing-input {
            width: 100%;
            padding: 0.85rem 1rem;
            font-size: 1.05rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            text-align: center;
            border: 2px solid #cbd5e1;
            border-radius: 10px;
            outline: none;
            transition: all 0.15s ease-in-out;
            box-sizing: border-box;
            background: #f8fafc;
            color: #0f172a;
        }

        .pairing-input:focus {
            background: #ffffff;
            border-color: #0284c7;
            box-shadow: 0 0 0 3px rgba(2, 132, 199, 0.15);
        }

        .btn-pair {
            width: 100%;
            padding: 0.9rem 1.25rem;
            font-size: 0.95rem;
            font-weight: 600;
            color: #ffffff !important;
            background: #0284c7 !important;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s ease-in-out, transform 0.05s ease;
            box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-sizing: border-box;
        }

        .btn-pair:hover {
            background: #0369a1 !important;
        }

        .btn-pair:active {
            transform: scale(0.99);
        }

        .link-back {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: #64748b;
            text-decoration: none;
            margin-top: 1.5rem;
            font-weight: 500;
            transition: color 0.15s;
        }

        .link-back:hover {
            color: #0f172a;
        }
    </style>
</head>
<body class="pair-body" style="min-height: 100vh; display: flex; flex-direction: column; justify-content: center; align-items: center; background: linear-gradient(135deg, #f8fafc 0%, #edf2f7 100%); margin: 0; padding: 1.5rem; font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; color: #1e293b; box-sizing: border-box;">

    <div class="pair-card" style="width: 100%; max-width: 440px; background: #ffffff; border-radius: 16px; box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.08), 0 8px 10px -6px rgba(15, 23, 42, 0.04); border: 1px solid #e2e8f0; padding: 2.5rem 2.25rem 2.25rem; box-sizing: border-box; text-align: center;">

        <!-- Brand / Terminal Icon -->
        <div class="pair-icon" style="width: 56px; height: 56px; background: linear-gradient(135deg, #0284c7 0%, #0ea5e9 100%); color: #ffffff; border-radius: 14px; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem; box-shadow: 0 4px 12px rgba(2, 132, 199, 0.25);">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                <line x1="8" y1="21" x2="16" y2="21"></line>
                <line x1="12" y1="17" x2="12" y2="21"></line>
            </svg>
        </div>

        <h1 class="pair-title" style="font-size: 1.5rem; font-weight: 700; color: #0f172a; margin: 0 0 0.35rem 0; letter-spacing: -0.02em;">Scanner Device Pairing</h1>
        <p class="pair-subtitle" style="font-size: 0.875rem; color: #64748b; margin: 0 0 1.75rem 0; line-height: 1.4;">Enter the 6-digit pairing code from the School Administrator</p>

        <!-- Active Paired Device Status -->
        <?php if ($isPaired && $pairedDevice): ?>
            <div class="alert-paired" style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; padding: 0.85rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.5rem; text-align: left; display: flex; align-items: center; justify-content: space-between; gap: 0.5rem;">
                <div>
                    <strong>Device Already Paired</strong>
                    <div style="font-size: 0.75rem; color: #047857;"><?= esc($pairedDevice['device_name'] ?? 'Kiosk Terminal') ?></div>
                </div>
                <a href="/attendance" class="btn-open-scanner" style="font-size: 0.8rem; font-weight: 600; background: #059669; color: #ffffff; padding: 0.4rem 0.75rem; border-radius: 6px; text-decoration: none; white-space: nowrap;">Open Scanner &rarr;</a>
            </div>
        <?php endif; ?>

        <!-- Error Banner -->
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert-error" style="background-color: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.85rem; margin-bottom: 1.25rem; text-align: left; display: flex; align-items: center; gap: 0.5rem;">
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
                <label for="pairing_code" class="form-label" style="display: block; font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Single-Use Pairing Code (6-Digit)</label>
                <input
                    type="text"
                    id="pairing_code"
                    name="pairing_code"
                    class="pairing-input"
                    value="<?= esc(old('pairing_code')) ?>"
                    placeholder="e.g. 748 291"
                    maxlength="40"
                    required
                    autofocus
                    autocomplete="off"
                    autocorrect="off"
                    autocapitalize="characters"
                    spellcheck="false"
                    style="width: 100%; padding: 0.85rem 1rem; font-size: 1.25rem; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; text-transform: uppercase; letter-spacing: 0.15em; text-align: center; border: 2px solid #cbd5e1; border-radius: 10px; outline: none; box-sizing: border-box; background: #f8fafc; color: #0f172a;"
                >
            </div>

            <button type="submit" class="btn-pair" style="width: 100%; padding: 0.9rem 1.25rem; font-size: 0.95rem; font-weight: 600; color: #ffffff !important; background: #0284c7 !important; border: none; border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 2px 6px rgba(2, 132, 199, 0.25); box-sizing: border-box;">
                <span>Authorize &amp; Pair Scanner</span>
                <span>&rarr;</span>
            </button>
        </form>

        <a href="/" class="link-back" style="display: inline-flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; color: #64748b; text-decoration: none; margin-top: 1.5rem; font-weight: 500;">
            <span>&larr; Return to Sign In</span>
        </a>
    </div>

    <div class="portal-footer" style="margin-top: 2rem; text-align: center; font-size: 0.8rem; color: #94a3b8;">
        &copy; <?= date('Y') ?> Edifies School &bull; Dedicated Attendance Scanner Terminal
    </div>

</body>
</html>
