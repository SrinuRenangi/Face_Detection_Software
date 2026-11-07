<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($pageTitle) ?> &bull; Edifies School</title>
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .table-actions {
            display: flex;
            gap: 0.35rem;
            align-items: center;
        }
        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: var(--radius-sm);
        }
        .emp-id-tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.8rem;
            font-weight: 700;
            background-color: var(--gray-100);
            padding: 0.2rem 0.45rem;
            border-radius: var(--radius-sm);
            border: 1px solid var(--gray-300);
            color: var(--gray-800);
            display: inline-block;
        }
        .text-muted {
            color: var(--gray-500);
            font-size: 0.8rem;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <header class="navbar">
        <div class="container nav-content">
            <a href="<?= ($userRole === 'ROLE_ADMIN') ? '/admin' : '/admin/employees' ?>" class="brand-link">
                <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"></path>
                </svg>
                <span>Edifies School</span>
                <span class="brand-badge"><?= ($userRole === 'ROLE_ADMIN') ? 'Admin' : 'Manager' ?></span>
            </a>
            <div class="nav-actions">
                <?php if ($userRole === 'ROLE_ADMIN'): ?>
                <a href="/admin" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                    </svg>
                    <span>Dashboard</span>
                </a>

                <a href="/admin/attendance/reports" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span>Attendance &amp; Reports</span>
                </a>
                <?php endif; ?>

                <a href="/attendance" target="_blank" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.4rem 0.8rem;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                    <span>View Kiosk</span>
                </a>

                <div style="display: flex; align-items: center; gap: 0.5rem; padding-left: 0.5rem; border-left: 1px solid var(--gray-200);">
                    <div style="text-align: right; line-height: 1.2;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--gray-900);"><?= esc($adminFullName) ?></div>
                        <span class="badge <?= $userRole === 'ROLE_ADMIN' ? 'badge-info' : 'badge-warning' ?>" style="font-size: 0.65rem;">
                            <?= esc($userRole) ?>
                        </span>
                    </div>

                    <form action="/admin/logout" method="POST" style="margin: 0; display: inline;">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; color: var(--danger); border-color: #fca5a5; cursor: pointer; background: transparent;" title="Sign Out">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                                <polyline points="16 17 21 12 16 7"></polyline>
                                <line x1="21" y1="12" x2="9" y2="12"></line>
                            </svg>
                            <span>Sign Out</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <main class="container" style="flex: 1; padding-top: 2rem; padding-bottom: 3rem;">
        <!-- Page Header & Action Controls -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1.75rem;">
            <div>
                <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--gray-900); margin-bottom: 0.25rem;">
                    Staff Directory & Employee Management
                </h1>
                <p style="color: var(--gray-500); font-size: 0.95rem;">
                    Manage employee records, department classification, biometric status, and active directory states.
                </p>
            </div>

            <?php if ($userRole === 'ROLE_ADMIN'): ?>
                <button type="button" class="btn btn-primary" onclick="openAddModal()" id="btn-add-employee">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="12" y1="5" x2="12" y2="19"></line>
                        <line x1="5" y1="12" x2="19" y2="12"></line>
                    </svg>
                    <span>Add New Employee</span>
                </button>
            <?php else: ?>
                <span class="badge badge-warning" style="padding: 0.5rem 0.75rem; font-size: 0.8rem;">
                    Manager Access (Read-Only)
                </span>
            <?php endif; ?>
        </div>

        <!-- Success Alert -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"></polyline>
                </svg>
                <span><?= esc(session()->getFlashdata('success')) ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Alert -->
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

        <!-- Form Validation Errors -->
        <?php if (session()->getFlashdata('errors')): ?>
            <div class="alert alert-danger" style="flex-direction: column; align-items: flex-start;">
                <div style="font-weight: 700; margin-bottom: 0.25rem;">Please review the input errors below:</div>
                <ul style="margin-left: 1.25rem; font-size: 0.85rem;">
                    <?php foreach (session()->getFlashdata('errors') as $err): ?>
                        <li><?= esc($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Summary KPI Metric Cards -->
        <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); margin-bottom: 1.5rem;">
            <div class="stat-widget">
                <div class="stat-icon stat-icon-blue">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($counts['total']) ?></div>
                    <div class="stat-label">Total Staff</div>
                </div>
            </div>

            <div class="stat-widget">
                <div class="stat-icon stat-icon-green">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($counts['active']) ?></div>
                    <div class="stat-label">Active Employees</div>
                </div>
            </div>

            <div class="stat-widget">
                <div class="stat-icon" style="background-color: #fee2e2; color: var(--danger);">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($counts['inactive']) ?></div>
                    <div class="stat-label">Deactivated</div>
                </div>
            </div>

            <div class="stat-widget">
                <div class="stat-icon stat-icon-teal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?= esc($counts['faceRegistered']) ?></div>
                    <div class="stat-label">Face Registered</div>
                </div>
            </div>
        </div>

        <!-- Search & Filter Controls -->
        <form method="GET" action="/admin/employees" class="filter-bar" id="filter-form">
            <div class="search-input">
                <input
                    type="text"
                    name="search"
                    class="form-input"
                    placeholder="Search by Employee ID or Full Name..."
                    value="<?= esc($search) ?>"
                    autocomplete="off"
                >
            </div>

            <div class="filter-select">
                <select name="department" class="form-select">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= esc($dept) ?>" <?= ($department === $dept) ? 'selected' : '' ?>>
                            <?= esc($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-select">
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="ACTIVE" <?= ($status === 'ACTIVE') ? 'selected' : '' ?>>Active Only</option>
                    <option value="INACTIVE" <?= ($status === 'INACTIVE') ? 'selected' : '' ?>>Inactive Only</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-secondary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Filter</span>
                </button>
                <?php if (!empty($search) || !empty($status) || !empty($department)): ?>
                    <a href="/admin/employees" class="btn btn-secondary" title="Clear all filters">
                        <span>Reset</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Employees Table Card -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Full Name</th>
                            <th>Department & Designation</th>
                            <th>Joining Date</th>
                            <th>Contact</th>
                            <th>Biometric Status</th>
                            <th>PIN Fallback</th>
                            <th>Employment Status</th>
                            <?php if ($userRole === 'ROLE_ADMIN'): ?>
                                <th style="text-align: right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="<?= ($userRole === 'ROLE_ADMIN') ? 9 : 8 ?>" style="text-align: center; padding: 3rem 1.5rem; color: var(--gray-500);">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.6;">👥</div>
                                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--gray-800); margin-bottom: 0.25rem;">No Employees Found</div>
                                    <p style="font-size: 0.85rem; max-width: 400px; margin: 0 auto 1rem;">
                                        <?php if (!empty($search) || !empty($status) || !empty($department)): ?>
                                            No employee records matched your filter criteria. Try adjusting or clearing your filters.
                                        <?php elseif ($userRole === 'ROLE_ADMIN'): ?>
                                            No employee records exist in the system yet. Click "Add New Employee" to register your first staff member.
                                        <?php else: ?>
                                            No employee records exist in the system yet. Please contact an Administrator to enroll staff members.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($search) || !empty($status) || !empty($department)): ?>
                                        <a href="/admin/employees" class="btn btn-secondary btn-xs">Clear Filters</a>
                                    <?php elseif ($userRole === 'ROLE_ADMIN'): ?>
                                        <button type="button" class="btn btn-primary btn-xs" onclick="openAddModal()">Add First Employee</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr>
                                    <td>
                                        <span class="emp-id-tag"><?= esc($emp['employee_id']) ?></span>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: var(--gray-900);"><?= esc($emp['full_name']) ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--gray-800);"><?= esc($emp['department'] ?? 'General') ?></div>
                                        <div class="text-muted"><?= esc($emp['designation'] ?? 'Staff') ?></div>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--gray-700);">
                                            <?= !empty($emp['joining_date']) ? esc($emp['joining_date']) : '<span class="text-muted">&mdash;</span>' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="font-size: 0.85rem; color: var(--gray-700);">
                                            <?= !empty($emp['contact_phone']) ? esc($emp['contact_phone']) : '<span class="text-muted">&mdash;</span>' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($emp['face_status'] === 'REGISTERED'): ?>
                                            <span class="badge badge-bio-registered" title="Active 512-d facial embedding enrolled">
                                                &#10003; Registered
                                            </span>
                                        <?php elseif ($emp['face_status'] === 'DISABLED'): ?>
                                            <span class="badge badge-bio-disabled" title="Template enrolled but account is deactivated">
                                                &#9888; Disabled
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-bio-not-registered" title="No facial embedding enrolled (Stage 3 ready)">
                                                Not Registered
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($emp['pin_configured'])): ?>
                                            <span class="badge badge-success" title="Six-digit numeric PIN configured">&#10003; PIN Set</span>
                                        <?php else: ?>
                                            <span class="badge" style="background-color: var(--gray-200); color: var(--gray-600);" title="No PIN configured">No PIN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($emp['status'] === 'ACTIVE'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <?php if ($userRole === 'ROLE_ADMIN'): ?>
                                        <td style="text-align: right;">
                                            <div class="table-actions" style="justify-content: flex-end;">
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-xs"
                                                    title="Edit Employee"
                                                    onclick="openEditModal(<?= htmlspecialchars(json_encode($emp), ENT_QUOTES, 'UTF-8') ?>)"
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                                    </svg>
                                                    <span>Edit</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-xs"
                                                    title="<?= !empty($emp['pin_configured']) ? 'Reset Employee PIN' : 'Assign Employee PIN' ?>"
                                                    onclick="openPinModal('<?= esc($emp['id']) ?>', '<?= esc($emp['employee_id']) ?>', '<?= esc(addslashes($emp['full_name'])) ?>', <?= !empty($emp['pin_configured']) ? 'true' : 'false' ?>)"
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                    </svg>
                                                    <span><?= !empty($emp['pin_configured']) ? 'Reset PIN' : 'Set PIN' ?></span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-xs"
                                                    style="background: #0284c7; border-color: #0284c7;"
                                                    title="Enroll Face for Automatic Scanner Recognition"
                                                    onclick="openFaceEnrollModal('<?= esc($emp['id']) ?>', '<?= esc($emp['employee_id']) ?>', '<?= esc(addslashes($emp['full_name'])) ?>')"
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <circle cx="12" cy="12" r="10"></circle>
                                                        <circle cx="9" cy="9" r="1"></circle>
                                                        <circle cx="15" cy="9" r="1"></circle>
                                                        <path d="M8 15s1.5 2 4 2 4-2 4-2"></path>
                                                    </svg>
                                                    <span>Enroll Face</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-xs <?= ($emp['status'] === 'ACTIVE') ? 'status-toggle-deactivate' : 'status-toggle-activate' ?>"
                                                    style="<?= ($emp['status'] === 'ACTIVE') ? 'color: var(--danger); border-color: #fca5a5;' : 'color: var(--success); border-color: #86efac;' ?>"
                                                    title="<?= ($emp['status'] === 'ACTIVE') ? 'Deactivate Employee (Preserves History)' : 'Activate Employee' ?>"
                                                    onclick="openStatusModal('<?= esc($emp['id']) ?>', '<?= esc($emp['employee_id']) ?>', '<?= esc(addslashes($emp['full_name'])) ?>', '<?= esc($emp['status']) ?>')"
                                                >
                                                    <?php if ($emp['status'] === 'ACTIVE'): ?>
                                                        <span>Deactivate</span>
                                                    <?php else: ?>
                                                        <span>Activate</span>
                                                    <?php endif; ?>
                                                </button>
                                            </div>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination-wrapper">
                <div style="font-size: 0.85rem; color: var(--gray-600);">
                    Showing page <strong><?= $currentPage ?></strong> of <strong><?= $totalPages ?></strong> (Total <?= $totalFiltered ?> staff)
                </div>

                <div class="pagination-links">
                    <?php
                        $buildUrl = function($p) use ($search, $status, $department) {
                            $params = ['page' => $p];
                            if (!empty($search)) $params['search'] = $search;
                            if (!empty($status)) $params['status'] = $status;
                            if (!empty($department)) $params['department'] = $department;
                            return '/admin/employees?' . http_build_query($params);
                        };
                    ?>

                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $buildUrl($currentPage - 1) ?>" class="page-btn">&laquo; Prev</a>
                    <?php else: ?>
                        <span class="page-btn disabled">&laquo; Prev</span>
                    <?php endif; ?>

                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <a href="<?= $buildUrl($i) ?>" class="page-btn <?= ($i === $currentPage) ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $buildUrl($currentPage + 1) ?>" class="page-btn">Next &raquo;</a>
                    <?php else: ?>
                        <span class="page-btn disabled">Next &raquo;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php if ($userRole === 'ROLE_ADMIN'): ?>
        <!-- Modal 1: Add Employee -->
        <div class="modal-overlay" id="addModal">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Add New Employee</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('addModal')">&times;</button>
                </div>
                <form action="/admin/employees" method="POST" id="form-add-employee">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="add_employee_id">Employee ID <span style="color: var(--danger);">*</span></label>
                            <input
                                type="text"
                                name="employee_id"
                                id="add_employee_id"
                                class="form-input"
                                placeholder="e.g. EMP-101 or T-042"
                                required
                                pattern="^[A-Za-z0-9\-_]+$"
                                maxlength="32"
                                autocomplete="off"
                            >
                            <span class="text-muted" style="margin-top: 0.25rem; display: block;">Unique identifier (alphanumeric, dashes, underscores).</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="add_full_name">Full Name <span style="color: var(--danger);">*</span></label>
                            <input
                                type="text"
                                name="full_name"
                                id="add_full_name"
                                class="form-input"
                                placeholder="e.g. Eleanor Vance"
                                required
                                maxlength="128"
                                autocomplete="off"
                            >
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="add_department">Department</label>
                                <input
                                    type="text"
                                    name="department"
                                    id="add_department"
                                    class="form-input"
                                    placeholder="e.g. Science"
                                    maxlength="64"
                                >
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="add_designation">Designation</label>
                                <input
                                    type="text"
                                    name="designation"
                                    id="add_designation"
                                    class="form-input"
                                    placeholder="e.g. Senior Teacher"
                                    maxlength="64"
                                >
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="add_joining_date">Joining Date</label>
                                <input
                                    type="date"
                                    name="joining_date"
                                    id="add_joining_date"
                                    class="form-input"
                                >
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="add_contact_phone">Contact Phone</label>
                                <input
                                    type="tel"
                                    name="contact_phone"
                                    id="add_contact_phone"
                                    class="form-input"
                                    placeholder="e.g. +91 9876543210"
                                    maxlength="32"
                                >
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="add_status">Initial Employment Status</label>
                            <select name="status" id="add_status" class="form-select">
                                <option value="ACTIVE" selected>ACTIVE (Eligible for attendance)</option>
                                <option value="INACTIVE">INACTIVE (Deactivated)</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('addModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Employee</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 2: Edit Employee (Admin Only) -->
        <div class="modal-overlay" id="editModal">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3>Edit Employee Profile</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('editModal')">&times;</button>
                </div>
                <form action="" method="POST" id="form-edit-employee">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="form-label" for="edit_employee_id">Employee ID <span style="color: var(--danger);">*</span></label>
                            <input
                                type="text"
                                name="employee_id"
                                id="edit_employee_id"
                                class="form-input"
                                required
                                pattern="^[A-Za-z0-9\-_]+$"
                                maxlength="32"
                            >
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="edit_full_name">Full Name <span style="color: var(--danger);">*</span></label>
                            <input
                                type="text"
                                name="full_name"
                                id="edit_full_name"
                                class="form-input"
                                required
                                maxlength="128"
                            >
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="edit_department">Department</label>
                                <input
                                    type="text"
                                    name="department"
                                    id="edit_department"
                                    class="form-input"
                                    maxlength="64"
                                >
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="edit_designation">Designation</label>
                                <input
                                    type="text"
                                    name="designation"
                                    id="edit_designation"
                                    class="form-input"
                                    maxlength="64"
                                >
                            </div>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                            <div class="form-group">
                                <label class="form-label" for="edit_joining_date">Joining Date</label>
                                <input
                                    type="date"
                                    name="joining_date"
                                    id="edit_joining_date"
                                    class="form-input"
                                >
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="edit_contact_phone">Contact Phone</label>
                                <input
                                    type="tel"
                                    name="contact_phone"
                                    id="edit_contact_phone"
                                    class="form-input"
                                    maxlength="32"
                                >
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 0;">
                            <label class="form-label" for="edit_status">Employment Status</label>
                            <select name="status" id="edit_status" class="form-select">
                                <option value="ACTIVE">ACTIVE</option>
                                <option value="INACTIVE">INACTIVE</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal 3: Non-Destructive Status Toggle Confirmation (Admin Only) -->
        <div class="modal-overlay" id="statusModal">
            <div class="modal-dialog" style="max-width: 440px;">
                <div class="modal-header">
                    <h3 id="status-modal-title">Confirm Status Change</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('statusModal')">&times;</button>
                </div>
                <form action="" method="POST" id="form-toggle-status">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                            <div id="status-modal-icon" style="font-size: 2rem; line-height: 1;">⚠️</div>
                            <div>
                                <div style="font-weight: 700; color: var(--gray-900); margin-bottom: 0.35rem;" id="status-modal-emp-name"></div>
                                <div style="font-size: 0.85rem; color: var(--gray-600); line-height: 1.5;" id="status-modal-message"></div>
                            </div>
                        </div>

                        <div style="margin-top: 1.25rem; padding: 0.75rem 1rem; background-color: var(--gray-50); border-radius: var(--radius-sm); border: 1px solid var(--gray-200); font-size: 0.8rem; color: var(--gray-700);">
                            <strong>Safety Guarantee:</strong> All past attendance logs, shift records, and biometric associations are permanently preserved in the database.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('statusModal')">Cancel</button>
                        <button type="submit" class="btn" id="status-modal-submit-btn">Confirm</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Assign / Reset Employee PIN -->
        <div class="modal-overlay" id="pinModal">
            <div class="modal-card">
                <div class="modal-header">
                    <h3 class="modal-title" id="pin-modal-title">Configure Employee PIN</h3>
                    <button type="button" class="modal-close" onclick="closeModal('pinModal')">&times;</button>
                </div>
                <form action="" method="POST" id="form-set-pin">
                    <?= csrf_field() ?>
                    <div class="modal-body">
                        <div style="background-color: var(--gray-50); border: 1px solid var(--gray-200); border-radius: var(--radius-sm); padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
                            <div style="font-size: 0.8rem; color: var(--gray-500); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Staff Member</div>
                            <div style="font-size: 1rem; font-weight: 700; color: var(--gray-900);" id="pin-modal-emp-name">&mdash;</div>
                            <div style="font-size: 0.8rem; color: var(--gray-600); margin-top: 0.2rem;" id="pin-modal-current-status">No PIN currently configured</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="employee_pin">Six-Digit Numeric PIN <span style="color: var(--danger);">*</span></label>
                            <input
                                type="password"
                                name="pin"
                                id="employee_pin"
                                class="form-control"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                placeholder="e.g. 123456"
                                required
                                autocomplete="new-password"
                                style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 0.25em; font-size: 1.1rem;"
                            >
                            <div style="font-size: 0.75rem; color: var(--gray-500); margin-top: 0.25rem;">Must be exactly 6 digits (0–9). Encrypted with bcrypt.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="employee_pin_confirm">Confirm Six-Digit PIN <span style="color: var(--danger);">*</span></label>
                            <input
                                type="password"
                                name="pin_confirm"
                                id="employee_pin_confirm"
                                class="form-control"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                placeholder="Re-enter 6-digit PIN"
                                required
                                autocomplete="new-password"
                                style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; letter-spacing: 0.25em; font-size: 1.1rem;"
                            >
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('pinModal')">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="pin-modal-submit-btn">Save PIN</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Modal Interaction Scripts -->
    <script>
        function openAddModal() {
            const modal = document.getElementById('addModal');
            if (modal) {
                modal.classList.add('active');
                setTimeout(() => document.getElementById('add_employee_id')?.focus(), 50);
            }
        }

        function openEditModal(emp) {
            const modal = document.getElementById('editModal');
            const form = document.getElementById('form-edit-employee');
            if (modal && form) {
                form.action = '/admin/employees/' + encodeURIComponent(emp.id) + '/update';
                document.getElementById('edit_employee_id').value = emp.employee_id || '';
                document.getElementById('edit_full_name').value = emp.full_name || '';
                document.getElementById('edit_department').value = emp.department || '';
                document.getElementById('edit_designation').value = emp.designation || '';
                document.getElementById('edit_joining_date').value = emp.joining_date || '';
                document.getElementById('edit_contact_phone').value = emp.contact_phone || '';
                document.getElementById('edit_status').value = emp.status || 'ACTIVE';
                modal.classList.add('active');
                setTimeout(() => document.getElementById('edit_full_name')?.focus(), 50);
            }
        }

        function openStatusModal(id, empId, name, currentStatus) {
            const modal = document.getElementById('statusModal');
            const form = document.getElementById('form-toggle-status');
            const titleEl = document.getElementById('status-modal-title');
            const nameEl = document.getElementById('status-modal-emp-name');
            const msgEl = document.getElementById('status-modal-message');
            const btnEl = document.getElementById('status-modal-submit-btn');
            const iconEl = document.getElementById('status-modal-icon');

            if (modal && form) {
                form.action = '/admin/employees/' + encodeURIComponent(id) + '/toggle-status';
                nameEl.textContent = name + ' (' + empId + ')';

                if (currentStatus === 'ACTIVE') {
                    titleEl.textContent = 'Deactivate Employee';
                    iconEl.textContent = '⏸️';
                    msgEl.textContent = 'Are you sure you want to deactivate this staff member? Deactivated employees will be prevented from checking in on kiosk tablets.';
                    btnEl.className = 'btn btn-danger';
                    btnEl.textContent = 'Deactivate Employee';
                } else {
                    titleEl.textContent = 'Activate Employee';
                    iconEl.textContent = '▶️';
                    msgEl.textContent = 'Are you sure you want to reactivate this staff member? The employee will regain ability to authenticate on kiosks.';
                    btnEl.className = 'btn btn-primary';
                    btnEl.textContent = 'Activate Employee';
                }

                modal.classList.add('active');
            }
        }

        function openPinModal(id, empId, name, isConfigured) {
            const modal = document.getElementById('pinModal');
            const form = document.getElementById('form-set-pin');
            const titleEl = document.getElementById('pin-modal-title');
            const nameEl = document.getElementById('pin-modal-emp-name');
            const statusEl = document.getElementById('pin-modal-current-status');
            const btnEl = document.getElementById('pin-modal-submit-btn');

            if (modal && form) {
                form.action = '/admin/employees/' + encodeURIComponent(id) + '/pin';
                nameEl.textContent = name + ' (' + empId + ')';
                document.getElementById('employee_pin').value = '';
                document.getElementById('employee_pin_confirm').value = '';

                if (isConfigured) {
                    titleEl.textContent = 'Reset Employee PIN';
                    statusEl.textContent = 'Active 6-digit PIN configured. Entering a new PIN will overwrite it.';
                    btnEl.textContent = 'Update & Reset PIN';
                } else {
                    titleEl.textContent = 'Assign Employee PIN';
                    statusEl.textContent = 'No PIN currently configured. Staff member cannot use PIN fallback.';
                    btnEl.textContent = 'Assign PIN';
                }

                modal.classList.add('active');
                setTimeout(() => document.getElementById('employee_pin')?.focus(), 50);
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Close modal on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>
