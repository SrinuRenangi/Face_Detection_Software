<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style <?= csp_style_nonce() ?>>
        .review-table-container {
            overflow-x: auto;
            border-radius: var(--radius-md);
            border: 1px solid var(--border-color);
            background: var(--surface);
            margin-top: 1.5rem;
        }
        .touch-btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
            border-radius: var(--radius-sm);
            cursor: pointer;
        }
        .badge-held {
            background: #fef3c7;
            color: #92400e;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-approved {
            background: #dcfce7;
            color: #166534;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .badge-rejected {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
        }
        .mono-text {
            font-family: monospace;
            font-size: 0.85rem;
        }
        .review-card {
            display: none;
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1rem;
            margin-bottom: 1rem;
        }
        @media (max-width: 768px) {
            .review-table-container table {
                display: none;
            }
            .review-card {
                display: block;
            }
        }
    </style>
</head>
<body>
    <?= view('admin/common/navbar', [
        'activeNav'     => 'reports',
        'userRole'      => session()->get('admin_role') ?? 'ROLE_ADMIN',
        'adminFullName' => session()->get('admin_full_name') ?? 'Administrator'
    ]) ?>

    <main class="container" style="padding: 2rem 1rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0; color: var(--text-main);">Held Attendance Events Review Queue</h1>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    Inspect, approve, or reject unverified offline events, sequence anomalies, and retired epoch punches.
                </p>
            </div>
            <div>
                <span class="badge-held"><?= $pendingCount ?> Pending &bull; <?= $resolvedCount ?> Resolved</span>
            </div>
        </div>

        <!-- Filter Navigation Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-top: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
            <a href="/admin/attendance/review?filter=pending" class="btn <?= $filter === 'pending' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                <span>Pending Review (<?= $pendingCount ?>)</span>
            </a>
            <a href="/admin/attendance/review?filter=resolved" class="btn <?= $filter === 'resolved' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                <span>Resolved History (<?= $resolvedCount ?>)</span>
            </a>
            <a href="/admin/attendance/review?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 0.85rem; padding: 0.4rem 0.9rem;">
                <span>All Events (<?= $totalCount ?>)</span>
            </a>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" style="margin-top: 1rem; padding: 0.75rem 1rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error" style="margin-top: 1rem; padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <?php if (empty($events)): ?>
            <div style="background: var(--surface); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 3rem; text-align: center; margin-top: 2rem;">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" style="margin-bottom: 1rem;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <h3 style="font-size: 1.15rem; font-weight: 600; color: var(--text-main);">All Caught Up</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem;">
                    There are no held attendance events requiring administrative review at this time.
                </p>
            </div>
        <?php else: ?>
            <!-- Desktop Table View -->
            <div class="review-table-container">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.85rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 2px solid var(--border-color); color: var(--text-muted);">
                            <th style="padding: 0.75rem 1rem;">Status / Decision</th>
                            <th style="padding: 0.75rem 1rem;">Employee</th>
                            <th style="padding: 0.75rem 1rem;">Device</th>
                            <th style="padding: 0.75rem 1rem;">Work Date / Type</th>
                            <th style="padding: 0.75rem 1rem;">Claimed / Derived Time</th>
                            <th style="padding: 0.75rem 1rem;">Sequence / Epoch</th>
                            <th style="padding: 0.75rem 1rem;">Review Reason</th>
                            <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($events as $e): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 0.75rem 1rem;">
                                    <?php if (!empty($e['review_decision'])): ?>
                                        <span class="<?= $e['review_decision'] === 'APPROVED' ? 'badge-approved' : 'badge-rejected' ?>">
                                            <?= esc($e['review_decision']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-held">PENDING REVIEW</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="font-weight: 600; color: var(--text-main);"><?= esc($e['emp_name'] ?? 'Unknown') ?></div>
                                    <div style="color: var(--text-muted); font-size: 0.75rem;"><?= esc($e['emp_code'] ?? 'ID N/A') ?> (<?= esc($e['emp_dept'] ?? 'General') ?>)</div>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="font-weight: 500;"><?= esc($e['device_name'] ?? 'Generic Device') ?></div>
                                    <div class="mono-text" style="color: var(--text-muted); font-size: 0.75rem;"><?= esc($e['device_identifier'] ?? $e['device_id']) ?></div>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div><?= esc(date('d M Y', strtotime($e['client_recorded_at']))) ?></div>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: #475569;"><?= esc($e['event_type']) ?></span>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div style="color: var(--text-main); font-weight: 500;">
                                        Claimed: <?= esc($e['client_recorded_at']) ?> UTC
                                    </div>
                                    <?php if (!empty($e['server_anchor_id'])): ?>
                                        <div style="color: #0284c7; font-size: 0.75rem;">
                                            Derived: <?= esc($e['derived_time']) ?> UTC
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 0.75rem 1rem;">
                                    <div class="mono-text">Seq #<?= esc($e['device_event_sequence']) ?></div>
                                    <div class="mono-text" style="color: var(--text-muted); font-size: 0.7rem;" title="<?= esc($e['device_epoch_id']) ?>">
                                        <?= esc(substr($e['device_epoch_id'], 0, 8)) ?>...
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 1rem; max-width: 250px;">
                                    <div style="color: #92400e; font-size: 0.8rem; line-height: 1.3;">
                                        <?= esc($e['notes'] ?: 'Offline punch trust unverified.') ?>
                                    </div>
                                </td>
                                <td style="padding: 0.75rem 1rem; text-align: right;">
                                    <a href="/admin/attendance/review/<?= esc($e['id']) ?>" class="btn btn-secondary touch-btn" style="min-height: 38px; padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                        <span>Inspect & Decision &rarr;</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile Card View -->
            <div style="margin-top: 1.5rem;">
                <?php foreach ($events as $e): ?>
                    <div class="review-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                            <div>
                                <h3 style="margin: 0; font-size: 1rem; font-weight: 600; color: var(--text-main);"><?= esc($e['emp_name'] ?? 'Unknown') ?></h3>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?= esc($e['emp_code'] ?? 'ID N/A') ?></div>
                            </div>
                            <?php if (!empty($e['review_decision'])): ?>
                                <span class="<?= $e['review_decision'] === 'APPROVED' ? 'badge-approved' : 'badge-rejected' ?>">
                                    <?= esc($e['review_decision']) ?>
                                </span>
                            <?php else: ?>
                                <span class="badge-held">PENDING</span>
                            <?php endif; ?>
                        </div>

                        <div style="font-size: 0.85rem; margin: 0.5rem 0; color: var(--text-main);">
                            <div><strong>Device:</strong> <?= esc($e['device_name'] ?? 'Generic Device') ?></div>
                            <div><strong>Punch:</strong> <?= esc($e['client_recorded_at']) ?> UTC (<?= esc($e['event_type']) ?>)</div>
                            <div><strong>Sequence:</strong> #<?= esc($e['device_event_sequence']) ?> (Epoch: <?= esc(substr($e['device_epoch_id'], 0, 8)) ?>...)</div>
                            <div style="margin-top: 0.35rem; color: #92400e; font-size: 0.8rem;">
                                <strong>Reason:</strong> <?= esc($e['notes'] ?: 'Trust unverified') ?>
                            </div>
                        </div>

                        <div style="margin-top: 0.75rem; text-align: right;">
                            <a href="/admin/attendance/review/<?= esc($e['id']) ?>" class="btn btn-primary touch-btn" style="width: 100%; text-align: center;">
                                <span>Inspect &amp; Review &rarr;</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>

