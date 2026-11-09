<?php
$currentRole = $userRole ?? $adminRole ?? session()->get('admin_role') ?? 'ROLE_ADMIN';
$currentName = $adminFullName ?? $userName ?? session()->get('admin_full_name') ?? 'Administrator';
$isAdmin = ($currentRole === 'ROLE_ADMIN' || $currentRole === 'ADMIN');
$activeNav = $activeNav ?? '';
$initials = '';
$nameParts = explode(' ', trim($currentName));
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
}
$initials = substr($initials, 0, 2) ?: 'A';
?>
<aside class="dash-sidebar">
    <div class="sidebar-top">
        <!-- School Brand Header -->
        <a href="<?= $isAdmin ? '/admin' : '/admin/employees' ?>" class="brand-link">
            <div class="sidebar-brand-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <div>
                <div class="sidebar-brand-title">Edifies School</div>
                <span class="sidebar-brand-badge"><?= $isAdmin ? 'Admin Console' : 'Manager Console' ?></span>
            </div>
        </a>

        <!-- Vertical Navigation Links -->
        <nav class="sidebar-menu">
            <div class="sidebar-menu-heading">Menu</div>

            <?php if ($isAdmin): ?>
            <a href="/admin" class="sidebar-link <?= ($activeNav === 'dashboard') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>

            <a href="/admin/employees" class="sidebar-link <?= ($activeNav === 'employees') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Staff Directory</span>
            </a>

            <?php if ($isAdmin): ?>
            <a href="/admin/kiosks" class="sidebar-link <?= ($activeNav === 'kiosks') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                <span>Scanner Devices</span>
            </a>

            <a href="/admin/attendance/reports" class="sidebar-link <?= ($activeNav === 'reports') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Attendance &amp; Reports</span>
            </a>
            <?php endif; ?>

            <a href="/admin/settings" class="sidebar-link <?= ($activeNav === 'settings') ? 'active' : '' ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
                <span>Settings &amp; Security</span>
            </a>

            <div class="sidebar-menu-heading" style="margin-top: 0.75rem;">Terminal Scanner</div>

            <a href="/attendance" target="_blank" class="sidebar-link">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                    <circle cx="12" cy="13" r="4"></circle>
                </svg>
                <span style="color: #38bdf8;">Launch Scanner &rarr;</span>
            </a>
        </nav>
    </div>

    <!-- User Profile & Sign Out Footer -->
    <div class="sidebar-bottom">
        <div class="sidebar-user-card">
            <div class="sidebar-user-avatar"><?= esc($initials) ?></div>
            <div class="sidebar-user-details">
                <div class="sidebar-user-name" title="<?= esc($currentName) ?>"><?= esc($currentName) ?></div>
                <div class="sidebar-user-role"><?= esc($currentRole) ?></div>
            </div>
        </div>

        <form action="/admin/logout" method="POST" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-signout-btn" title="Sign Out of Session">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
</aside>

<style <?= csp_style_nonce() ?>>
    /* Admin Left Sidebar Global Responsive Styles */
    .dash-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 250px;
        height: 100vh;
        background: #0f172a;
        border-right: 1px solid #1e293b;
        color: #f8fafc;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 1.5rem 1rem 1.25rem;
        box-sizing: border-box;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.15);
        user-select: none;
        overflow-y: auto;
    }

    body {
        margin-left: 250px !important;
        background-color: #f8fafc;
    }

    .container,
    main.container,
    .dash-content {
        max-width: 1400px !important;
        margin-left: 0 !important;
        margin-right: auto !important;
        padding-left: 2rem !important;
        padding-right: 2rem !important;
        box-sizing: border-box !important;
    }

    .sidebar-top {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
    }

    .sidebar-brand,
    .brand-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        color: #ffffff;
        padding: 0 0.5rem 1.25rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-brand:hover {
        opacity: 0.95;
    }

    .sidebar-brand-icon {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: linear-gradient(135deg, #2563eb 0%, #0284c7 100%);
        color: #ffffff;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
    }

    .sidebar-brand-title {
        font-weight: 800;
        font-size: 1.1rem;
        color: #ffffff;
        letter-spacing: -0.01em;
        line-height: 1.2;
    }

    .sidebar-brand-badge {
        display: inline-block;
        font-size: 0.65rem;
        font-weight: 700;
        padding: 0.15rem 0.45rem;
        border-radius: 9999px;
        background: rgba(56, 189, 248, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(56, 189, 248, 0.3);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-top: 0.2rem;
    }

    .sidebar-menu {
        display: flex;
        flex-direction: column;
        gap: 0.35rem;
    }

    .sidebar-menu-heading {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #64748b;
        padding: 0.5rem 0.75rem 0.25rem;
    }

    .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.7rem 0.85rem;
        border-radius: 9px;
        color: #94a3b8;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        transition: all 0.15s ease-in-out;
        border: 1px solid transparent;
    }

    .sidebar-link:hover {
        background: rgba(255, 255, 255, 0.06);
        color: #ffffff;
    }

    .sidebar-link.active {
        background: #1e293b;
        color: #38bdf8;
        border-color: rgba(56, 189, 248, 0.25);
        font-weight: 700;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .sidebar-link svg {
        flex-shrink: 0;
        stroke: currentColor;
    }

    .sidebar-bottom {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding-top: 1rem;
        border-top: 1px solid rgba(255, 255, 255, 0.08);
    }

    .sidebar-user-card {
        display: flex;
        align-items: center;
        gap: 0.7rem;
        padding: 0.65rem 0.75rem;
        background: #1e293b;
        border-radius: 10px;
        border: 1px solid rgba(255, 255, 255, 0.05);
    }

    .sidebar-user-avatar {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        background: #334155;
        color: #38bdf8;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.85rem;
        flex-shrink: 0;
    }

    .sidebar-user-details {
        overflow: hidden;
        flex: 1;
        line-height: 1.25;
    }

    .sidebar-user-name {
        font-size: 0.825rem;
        font-weight: 700;
        color: #ffffff;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .sidebar-user-role {
        font-size: 0.7rem;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.04em;
    }

    .sidebar-signout-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.6rem 0.75rem;
        background: rgba(239, 68, 68, 0.1);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.25);
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease-in-out;
    }

    .sidebar-signout-btn:hover {
        background: rgba(239, 68, 68, 0.2);
        color: #ef4444;
        border-color: rgba(239, 68, 68, 0.4);
    }

    @media (max-width: 900px) {
        .dash-sidebar {
            position: static;
            width: 100%;
            height: auto;
        }
        body {
            margin-left: 0 !important;
        }
    }
</style>

<script <?= csp_script_nonce() ?>>
    // Invalidate Back-Forward Cache (bfcache) when navigating with browser arrows
    window.addEventListener('pageshow', function (event) {
        if (event.persisted || (window.performance && window.performance.navigation && window.performance.navigation.type === 2)) {
            window.location.reload();
        }
    });
</script>