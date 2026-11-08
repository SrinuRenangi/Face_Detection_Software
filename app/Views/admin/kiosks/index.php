<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
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
        'adminFullName' => $userName ?? 'Administrator'
    ]) ?>

    <main class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
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
            <!-- Left Column: Provision New Kiosk Terminal -->
            <div class="card" style="background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.25rem;">
                <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--gray-900); margin-top: 0; margin-bottom: 1rem;">
                    Register Kiosk Terminal
                </h2>

                <form action="/admin/kiosks/create" method="POST">
                    <?= csrf_field() ?>

                    <div style="margin-bottom: 1rem;">
                        <label for="device_name" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem;">
                            Terminal Location / Name
                        </label>
                        <input type="text" id="device_name" name="device_name" required placeholder="e.g. Main Lobby Tablet A"
                               style="width: 100%; padding: 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.9rem;"
                               value="<?= esc(old('device_name')) ?>">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label for="device_type" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem;">
                            Hardware Form Factor
                        </label>
                        <select id="device_type" name="device_type" required
                                style="width: 100%; padding: 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.9rem;">
                            <option value="TABLET" <?= old('device_type') === 'TABLET' ? 'selected' : '' ?>>Tablet (Landscape Kiosk Stand)</option>
                            <option value="SMARTPHONE" <?= old('device_type') === 'SMARTPHONE' ? 'selected' : '' ?>>Smartphone (Portrait Wall Mount)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 1.25rem;">
                        <label for="terminal_mode" style="display: block; font-size: 0.85rem; font-weight: 600; color: var(--gray-700); margin-bottom: 0.25rem;">
                            Operational Terminal Mode
                        </label>
                        <select id="terminal_mode" name="terminal_mode" required
                                style="width: 100%; padding: 0.6rem; border: 1px solid var(--gray-300); border-radius: var(--radius-sm); font-size: 0.9rem;">
                            <option value="ENTRY_ONLY" <?= old('terminal_mode') === 'ENTRY_ONLY' ? 'selected' : '' ?>>ENTRY_ONLY (Dedicated Morning Arrival)</option>
                            <option value="EXIT_ONLY" <?= old('terminal_mode') === 'EXIT_ONLY' ? 'selected' : '' ?>>EXIT_ONLY (Dedicated Afternoon Departure)</option>
                        </select>
                        <p style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">
                            * DIRECTIONAL_AUTO is disabled per Stage 3.2.2 policy until optical tracking is physically validated.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-primary touch-btn" style="width: 100%; font-weight: 600;">
                        Provision Terminal & Generate Code
                    </button>
                </form>
            </div>

            <!-- Right Column: Registered Terminals Table -->
            <div class="card" style="background: white; border: 1px solid var(--gray-200); border-radius: var(--radius-md); padding: 1.25rem;">
                <h2 style="font-size: 1.1rem; font-weight: 700; color: var(--gray-900); margin-top: 0; margin-bottom: 1rem;">
                    Configured Attendance Terminals (<?= count($devices) ?>)
                </h2>

                <?php if (empty($devices)): ?>
                    <p style="color: var(--gray-500); font-size: 0.9rem;">No attendance terminals currently registered.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                            <thead>
                                <tr style="border-bottom: 2px solid var(--gray-200); text-align: left; color: var(--gray-600);">
                                    <th style="padding: 0.6rem 0.5rem;">Identifier</th>
                                    <th style="padding: 0.6rem 0.5rem;">Name & Type</th>
                                    <th style="padding: 0.6rem 0.5rem;">Mode</th>
                                    <th style="padding: 0.6rem 0.5rem;">Status</th>
                                    <th style="padding: 0.6rem 0.5rem;">Pairing State</th>
                                    <th style="padding: 0.6rem 0.5rem; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($devices as $dev): ?>
                                    <tr style="border-bottom: 1px solid var(--gray-100);">
                                        <td style="padding: 0.75rem 0.5rem; font-family: monospace; font-weight: 700; color: var(--gray-900);">
                                            <?= esc($dev['device_identifier']) ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <div style="font-weight: 600; color: var(--gray-900);"><?= esc($dev['device_name']) ?></div>
                                            <div style="font-size: 0.75rem; color: var(--gray-500);">
                                                <?= esc($dev['device_type']) ?> &bull; <?= esc($dev['hardware_tier']) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <span class="badge <?= $dev['terminal_mode'] === 'ENTRY_ONLY' ? 'badge-info' : 'badge-warning' ?>" style="font-size: 0.7rem;">
                                                <?= esc($dev['terminal_mode']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <?php if ($dev['status'] === 'ACTIVE'): ?>
                                                <span class="badge badge-success" style="font-size: 0.7rem;">ACTIVE</span>
                                            <?php elseif ($dev['status'] === 'PENDING_PAIRING'): ?>
                                                <span class="badge badge-warning" style="font-size: 0.7rem;">PENDING</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger" style="font-size: 0.7rem;">REVOKED</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem;">
                                            <?php if (isset($codesByDeviceId[$dev['id']])): ?>
                                                <div style="display: flex; align-items: center; gap: 0.4rem;">
                                                    <span style="font-size: 0.75rem; color: #0284c7; font-weight: 600;">Active Code Pending</span>
                                                </div>
                                            <?php elseif ($dev['status'] === 'ACTIVE'): ?>
                                                <span style="font-size: 0.75rem; color: var(--gray-500);">Paired &bull; Last seen: <?= esc(substr($dev['last_seen_at'] ?? 'Never', 11, 8)) ?></span>
                                            <?php else: ?>
                                                <span style="font-size: 0.75rem; color: var(--gray-400);">No active code</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                            <div style="display: inline-flex; gap: 0.35rem;">
                                                <?php if ($dev['status'] !== 'REVOKED'): ?>
                                                    <form action="/admin/kiosks/<?= esc($dev['id']) ?>/generate-code" method="POST" style="margin: 0; display: inline;">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-secondary touch-btn" style="padding: 0.35rem 0.6rem; font-size: 0.75rem;" title="Issue a fresh 15-minute pairing code">
                                                            New Code
                                                        </button>
                                                    </form>

                                                    <form action="/admin/kiosks/<?= esc($dev['id']) ?>/revoke" method="POST" style="margin: 0; display: inline;" onsubmit="return confirm('Permanently revoke this kiosk terminal?');">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn btn-secondary touch-btn" style="padding: 0.35rem 0.6rem; font-size: 0.75rem; color: var(--danger); border-color: #fca5a5;" title="Revoke this terminal">
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

