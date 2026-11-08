<?php
$currentRole = $userRole ?? $adminRole ?? session()->get('admin_role') ?? 'ROLE_ADMIN';
$currentName = $adminFullName ?? $userName ?? session()->get('admin_full_name') ?? 'Administrator';
$isAdmin = ($currentRole === 'ROLE_ADMIN' || $currentRole === 'ADMIN');
$activeNav = $activeNav ?? '';
?>
<header class="dash-navbar">
    <div class="dash-nav-container">
        <a href="<?= $isAdmin ? '/admin' : '/admin/employees' ?>" class="brand-link">
            <div class="dash-brand-icon">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
            </div>
            <span>Edifies School</span>
            <span class="dash-brand-badge"><?= $isAdmin ? 'Admin' : 'Manager' ?></span>
        </a>

        <nav class="dash-nav-links">
            <?php if ($isAdmin): ?>
            <a href="/admin" class="dash-nav-item <?= ($activeNav === 'dashboard') ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                <span>Dashboard</span>
            </a>
            <?php endif; ?>

            <a href="/admin/employees" class="dash-nav-item <?= ($activeNav === 'employees') ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                <span>Staff Directory</span>
            </a>

            <?php if ($isAdmin): ?>
            <a href="/admin/kiosks" class="dash-nav-item <?= ($activeNav === 'kiosks') ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                    <line x1="8" y1="21" x2="16" y2="21"></line>
                    <line x1="12" y1="17" x2="12" y2="21"></line>
                </svg>
                <span>Scanner Devices</span>
            </a>

            <a href="/admin/attendance/reports" class="dash-nav-item <?= ($activeNav === 'reports') ? 'active' : '' ?>">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <span>Attendance &amp; Reports</span>
            </a>
            <?php endif; ?>

            <div class="dash-user-section">
                <div class="dash-user-info">
                    <div class="dash-user-name"><?= esc($currentName) ?></div>
                    <div class="dash-user-role"><?= esc($currentRole) ?></div>
                </div>

                <form action="/admin/logout" method="POST" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-signout" title="Sign Out">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </nav>
    </div>
</header>
