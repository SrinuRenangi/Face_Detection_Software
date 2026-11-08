<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css?v=<?= time() ?>">
    <style <?= csp_style_nonce() ?>>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .kiosk-grid {
            display: grid;
            grid-template-columns: 360px 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        @media (max-width: 900px) {
            .kiosk-grid {
                grid-template-columns: 1fr;
            }
        }
        .code-display {
            font-family: monospace;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.15em;
            background: #0f172a;
            color: #38bdf8;
            padding: 0.35rem 0.6rem;
            border-radius: var(--radius-sm);
            display: inline-block;
        }
        .touch-btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
    </style>
</head>
<body>
    <?= view('admin/common/navbar', [
        'activeNav'     => 'kiosks',
        'userRole'      => $userRole ?? 'ROLE_ADMIN',
        'adminFullName' => $adminFullName ?? $userName ?? 'Administrator'
    ]) ?>

    <main class="container" style="flex: 1; padding-top: 2rem; padding-bottom: 3rem;">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger" style="margin-bottom: 1rem; padding: 0.75rem 1rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--gray-900); margin: 0;">Attendance Kiosk Terminals</h1>
                <p style="font-size: 0.85rem; color: var(--gray-500); margin: 0.25rem 0 0 0;">
                    Register and manage authorized attendance phones, tablets, pairing codes, and operational terminal modes.
                </p>
            </div>
        </div>

        <div class="kiosk-grid">
            <!-- Left Column: Generate Single-Use Pairing Code -->
            <div class="card" style="background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.5rem;">
                <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.75rem;">
                    <div style="width: 38px; height: 38px; border-radius: 9px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                    </div>
                    <div>
                        <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--gray-900); margin: 0;">
                            Pair Scanner Device
                        </h2>
                        <span style="font-size: 0.75rem; color: #64748b;">Register Kiosk Terminal &bull; Permanent 1-time pairing</span>
                    </div>
                </div>

                <p style="font-size: 0.85rem; color: #475569; line-height: 1.45; margin-bottom: 1.25rem;">
                    Generate a single-use 6-digit pairing code to connect an attendance tablet or smartphone. Enter the code once on that device at <strong><a href="/kiosk/pair" target="_blank" style="color: #2563eb; text-decoration: underline;">/kiosk/pair</a></strong> to authorize it as an attendance scanner.
                </p>

                <?php if (session()->getFlashdata('active_pairing_code')): ?>
                    <?php 
                        $rawCode = session()->getFlashdata('active_pairing_code');
                        $displayCode = substr($rawCode, 0, 3) . ' ' . substr($rawCode, 3, 3);
                    ?>
                    <div style="background: #f0fdf4; border: 2px solid #86efac; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; text-align: center;">
                        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #166534; margin-bottom: 0.25rem;">
                            Active Pairing Code
                        </div>
                        <div style="font-size: 0.85rem; color: #15803d; margin-bottom: 0.75rem;">
                            <?= esc(session()->getFlashdata('active_pairing_device') ?? 'Scanner Device') ?>
                        </div>
                        <div style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 2.25rem; font-weight: 800; letter-spacing: 0.18em; color: #0f172a; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 10px; padding: 0.6rem 1rem; margin-bottom: 0.75rem; display: inline-block;">
                            <?= esc($displayCode) ?>
                        </div>
                        <div style="font-size: 0.8rem; color: #166534;">
                            Valid until <strong><?= esc(session()->getFlashdata('active_pairing_expires') ?? '15 minutes') ?></strong>
                        </div>
                        <p style="font-size: 0.75rem; color: #475569; margin-top: 0.5rem; margin-bottom: 0;">
                            Open <code style="background: #e2e8f0; padding: 0.15rem 0.35rem; border-radius: 4px;">http://localhost:8088/kiosk/pair</code> on the scanner tablet and enter this code.
                        </p>
                    </div>
                <?php endif; ?>

                <form action="/admin/kiosks/create" method="POST">
                    <?= csrf_field() ?>

                    <div style="margin-bottom: 1.25rem;">
                        <label for="device_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.35rem;">
                            Device Name / Location (Optional)
                        </label>
                        <input type="text" id="device_name" name="device_name" placeholder="e.g. Front Gate Scanner, Reception Tablet"
                               style="width: 100%; padding: 0.65rem 0.85rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.9rem;"
                               value="<?= esc(old('device_name')) ?>">
                        <span style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem; display: block;">
                            Once paired, the device remains paired permanently every day.
                        </span>
                    </div>

                    <button type="submit" class="btn btn-primary touch-btn" style="width: 100%; font-weight: 600; padding: 0.75rem 1rem; border-radius: 8px;">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" style="margin-right: 0.35rem;">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                            <line x1="8" y1="21" x2="16" y2="21"></line>
                            <line x1="12" y1="17" x2="12" y2="21"></line>
                        </svg>
                        Generate One-Time Pairing Code
                    </button>
                </form>
            </div>

            <!-- Right Column: Registered Terminals Table -->
            <div class="card" style="background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <div>
                        <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--gray-900); margin: 0;">
                            Configured Attendance Terminals (<?= count($devices) ?>)
                        </h2>
                        <span style="font-size: 0.75rem; color: #64748b;">Paired devices operate permanently without daily code generation</span>
                    </div>
                </div>

                <?php if (empty($devices)): ?>
                    <p style="color: var(--gray-500); font-size: 0.9rem;">No attendance terminals currently registered.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--gray-200); text-align: left; color: var(--gray-600);">
                                    <th style="padding: 0.6rem 0.5rem;">Device</th>
                                    <th style="padding: 0.6rem 0.5rem;">Mode</th>
                                    <th style="padding: 0.6rem 0.5rem;">Status</th>
                                    <th style="padding: 0.6rem 0.5rem;">Pairing State</th>
                                    <th style="padding: 0.6rem 0.5rem; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $dev): ?>
                                    <tr style="border-bottom: 1px solid var(--gray-100);">
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <div style="font-weight: 700; color: var(--gray-900);"><?= esc($dev['device_name']) ?></div>
                                            <div style="font-family: monospace; font-size: 0.75rem; color: #64748b;">
                                                <?= esc($dev['device_identifier']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <span class="badge badge-info" style="font-size: 0.7rem; background: #eff6ff; color: #1d4ed8; border: 1px solid #dbeafe;">
                                                Unified Scanner
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <?php if ($dev['status'] === 'ACTIVE'): ?>
                                                <span class="badge badge-success" style="font-size: 0.7rem; background: #dcfce7; color: #166534; border: 1px solid #bbf7d0;">ACTIVE &bull; PAIRED</span>
                                            <?php elseif ($dev['status'] === 'PENDING_PAIRING'): ?>
                                                <span class="badge badge-warning" style="font-size: 0.7rem; background: #fef3c7; color: #92400e; border: 1px solid #fde68a;">PENDING CODE</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger" style="font-size: 0.7rem; background: #fee2e2; color: #991b1b; border: 1px solid #fecaca;">REVOKED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <?php if (isset($codesByDeviceId[$dev['id']])): ?>
                                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                                    <span style="font-size: 0.75rem; color: #0284c7; font-weight: 700;">Code Active</span>
                                                </div>
                                            <?php elseif ($dev['status'] === 'ACTIVE'): ?>
                                                <span style="font-size: 0.75rem; color: #16a34a; font-weight: 600;">Permanently Paired</span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: var(--gray-400);">No active code</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.35rem;">
                                                <?php if ($dev['status'] !== 'REVOKED'): ?>
                                                    <form action="/admin/kiosks/<?= esc($dev['id']) ?>/generate-code" method="POST" style="margin: 0; display: inline;">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-secondary touch-btn" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; border-radius: 6px;" title="Issue a fresh 15-minute pairing code for re-pairing">
                                                            New Code
                                                        </button>
                                                    </form>

                                                    <form action="/admin/kiosks/<?= esc($dev['id']) ?>/revoke" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Revoke this scanner device? It will no longer be authorized to record attendance.');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-secondary touch-btn" style="padding: 0.35rem 0.65rem; font-size: 0.75rem; color: var(--danger); border-color: #fca5a5; border-radius: 6px;" title="Revoke this terminal">
                                                            Revoke
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span style="font-size: 0.75rem; color: var(--danger);">Revoked</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>

