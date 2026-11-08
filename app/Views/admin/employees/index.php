<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
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
    <!-- Unified Top Navigation Bar -->
    <?php
        $canManage = in_array($userRole, ['ROLE_ADMIN', 'ADMIN'], true);
    ?>
    <?= view('admin/common/navbar', [
        'activeNav'     => 'employees',
        'userRole'      => $userRole,
        'adminFullName' => $adminFullName
    ]) ?>

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

            <?php if ($canManage): ?>
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
                    <div class="stat-value" id="kpi-face-registered"><?= esc($counts['faceRegistered']) ?></div>
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
                            <?php if ($canManage): ?>
                                <th style="text-align: right;">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($employees)): ?>
                            <tr>
                                <td colspan="<?= $canManage ? 9 : 8 ?>" style="text-align: center; padding: 3rem 1.5rem; color: var(--gray-500);">
                                    <div style="font-size: 2.5rem; margin-bottom: 0.5rem; opacity: 0.6;">👥</div>
                                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--gray-800); margin-bottom: 0.25rem;">No Employees Found</div>
                                    <p style="font-size: 0.85rem; max-width: 400px; margin: 0 auto 1rem;">
                                        <?php if (!empty($search) || !empty($status) || !empty($department)): ?>
                                            No employee records matched your filter criteria. Try adjusting or clearing your filters.
                                        <?php elseif ($canManage): ?>
                                            No employee records exist in the system yet. Click "Add New Employee" to register your first staff member.
                                        <?php else: ?>
                                            No employee records exist in the system yet. Please contact an Administrator to enroll staff members.
                                        <?php endif; ?>
                                    </p>
                                    <?php if (!empty($search) || !empty($status) || !empty($department)): ?>
                                        <a href="/admin/employees" class="btn btn-secondary btn-xs">Clear Filters</a>
                                    <?php elseif ($canManage): ?>
                                        <button type="button" class="btn btn-primary btn-xs btn-open-add" onclick="openAddModal()">Add First Employee</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($employees as $emp): ?>
                                <tr id="emp-row-<?= esc($emp['id']) ?>">
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
                                    <td id="bio-cell-<?= esc($emp['id']) ?>">
                                        <?php if ($emp['face_status'] === 'REGISTERED'): ?>
                                            <span class="badge badge-bio-registered" title="Active 128-d facial embedding enrolled">
                                                &#10003; Registered
                                            </span>
                                        <?php elseif ($emp['face_status'] === 'DISABLED'): ?>
                                            <span class="badge badge-bio-disabled" title="Template enrolled but account is deactivated">
                                                &#9888; Disabled
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-bio-not-registered" title="No facial embedding enrolled">
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
                                    <?php if ($canManage): ?>
                                        <td style="text-align: right;">
                                            <div class="table-actions" style="justify-content: flex-end;">
                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-xs btn-open-edit"
                                                    title="Edit Employee"
                                                    data-id="<?= esc($emp['id']) ?>"
                                                    data-empid="<?= esc($emp['employee_id']) ?>"
                                                    data-name="<?= esc($emp['full_name']) ?>"
                                                    data-dept="<?= esc($emp['department'] ?? '') ?>"
                                                    data-desig="<?= esc($emp['designation'] ?? '') ?>"
                                                    data-joining="<?= esc($emp['joining_date'] ?? '') ?>"
                                                    data-phone="<?= esc($emp['contact_phone'] ?? '') ?>"
                                                    data-status="<?= esc($emp['status']) ?>"
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
                                                    </svg>
                                                    <span>Edit</span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-secondary btn-xs btn-open-pin"
                                                    title="<?= !empty($emp['pin_configured']) ? 'Reset Employee PIN' : 'Assign Employee PIN' ?>"
                                                    data-id="<?= esc($emp['id']) ?>"
                                                    data-empid="<?= esc($emp['employee_id']) ?>"
                                                    data-name="<?= esc($emp['full_name']) ?>"
                                                    data-configured="<?= !empty($emp['pin_configured']) ? '1' : '0' ?>"
                                                >
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                    </svg>
                                                    <span><?= !empty($emp['pin_configured']) ? 'Reset PIN' : 'Set PIN' ?></span>
                                                </button>

                                                <button
                                                    type="button"
                                                    class="btn btn-primary btn-xs btn-open-face"
                                                    style="background: #0284c7; border-color: #0284c7;"
                                                    title="Enroll Face for Automatic Scanner Recognition"
                                                    data-id="<?= esc($emp['id']) ?>"
                                                    data-empid="<?= esc($emp['employee_id']) ?>"
                                                    data-name="<?= esc($emp['full_name']) ?>"
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
                                                    class="btn btn-secondary btn-xs btn-open-status <?= ($emp['status'] === 'ACTIVE') ? 'status-toggle-deactivate' : 'status-toggle-activate' ?>"
                                                    style="<?= ($emp['status'] === 'ACTIVE') ? 'color: var(--danger); border-color: #fca5a5;' : 'color: var(--success); border-color: #86efac;' ?>"
                                                    title="<?= ($emp['status'] === 'ACTIVE') ? 'Deactivate Employee (Preserves History)' : 'Activate Employee' ?>"
                                                    data-id="<?= esc($emp['id']) ?>"
                                                    data-empid="<?= esc($emp['employee_id']) ?>"
                                                    data-name="<?= esc($emp['full_name']) ?>"
                                                    data-status="<?= esc($emp['status']) ?>"
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

    <?php if ($canManage): ?>
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

        <!-- Modal 4: Assign / Reset Employee PIN -->
        <div class="modal-overlay" id="pinModal">
            <div class="modal-dialog">
                <div class="modal-header">
                    <h3 id="pin-modal-title">Configure Employee PIN</h3>
                    <button type="button" class="modal-close-btn" onclick="closeModal('pinModal')">&times;</button>
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
                                class="form-input"
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
                                class="form-input"
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

        <!-- Modal 5: Face Biometric Enrollment Modal (ADMIN Only) -->
        <div class="modal-overlay" id="faceEnrollModal">
            <div class="modal-dialog" style="max-width: 580px;">
                <div class="modal-header">
                    <div>
                        <h3 id="face-modal-title" style="margin: 0; font-size: 1.15rem; font-weight: 700;">Enroll Staff Face Template</h3>
                        <div id="face-modal-subtitle" style="font-size: 0.8rem; color: var(--gray-500); margin-top: 0.2rem;">Staff Face Enrollment</div>
                    </div>
                    <button type="button" class="modal-close-btn" onclick="closeFaceEnrollModal()">&times;</button>
                </div>
                <div class="modal-body" style="padding: 1.25rem;">
                    <!-- Mode Navigation Tabs -->
                    <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem; border-bottom: 1px solid var(--gray-200); padding-bottom: 0.5rem; flex-wrap: wrap;">
                        <button type="button" id="tab-btn-camera" class="btn btn-primary btn-xs" onclick="switchEnrollTab('camera')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                <circle cx="12" cy="13" r="4"></circle>
                            </svg>
                            <span>Live Camera</span>
                        </button>
                        <button type="button" id="tab-btn-upload" class="btn btn-secondary btn-xs" onclick="switchEnrollTab('upload')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <span>Upload Portrait Photo</span>
                        </button>
                        <button type="button" id="tab-btn-instant" class="btn btn-secondary btn-xs" onclick="switchEnrollTab('instant')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                            </svg>
                            <span>Instant Biometric Sample</span>
                        </button>
                    </div>

                    <!-- Live Camera Feed View -->
                    <div id="enroll-camera-view" style="display: block;">
                        <div style="position: relative; width: 100%; aspect-ratio: 4/3; background: #000; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <video id="enroll-video" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                            <canvas id="enroll-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1); pointer-events: none;"></canvas>
                            <div id="enroll-reticle" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 55%; height: 70%; border: 2px dashed rgba(56, 189, 248, 0.6); border-radius: 50%; pointer-events: none;"></div>
                        </div>
                    </div>

                    <!-- Upload Photo View -->
                    <div id="enroll-upload-view" style="display: none;">
                        <div style="border: 2px dashed var(--gray-300); border-radius: 12px; padding: 1.5rem; text-align: center; background: var(--gray-50); margin-bottom: 0.75rem;">
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color: var(--gray-400); margin-bottom: 0.5rem;">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="17 8 12 3 7 8"></polyline>
                                <line x1="12" y1="3" x2="12" y2="15"></line>
                            </svg>
                            <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 0.75rem;">
                                Select a front-facing portrait photo (JPG or PNG)
                            </p>
                            <input type="file" id="enroll-file-input" accept="image/jpeg,image/png,image/webp" style="font-size: 0.85rem;" onchange="handleEnrollPhotoUpload(event)">
                        </div>
                        <div id="upload-preview-container" style="display: none; position: relative; width: 100%; max-height: 280px; background: #000; border-radius: 12px; overflow: hidden; text-align: center;">
                            <img id="upload-preview-img" style="max-height: 280px; max-width: 100%; object-fit: contain;">
                        </div>
                    </div>

                    <!-- Instant Sample View -->
                    <div id="enroll-instant-view" style="display: none; padding: 1.5rem; text-align: center; background: var(--gray-50); border-radius: 12px; border: 1px solid var(--gray-200);">
                        <div style="font-size: 2.2rem; margin-bottom: 0.5rem;">⚡</div>
                        <div style="font-weight: 700; color: var(--gray-900); font-size: 1.05rem; margin-bottom: 0.35rem;">Instant 128-d Biometric Embedding</div>
                        <p style="font-size: 0.85rem; color: var(--gray-600); margin-bottom: 1.25rem; max-width: 420px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                            Generates a normalized, valid 128-dimensional biometric embedding for rapid testing and immediate database registration.
                        </p>
                        <button type="button" class="btn btn-primary btn-sm" onclick="generateInstantBiometric()" style="background: #0284c7; border-color: #0284c7;">
                            <span>Generate 128-d Vector</span>
                        </button>
                    </div>

                    <!-- Status Indicator Pill -->
                    <div id="enroll-status-bar" style="margin-top: 1rem; padding: 0.65rem 1rem; border-radius: 8px; font-size: 0.85rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                        <span id="enroll-status-icon">⏳</span>
                        <span id="enroll-status-text">Starting face recognition system...</span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeFaceEnrollModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="btn-save-enrollment" onclick="saveFaceEnrollment()" disabled style="background: #0284c7; border-color: #0284c7;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                        <span>Capture &amp; Register Face</span>
                    </button>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Load face-api.js for client-side biometric extraction -->
    <script <?= csp_script_nonce() ?> src="/js/face-api.js"></script>

    <!-- Modal Interaction & Face Enrollment Scripts -->
    <script <?= csp_script_nonce() ?>>
        // Global variables for Face Enrollment
        let currentEnrollEmpId = null;
        let currentEnrollEmpCode = null;
        let currentEnrollName = null;
        let enrollStream = null;
        let isCameraActive = false;
        let enrollAnimFrame = null;
        let lastDetectedDescriptor = null;
        let faceApiModelsLoaded = false;
        let currentEnrollMode = 'camera';

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
                    msgEl.textContent = 'Are you sure you want to deactivate this staff member? Deactivated employees will be prevented from checking in on kiosk terminals.';
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

        // =========================================================================
        // Face Biometric Enrollment Handlers
        // =========================================================================

        async function openFaceEnrollModal(id, empCode, fullName) {
            currentEnrollEmpId = id;
            currentEnrollEmpCode = empCode;
            currentEnrollName = fullName;
            lastDetectedDescriptor = null;

            const modal = document.getElementById('faceEnrollModal');
            const titleEl = document.getElementById('face-modal-title');
            const subEl = document.getElementById('face-modal-subtitle');
            const saveBtn = document.getElementById('btn-save-enrollment');

            if (!modal) return;

            titleEl.textContent = 'Enroll Staff Face Template';
            subEl.textContent = `${fullName} (${empCode})`;
            saveBtn.disabled = true;

            modal.classList.add('active');
            switchEnrollTab('camera');

            // Load face-api models if not yet ready
            await ensureFaceModelsLoaded();

            // Start webcam feed
            startEnrollCamera();
        }

        function closeFaceEnrollModal() {
            stopEnrollCamera();
            const modal = document.getElementById('faceEnrollModal');
            if (modal) modal.classList.remove('active');

            // Reset upload file input
            const fileInput = document.getElementById('enroll-file-input');
            if (fileInput) fileInput.value = '';
            const previewContainer = document.getElementById('upload-preview-container');
            if (previewContainer) previewContainer.style.display = 'none';

            lastDetectedDescriptor = null;
        }

        function switchEnrollTab(mode) {
            currentEnrollMode = mode;
            const btnCam = document.getElementById('tab-btn-camera');
            const btnUp = document.getElementById('tab-btn-upload');
            const btnInst = document.getElementById('tab-btn-instant');
            const viewCam = document.getElementById('enroll-camera-view');
            const viewUp = document.getElementById('enroll-upload-view');
            const viewInst = document.getElementById('enroll-instant-view');
            const saveBtn = document.getElementById('btn-save-enrollment');

            saveBtn.disabled = true;
            lastDetectedDescriptor = null;

            if (mode === 'camera') {
                if (btnCam) btnCam.className = 'btn btn-primary btn-xs';
                if (btnUp) btnUp.className = 'btn btn-secondary btn-xs';
                if (btnInst) btnInst.className = 'btn btn-secondary btn-xs';
                if (viewCam) viewCam.style.display = 'block';
                if (viewUp) viewUp.style.display = 'none';
                if (viewInst) viewInst.style.display = 'none';
                startEnrollCamera();
            } else if (mode === 'upload') {
                if (btnCam) btnCam.className = 'btn btn-secondary btn-xs';
                if (btnUp) btnUp.className = 'btn btn-primary btn-xs';
                if (btnInst) btnInst.className = 'btn btn-secondary btn-xs';
                if (viewCam) viewCam.style.display = 'none';
                if (viewUp) viewUp.style.display = 'block';
                if (viewInst) viewInst.style.display = 'none';
                stopEnrollCamera();
                updateEnrollStatus('looking', 'Choose a portrait photo of staff member to extract embedding.');
            } else if (mode === 'instant') {
                if (btnCam) btnCam.className = 'btn btn-secondary btn-xs';
                if (btnUp) btnUp.className = 'btn btn-secondary btn-xs';
                if (btnInst) btnInst.className = 'btn btn-primary btn-xs';
                if (viewCam) viewCam.style.display = 'none';
                if (viewUp) viewUp.style.display = 'none';
                if (viewInst) viewInst.style.display = 'block';
                stopEnrollCamera();
                updateEnrollStatus('ready', 'Click "Generate 128-d Vector" to create synthetic embedding for testing.');
            }
        }

        function generateInstantBiometric() {
            const vec = new Float32Array(128);
            let sumSq = 0;
            for (let i = 0; i < 128; i++) {
                vec[i] = (Math.random() - 0.5) * 2;
                sumSq += vec[i] * vec[i];
            }
            const norm = Math.sqrt(sumSq) || 1;
            for (let i = 0; i < 128; i++) {
                vec[i] /= norm;
            }
            lastDetectedDescriptor = vec;
            const saveBtn = document.getElementById('btn-save-enrollment');
            if (saveBtn) saveBtn.disabled = false;
            updateEnrollStatus('ready', '✓ 128-d Biometric generated • Click "Capture & Register Face" to save.');
        }

        async function ensureFaceModelsLoaded() {
            if (faceApiModelsLoaded) return;
            updateEnrollStatus('loading', 'Loading neural network models from server...');
            try {
                await Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
                    faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
                    faceapi.nets.faceRecognitionNet.loadFromUri('/models')
                ]);
                faceApiModelsLoaded = true;
            } catch (err) {
                console.error('Face model load failure:', err);
                updateEnrollStatus('error', 'Failed to load biometric models: ' + (err.message || err));
            }
        }

        async function startEnrollCamera() {
            if (isCameraActive) return;
            const video = document.getElementById('enroll-video');
            if (!video) return;

            updateEnrollStatus('loading', 'Starting camera feed...');

            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false
                });

                video.srcObject = stream;
                enrollStream = stream;
                isCameraActive = true;

                video.onloadedmetadata = () => {
                    video.play();
                    updateEnrollStatus('looking', 'Position face clearly within the oval frame...');
                    startCameraDetectionLoop();
                };
            } catch (err) {
                console.warn('Webcam access error:', err);
                updateEnrollStatus('error', 'Camera not available: ' + (err.message || 'Permission denied. Use Photo Upload tab.'));
            }
        }

        function stopEnrollCamera() {
            isCameraActive = false;
            if (enrollAnimFrame) {
                cancelAnimationFrame(enrollAnimFrame);
                enrollAnimFrame = null;
            }
            if (enrollStream) {
                enrollStream.getTracks().forEach(t => t.stop());
                enrollStream = null;
            }
            const video = document.getElementById('enroll-video');
            if (video) video.srcObject = null;
            const canvas = document.getElementById('enroll-canvas');
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
            }
        }

        function startCameraDetectionLoop() {
            const video = document.getElementById('enroll-video');
            const canvas = document.getElementById('enroll-canvas');
            const saveBtn = document.getElementById('btn-save-enrollment');

            async function frame() {
                if (!isCameraActive || currentEnrollMode !== 'camera') return;

                if (video.videoWidth > 0 && faceApiModelsLoaded) {
                    try {
                        const detection = await faceapi
                            .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 224, scoreThreshold: 0.5 }))
                            .withFaceLandmarks()
                            .withFaceDescriptor();

                        canvas.width = video.videoWidth;
                        canvas.height = video.videoHeight;
                        const ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);

                        if (detection) {
                            lastDetectedDescriptor = detection.descriptor;
                            const box = detection.detection.box;

                            // Draw clean tracking box on mirror canvas
                            ctx.lineWidth = 3;
                            ctx.strokeStyle = '#22c55e';
                            ctx.shadowColor = 'rgba(34, 197, 94, 0.5)';
                            ctx.shadowBlur = 8;
                            ctx.strokeRect(box.x, box.y, box.width, box.height);

                            const qualityPercent = Math.round(detection.detection.score * 100);
                            updateEnrollStatus('ready', `✓ Face detected (Confidence: ${qualityPercent}%) • Ready to register`);
                            saveBtn.disabled = false;
                        } else {
                            lastDetectedDescriptor = null;
                            saveBtn.disabled = true;
                            updateEnrollStatus('looking', 'Position face clearly within the oval frame...');
                        }
                    } catch (e) {
                        // Suppress frame loop transient errors
                    }
                }

                if (isCameraActive) {
                    enrollAnimFrame = requestAnimationFrame(frame);
                }
            }

            enrollAnimFrame = requestAnimationFrame(frame);
        }

        async function handleEnrollPhotoUpload(event) {
            const file = event.target.files && event.target.files[0];
            if (!file) return;

            await ensureFaceModelsLoaded();
            updateEnrollStatus('loading', 'Analyzing portrait photo...');

            const saveBtn = document.getElementById('btn-save-enrollment');
            saveBtn.disabled = true;
            lastDetectedDescriptor = null;

            try {
                const img = await faceapi.bufferToImage(file);
                const previewImg = document.getElementById('upload-preview-img');
                const previewContainer = document.getElementById('upload-preview-container');

                previewImg.src = img.src;
                previewContainer.style.display = 'block';

                const detection = await faceapi
                    .detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.45 }))
                    .withFaceLandmarks()
                    .withFaceDescriptor();

                if (detection) {
                    lastDetectedDescriptor = detection.descriptor;
                    const qualityPercent = Math.round(detection.detection.score * 100);
                    updateEnrollStatus('ready', `✓ Face recognized in photo (${qualityPercent}%) • Ready to register`);
                    saveBtn.disabled = false;
                } else {
                    updateEnrollStatus('error', '⚠️ No clear face detected in photo. Please upload a clear, front-facing portrait.');
                }
            } catch (err) {
                console.error('Photo analysis error:', err);
                updateEnrollStatus('error', 'Error reading image: ' + err.message);
            }
        }

        async function saveFaceEnrollment() {
            if (!currentEnrollEmpId || !lastDetectedDescriptor) return;

            const saveBtn = document.getElementById('btn-save-enrollment');
            saveBtn.disabled = true;
            updateEnrollStatus('saving', 'Saving 128-dimensional biometric template to database...');

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

            try {
                const response = await fetch(`/admin/employees/${encodeURIComponent(currentEnrollEmpId)}/face-template`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        embedding: Array.from(lastDetectedDescriptor),
                        quality_score: 0.98,
                        model_name: 'face-api-resnet34'
                    })
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    updateEnrollStatus('success', `✅ Face registered successfully for ${currentEnrollName}!`);

                    // Dynamically update the row's biometric status cell
                    const bioCell = document.getElementById(`bio-cell-${currentEnrollEmpId}`);
                    if (bioCell) {
                        bioCell.innerHTML = `<span class="badge badge-bio-registered" title="Active 128-d facial embedding enrolled">&#10003; Registered</span>`;
                    }

                    // Increment the Face Registered KPI counter if it was not registered before
                    const kpiVal = document.getElementById('kpi-face-registered');
                    if (kpiVal && bioCell && !bioCell.getAttribute('data-was-registered')) {
                        const count = parseInt(kpiVal.textContent.trim(), 10) || 0;
                        kpiVal.textContent = count + 1;
                        bioCell.setAttribute('data-was-registered', 'true');
                    }

                    // Close modal automatically after 1.6 seconds
                    setTimeout(() => {
                        closeFaceEnrollModal();
                    }, 1600);
                } else {
                    updateEnrollStatus('error', '⚠️ Registration failed: ' + (result.message || 'Server rejected template'));
                    saveBtn.disabled = false;
                }
            } catch (err) {
                console.error('Enrollment submission error:', err);
                updateEnrollStatus('error', 'Network error while saving biometric template.');
                saveBtn.disabled = false;
            }
        }

        function updateEnrollStatus(type, message) {
            const bar = document.getElementById('enroll-status-bar');
            const icon = document.getElementById('enroll-status-icon');
            const text = document.getElementById('enroll-status-text');

            if (!bar || !icon || !text) return;

            text.textContent = message;

            if (type === 'loading' || type === 'saving') {
                icon.textContent = '⏳';
                bar.style.background = '#fef3c7';
                bar.style.color = '#92400e';
                bar.style.borderColor = '#fde68a';
            } else if (type === 'ready' || type === 'success') {
                icon.textContent = '✅';
                bar.style.background = '#dcfce7';
                bar.style.color = '#166534';
                bar.style.borderColor = '#bbf7d0';
            } else if (type === 'error') {
                icon.textContent = '⚠️';
                bar.style.background = '#fee2e2';
                bar.style.color = '#991b1b';
                bar.style.borderColor = '#fecaca';
            } else { // looking
                icon.textContent = '👁️';
                bar.style.background = '#f1f5f9';
                bar.style.color = '#475569';
                bar.style.borderColor = '#e2e8f0';
            }
        }

        // Close modal on Escape key press
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeFaceEnrollModal();
                document.querySelectorAll('.modal-overlay.active').forEach(m => m.classList.remove('active'));
            }
        });

        // Close modal on backdrop click
        document.querySelectorAll('.modal-overlay').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    if (this.id === 'faceEnrollModal') {
                        closeFaceEnrollModal();
                    } else {
                        this.classList.remove('active');
                    }
                }
            });
        });
        // Global delegated click listener for all action buttons
        document.addEventListener('click', function(e) {
            // 1. Add Employee
            const addBtn = e.target.closest('#btn-add-employee, .btn-open-add');
            if (addBtn) {
                e.preventDefault();
                openAddModal();
                return;
            }

            // 2. Edit Employee
            const editBtn = e.target.closest('.btn-open-edit');
            if (editBtn) {
                e.preventDefault();
                const d = editBtn.dataset;
                openEditModal({
                    id: d.id,
                    employee_id: d.empid,
                    full_name: d.name,
                    department: d.dept,
                    designation: d.desig,
                    joining_date: d.joining,
                    contact_phone: d.phone,
                    status: d.status
                });
                return;
            }

            // 3. Set / Reset PIN
            const pinBtn = e.target.closest('.btn-open-pin');
            if (pinBtn) {
                e.preventDefault();
                const d = pinBtn.dataset;
                openPinModal(d.id, d.empid, d.name, d.configured === '1');
                return;
            }

            // 4. Enroll Face
            const faceBtn = e.target.closest('.btn-open-face');
            if (faceBtn) {
                e.preventDefault();
                const d = faceBtn.dataset;
                openFaceEnrollModal(d.id, d.empid, d.name);
                return;
            }

            // 5. Toggle Status
            const statusBtn = e.target.closest('.btn-open-status');
            if (statusBtn) {
                e.preventDefault();
                const d = statusBtn.dataset;
                openStatusModal(d.id, d.empid, d.name, d.status);
                return;
            }
        });

        // Expose functions globally for maximum accessibility
        window.openAddModal = openAddModal;
        window.openEditModal = openEditModal;
        window.openPinModal = openPinModal;
        window.openFaceEnrollModal = openFaceEnrollModal;
        window.openStatusModal = openStatusModal;
        window.closeModal = closeModal;
        window.closeFaceEnrollModal = closeFaceEnrollModal;
        window.switchEnrollTab = switchEnrollTab;
        window.generateInstantBiometric = generateInstantBiometric;
        window.saveFaceEnrollment = saveFaceEnrollment;
        window.handleEnrollPhotoUpload = handleEnrollPhotoUpload;
    </script>
</body>
</html>
