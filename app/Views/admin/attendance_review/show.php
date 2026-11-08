<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style <?= csp_style_nonce() ?>>
        .detail-card {
            background: var(--surface);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
        }
        .detail-value {
            font-size: 0.95rem;
            color: var(--text-main);
            word-break: break-all;
        }
        .mono {
            font-family: monospace;
            font-size: 0.85rem;
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
        .touch-action-btn {
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.95rem;
            font-weight: 600;
            border-radius: var(--radius-sm);
            cursor: pointer;
            border: none;
            width: 100%;
            transition: background 0.15s ease-in-out;
        }
        .btn-approve {
            background: #10b981;
            color: #ffffff;
        }
        .btn-approve:hover {
            background: #059669;
        }
        .btn-reject {
            background: #ef4444;
            color: #ffffff;
        }
        .btn-reject:hover {
            background: #dc2626;
        }
        .decision-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        @media (max-width: 768px) {
            .decision-actions {
                grid-template-columns: 1fr;
            }
        }
        pre.json-dump {
            background: #0f172a;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: var(--radius-sm);
            overflow-x: auto;
            font-size: 0.8rem;
            font-family: monospace;
            line-height: 1.4;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container nav-content">
            <a href="/admin" class="brand-link">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge">Admin</span>
            </a>
            <div class="nav-actions">
                <a href="/admin/dashboard" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <span>Dashboard</span>
                </a>
                <a href="/admin/attendance/review" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <span>&larr; Review Queue</span>
                </a>
            </div>
        </div>
    </header>

    <main class="container" style="padding: 2rem 1rem; max-width: 1000px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.5rem;">
            <div>
                <a href="/admin/attendance/review" style="color: var(--primary-color); text-decoration: none; font-size: 0.85rem; font-weight: 600;">
                    &larr; Back to Held Events Queue
                </a>
                <h1 style="font-size: 1.5rem; font-weight: 700; margin: 0.25rem 0 0 0; color: var(--text-main);">
                    Inspection: Event #<?= esc($event['id']) ?>
                </h1>
            </div>
            <div>
                <?php if (!empty($event['review_decision'])): ?>
                    <?php if ($event['review_decision'] === 'APPROVED'): ?>
                        <span class="badge-approved" style="font-size: 0.9rem; padding: 0.35rem 0.8rem;">DECISION: APPROVED</span>
                    <?php else: ?>
                        <span class="badge-rejected" style="font-size: 0.9rem; padding: 0.35rem 0.8rem;">DECISION: REJECTED</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="badge-held" style="font-size: 0.9rem; padding: 0.35rem 0.8rem;">PENDING REVIEW</span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error" style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; border-radius: var(--radius-sm);">
                <?= esc(session()->getFlashdata('error')) ?>
            </div>
        <?php endif; ?>

        <!-- Event Overview Card -->
        <div class="detail-card">
            <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                Event & Subject Information
            </h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Employee</span>
                    <span class="detail-value">
                        <strong><?= esc($event['emp_name'] ?? 'Unknown') ?></strong>
                        <span class="mono">(<?= esc($event['emp_code'] ?? 'ID #' . $event['employee_id']) ?>)</span>
                    </span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);"><?= esc($event['emp_dept'] ?? 'No Department') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Origin Device</span>
                    <span class="detail-value">
                        <strong><?= esc($event['device_name'] ?? 'Kiosk Device') ?></strong>
                    </span>
                    <span class="mono" style="font-size: 0.75rem; color: var(--text-muted);"><?= esc($event['device_identifier'] ?? 'N/A') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Punch Type & Date</span>
                    <span class="detail-value">
                        <strong style="color: <?= $event['event_type'] === 'CHECK_IN' ? '#059669' : '#d97706' ?>;">
                            <?= esc($event['event_type']) ?>
                        </strong>
                        on <?= esc($event['work_date'] ?? date('Y-m-d', strtotime($event['client_recorded_at']))) ?>
                    </span>
                    <span style="font-size: 0.8rem; color: var(--text-muted);">Sync Source: <?= esc($event['synchronization_source'] ?? 'OFFLINE_SYNC') ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Ledger State</span>
                    <span class="detail-value">
                        <span class="badge-held"><?= esc($event['processing_status']) ?></span>
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Append-Only ID: <?= esc($event['id']) ?></span>
                </div>
            </div>
        </div>

        <!-- Telemetry & Time Anchoring Audit Card -->
        <div class="detail-card">
            <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                Offline Time & Session Telemetry
            </h2>
            <div class="detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Device Claimed Time</span>
                    <span class="detail-value mono"><?= esc($event['client_recorded_at']) ?></span>
                    <span style="font-size: 0.75rem; color: #dc2626;">Untrusted Device Clock (<?= esc($event['client_timezone']) ?>)</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Authoritative Derived Time</span>
                    <span class="detail-value mono" style="font-weight: 700; color: #047857;">
                        <?= esc($event['derived_time'] ?? 'Indeterminate (Requires Review)') ?> UTC
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Local: <?= esc($event['local_punch_time'] ?? 'N/A') ?> IST</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Monotonic Elapsed Time</span>
                    <span class="detail-value mono">
                        <?= !is_null($event['anchor_elapsed_ms']) ? number_format($event['anchor_elapsed_ms']) . ' ms' : 'N/A' ?>
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                        <?= !is_null($event['anchor_elapsed_ms']) ? round($event['anchor_elapsed_ms'] / 1000, 2) . ' seconds' : 'None provided' ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Server Received Time</span>
                    <span class="detail-value mono"><?= esc($event['server_received_at']) ?></span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Server Sync Ingestion UTC</span>
                </div>
            </div>

            <div style="margin-top: 1.25rem; padding-top: 1rem; border-top: 1px dashed var(--border-color);">
                <div class="detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Server Anchor ID</span>
                        <span class="detail-value mono"><?= esc($event['server_anchor_id'] ?? 'None (Unanchored)') ?></span>
                        <?php if (!empty($event['anchor_server_utc'])): ?>
                            <span style="font-size: 0.75rem; color: var(--text-muted);">Anchor UTC: <?= esc($event['anchor_server_utc']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Session Continuity Audit</span>
                        <?php 
                            $claimedHash = !empty($event['browser_session_id']) ? hash('sha256', $event['browser_session_id']) : null;
                            $storedHash = $event['anchor_session_hash'] ?? null;
                            $sessionMatches = $claimedHash && $storedHash && hash_equals($storedHash, $claimedHash);
                        ?>
                        <span class="detail-value mono" style="font-size: 0.8rem;">
                            <?php if ($sessionMatches): ?>
                                <span style="color: #059669; font-weight: 600;">&#10003; Continuous Session Matched</span>
                            <?php else: ?>
                                <span style="color: #dc2626; font-weight: 600;">&#10007; Session Interrupted / Mismatched</span>
                            <?php endif; ?>
                        </span>
                        <span style="font-size: 0.75rem; color: var(--text-muted);">
                            Anchor Hash: <?= esc(substr($storedHash ?? 'None', 0, 16)) ?>...
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prior Decision Summary (If Decided) -->
        <?php if (!empty($event['review_decision'])): ?>
            <div class="detail-card" style="border-left: 4px solid <?= $event['review_decision'] === 'APPROVED' ? '#10b981' : '#ef4444' ?>;">
                <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main);">
                    Administrative Review Decision
                </h2>
                <div class="detail-grid" style="margin-top: 0.5rem;">
                    <div class="detail-item">
                        <span class="detail-label">Decision</span>
                        <span class="detail-value">
                            <?php if ($event['review_decision'] === 'APPROVED'): ?>
                                <strong style="color: #059669;">APPROVED</strong>
                            <?php else: ?>
                                <strong style="color: #dc2626;">REJECTED</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Reviewer</span>
                        <span class="detail-value"><?= esc($event['reviewer_username'] ?? 'Admin') ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Decided At</span>
                        <span class="detail-value mono"><?= esc($event['decided_at']) ?></span>
                    </div>
                    <?php if ($event['review_decision'] === 'APPROVED' && !empty($event['applied_record_id'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Applied Attendance Record</span>
                            <span class="detail-value mono">Record #<?= esc($event['applied_record_id']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
                <div style="margin-top: 1rem;">
                    <span class="detail-label">Substantive Reason</span>
                    <p style="margin: 0.25rem 0 0 0; font-size: 0.95rem; background: #f8fafc; padding: 0.75rem; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        <?= esc($event['review_reason']) ?>
                    </p>
                </div>
            </div>
        <?php else: ?>
            <!-- Decision Forms (Pending Review) -->
            <div class="detail-card">
                <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    Take Administrative Action
                </h2>
                <p style="font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1rem;">
                    Decisions are permanent and immutable. Approving will update the employee's daily aggregate record using the derived authoritative time. Rejecting will mark the event discarded and leave attendance aggregate untouched.
                </p>

                <div class="decision-actions">
                    <!-- Approve Form -->
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-sm); padding: 1.25rem;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: #166534; margin-top: 0;">Approve Event</h3>
                        <p style="font-size: 0.85rem; color: #15803d; margin-bottom: 1rem;">
                            Validates this punch and writes it to official daily attendance records.
                        </p>
                        <form action="/admin/attendance/review/<?= esc($event['id']) ?>/approve" method="POST" onsubmit="return confirmAction(this, 'APPROVE');">
                            <?= csrf_field() ?>
                            <div style="margin-bottom: 1rem;">
                                <label for="approve_reason" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; color: #166534;">
                                    Substantive Approval Reason (Required, min 5 chars):
                                </label>
                                <textarea id="approve_reason" name="reason" rows="3" required minlength="5" placeholder="e.g. Verified device timestamp matches staff classroom schedule." style="width: 100%; padding: 0.5rem; border: 1px solid #86efac; border-radius: var(--radius-sm); font-size: 0.85rem; box-sizing: border-box;"></textarea>
                            </div>
                            <button type="submit" class="touch-action-btn btn-approve">
                                Approve & Apply to Attendance
                            </button>
                        </form>
                    </div>

                    <!-- Reject Form -->
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-sm); padding: 1.25rem;">
                        <h3 style="font-size: 1rem; font-weight: 600; color: #991b1b; margin-top: 0;">Reject Event</h3>
                        <p style="font-size: 0.85rem; color: #b91c1c; margin-bottom: 1rem;">
                            Discards this punch. Aggregate attendance records will NOT be modified.
                        </p>
                        <form action="/admin/attendance/review/<?= esc($event['id']) ?>/reject" method="POST" onsubmit="return confirmAction(this, 'REJECT');">
                            <?= csrf_field() ?>
                            <div style="margin-bottom: 1rem;">
                                <label for="reject_reason" style="display: block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.35rem; color: #991b1b;">
                                    Substantive Rejection Reason (Required, min 5 chars):
                                </label>
                                <textarea id="reject_reason" name="reason" rows="3" required minlength="5" placeholder="e.g. Unverified offline event rejected due to browser restart during unanchored window." style="width: 100%; padding: 0.5rem; border: 1px solid #fca5a5; border-radius: var(--radius-sm); font-size: 0.85rem; box-sizing: border-box;"></textarea>
                            </div>
                            <button type="submit" class="touch-action-btn btn-reject">
                                Reject & Discard Event
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Raw Payload Dump -->
        <div class="detail-card">
            <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                Raw Ingested Event Payload
            </h2>
            <pre class="json-dump"><?= esc(json_encode($event, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
        </div>

        <!-- Audit History -->
        <div class="detail-card">
            <h2 style="font-size: 1.15rem; font-weight: 600; margin-top: 0; color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                Audit Log Trail
            </h2>
            <?php if (empty($auditLogs)): ?>
                <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">No direct audit log entries recorded for this event yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto; margin-top: 0.5rem;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border-color); color: var(--text-muted);">
                                <th style="padding: 0.5rem;">Time (UTC)</th>
                                <th style="padding: 0.5rem;">Admin</th>
                                <th style="padding: 0.5rem;">Action</th>
                                <th style="padding: 0.5rem;">Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($auditLogs as $log): ?>
                                <tr style="border-bottom: 1px solid var(--border-color);">
                                    <td class="mono" style="padding: 0.5rem;"><?= esc($log['created_at']) ?></td>
                                    <td style="padding: 0.5rem;"><?= esc($log['admin_username'] ?? 'System') ?></td>
                                    <td style="padding: 0.5rem; font-weight: 600;"><?= esc($log['action']) ?></td>
                                    <td style="padding: 0.5rem; color: var(--text-muted);"><?= esc($log['details']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script <?= csp_script_nonce() ?>>
        function confirmAction(form, actionType) {
            const reason = form.querySelector('textarea[name="reason"]').value.trim();
            if (reason.length < 5) {
                alert('Please enter a substantive reason of at least 5 characters.');
                return false;
            }
            return confirm('Are you sure you want to ' + actionType + ' this attendance event?\n\nReason: ' + reason + '\n\nThis decision cannot be undone.');
        }
    </script>
</body>
</html>

