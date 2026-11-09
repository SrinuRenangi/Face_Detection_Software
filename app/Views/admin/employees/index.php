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
                            <th>Gender</th>
                            <th>Department & Designation</th>
                            <th>Joining Date</th>
                            <th>Contact</th>
                            <th>Biometric Status</th>
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
                                        <?php 
                                            $g = strtoupper($emp['gender'] ?? 'MALE');
                                            $isFem = ($g === 'FEMALE');
                                            $isMale = ($g === 'MALE');
                                        ?>
                                        <span class="badge" style="<?= $isFem ? 'background:#fce7f3; color:#9d174d; border:1px solid #fbcfe8;' : ($isMale ? 'background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd;' : 'background:#f3f4f6; color:#374151;') ?>">
                                            <?= esc($g) ?>
                                        </span>
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
                                                    data-gender="<?= esc($emp['gender'] ?? 'MALE') ?>"
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

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
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

                            <div class="form-group">
                                <label class="form-label" for="add_gender">Gender <span style="color: var(--danger);">*</span></label>
                                <select name="gender" id="add_gender" class="form-select" required>
                                    <option value="MALE" selected>Male</option>
                                    <option value="FEMALE">Female</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
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

                        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 0.75rem;">
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

                            <div class="form-group">
                                <label class="form-label" for="edit_gender">Gender <span style="color: var(--danger);">*</span></label>
                                <select name="gender" id="edit_gender" class="form-select" required>
                                    <option value="MALE">Male</option>
                                    <option value="FEMALE">Female</option>
                                    <option value="OTHER">Other</option>
                                </select>
                            </div>
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
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.6rem; padding: 0.45rem 0.75rem; background: var(--gray-50); border: 1px solid var(--gray-200); border-radius: 8px;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; user-select: none; font-size: 0.85rem; font-weight: 600; color: var(--gray-800); margin: 0;">
                                <input type="checkbox" id="enroll-auto-capture-toggle" checked style="accent-color: #0284c7; width: 17px; height: 17px; cursor: pointer;">
                                <span>⚡ Smart Auto-Capture (Hands-Free)</span>
                            </label>
                            <span id="enroll-scan-badge" style="padding: 0.2rem 0.55rem; border-radius: 9999px; font-size: 0.72rem; font-weight: 700; background: #e0f2fe; color: #0369a1;">Ready</span>
                        </div>

                        <div style="position: relative; width: 100%; aspect-ratio: 4/3; background: #0b0f19; border-radius: 12px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <video id="enroll-video" autoplay playsinline muted style="width: 100%; height: 100%; object-fit: cover; transform: scaleX(-1);"></video>
                            <canvas id="enroll-canvas" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; transform: scaleX(-1); pointer-events: none;"></canvas>
                            <div id="enroll-reticle" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 55%; height: 72%; border: 2px dashed rgba(56, 189, 248, 0.7); border-radius: 50%; pointer-events: none; transition: border 0.2s, box-shadow 0.2s;"></div>

                            <!-- Auto-capture countdown overlay / progress bar -->
                            <div id="enroll-progress-wrap" style="position: absolute; bottom: 14px; left: 50%; transform: translateX(-50%); width: 75%; background: rgba(15, 23, 42, 0.88); backdrop-filter: blur(6px); padding: 8px 14px; border-radius: 20px; display: none; align-items: center; gap: 10px; z-index: 10; border: 1px solid rgba(255, 255, 255, 0.15); box-shadow: 0 4px 12px rgba(0,0,0,0.4);">
                                <div style="flex: 1; height: 7px; background: rgba(255, 255, 255, 0.2); border-radius: 4px; overflow: hidden;">
                                    <div id="enroll-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #10b981, #22c55e); transition: width 0.08s ease-out;"></div>
                                </div>
                                <span id="enroll-progress-text" style="color: #fff; font-size: 0.75rem; font-weight: 700; white-space: nowrap; min-width: 90px; text-align: right;">Holding: 0%</span>
                            </div>

                            <!-- Shutter flash animation layer -->
                            <div id="enroll-flash" style="position: absolute; inset: 0; background: #ffffff; opacity: 0; pointer-events: none; transition: opacity 0.15s ease-out; z-index: 20;"></div>
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
        let lastDetectedQualityScore = 0.95;
        let faceApiModelsLoaded = false;
        let currentEnrollMode = 'camera';
        let stableStartTime = null;
        let isAutoCapturing = false;
        const STABLE_HOLD_MS = 1200; // 1.2s continuous steady perfect scan

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
                document.getElementById('edit_gender').value = (emp.gender || 'MALE').toUpperCase();
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
            lastDetectedQualityScore = 0.95;
            isAutoCapturing = false;
            stableStartTime = null;

            const modal = document.getElementById('faceEnrollModal');
            const titleEl = document.getElementById('face-modal-title');
            const subEl = document.getElementById('face-modal-subtitle');
            const saveBtn = document.getElementById('btn-save-enrollment');

            if (!modal) return;

            resetAutoCaptureState();
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
            isAutoCapturing = false;
            stableStartTime = null;
            resetAutoCaptureState();

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
            isAutoCapturing = false;
            stableStartTime = null;
            resetAutoCaptureState();
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

        function playShutterSound() {
            try {
                const AudioCtxClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtxClass) return;
                const ctx = new AudioCtxClass();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.type = 'sine';
                osc.frequency.setValueAtTime(800, ctx.currentTime);
                osc.frequency.exponentialRampToValueAtTime(1200, ctx.currentTime + 0.08);
                gain.gain.setValueAtTime(0.3, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);
                osc.connect(gain);
                gain.connect(ctx.destination);
                osc.start();
                osc.stop(ctx.currentTime + 0.15);
            } catch (e) {
                // AudioContext restrictions gracefully handled
            }
        }

        function resetAutoCaptureState() {
            stableStartTime = null;
            const progressWrap = document.getElementById('enroll-progress-wrap');
            const progressBar = document.getElementById('enroll-progress-bar');
            if (progressWrap) progressWrap.style.display = 'none';
            if (progressBar) progressBar.style.width = '0%';
            const reticle = document.getElementById('enroll-reticle');
            if (reticle) {
                reticle.style.border = '2px dashed rgba(56, 189, 248, 0.7)';
                reticle.style.boxShadow = 'none';
            }
            const badge = document.getElementById('enroll-scan-badge');
            if (badge) {
                badge.textContent = 'Scanning';
                badge.style.background = '#e0f2fe';
                badge.style.color = '#0369a1';
            }
        }

        function evaluateFaceQuality(detection, videoWidth, videoHeight) {
            if (!detection) {
                return { isPerfect: false, score: 0, reason: 'none', message: 'Position face clearly within the oval frame...' };
            }

            const box = detection.detection.box;
            const score = detection.detection.score;
            const scorePct = Math.round(score * 100);

            // 1. Mandatory Confidence Score (System standard >= 85%)
            if (score < 0.85) {
                return {
                    isPerfect: false,
                    score: scorePct,
                    reason: 'low_score',
                    message: `Facial confidence (${scorePct}%) below mandatory 85% requirement. Face the light clearly.`
                };
            }

            // 2. Scale / Distance to camera
            const minWidth = videoWidth * 0.20;
            const maxWidth = videoWidth * 0.70;
            if (box.width < minWidth) {
                return {
                    isPerfect: false,
                    score: scorePct,
                    reason: 'too_far',
                    message: 'Move closer to the camera'
                };
            }
            if (box.width > maxWidth) {
                return {
                    isPerfect: false,
                    score: scorePct,
                    reason: 'too_close',
                    message: 'Move back slightly from the camera'
                };
            }

            // 3. Centering inside oval frame
            const faceCenterX = box.x + box.width / 2;
            const faceCenterY = box.y + box.height / 2;
            const frameCenterX = videoWidth / 2;
            const frameCenterY = videoHeight / 2;

            const offsetX = Math.abs(faceCenterX - frameCenterX) / frameCenterX;
            const offsetY = Math.abs(faceCenterY - frameCenterY) / frameCenterY;

            if (offsetX > 0.32 || offsetY > 0.35) {
                return {
                    isPerfect: false,
                    score: scorePct,
                    reason: 'off_center',
                    message: 'Center your face inside the oval guide'
                };
            }

            // 4. Head tilt / roll angle check
            if (detection.landmarks) {
                try {
                    const leftEye = detection.landmarks.getLeftEye();
                    const rightEye = detection.landmarks.getRightEye();
                    if (leftEye && rightEye && leftEye.length && rightEye.length) {
                        const lx = leftEye.reduce((s, p) => s + p.x, 0) / leftEye.length;
                        const ly = leftEye.reduce((s, p) => s + p.y, 0) / leftEye.length;
                        const rx = rightEye.reduce((s, p) => s + p.x, 0) / rightEye.length;
                        const ry = rightEye.reduce((s, p) => s + p.y, 0) / rightEye.length;
                        const angleDeg = Math.abs(Math.atan2(ry - ly, rx - lx) * (180 / Math.PI));
                        if (angleDeg > 18) {
                            return {
                                isPerfect: false,
                                score: scorePct,
                                reason: 'tilted',
                                message: 'Keep head level and looking forward'
                            };
                        }
                    }
                } catch (e) {}
            }

            return {
                isPerfect: true,
                score: scorePct,
                reason: 'perfect',
                message: 'Perfect face scan! Hold still...'
            };
        }

        function drawFaceTracking(ctx, box, landmarks, isPerfect) {
            const { x, y, width: w, height: h } = box;
            const len = Math.min(w, h) * 0.18;
            const color = isPerfect ? '#22c55e' : '#f59e0b';

            ctx.save();
            ctx.lineWidth = 3;
            ctx.strokeStyle = color;
            ctx.lineCap = 'round';
            ctx.shadowColor = isPerfect ? 'rgba(34, 197, 94, 0.6)' : 'rgba(245, 158, 11, 0.4)';
            ctx.shadowBlur = 10;

            // 4 Corner Brackets
            ctx.beginPath();
            // Top-Left
            ctx.moveTo(x, y + len); ctx.lineTo(x, y); ctx.lineTo(x + len, y);
            // Top-Right
            ctx.moveTo(x + w - len, y); ctx.lineTo(x + w, y); ctx.lineTo(x + w, y + len);
            // Bottom-Right
            ctx.moveTo(x + w, y + h - len); ctx.lineTo(x + w, y + h); ctx.lineTo(x + w - len, y + h);
            // Bottom-Left
            ctx.moveTo(x + len, y + h); ctx.lineTo(x, y + h); ctx.lineTo(x, y + h - len);
            ctx.stroke();

            // Landmarks
            if (landmarks && landmarks.positions) {
                ctx.fillStyle = isPerfect ? 'rgba(34, 197, 94, 0.8)' : 'rgba(56, 189, 248, 0.7)';
                const pts = landmarks.positions;
                for (let i = 0; i < pts.length; i += 2) {
                    ctx.beginPath();
                    ctx.arc(pts[i].x, pts[i].y, 2, 0, 2 * Math.PI);
                    ctx.fill();
                }
            }
            ctx.restore();
        }

        function startCameraDetectionLoop() {
            const video = document.getElementById('enroll-video');
            const canvas = document.getElementById('enroll-canvas');
            const saveBtn = document.getElementById('btn-save-enrollment');
            const reticle = document.getElementById('enroll-reticle');
            const progressWrap = document.getElementById('enroll-progress-wrap');
            const progressBar = document.getElementById('enroll-progress-bar');
            const progressText = document.getElementById('enroll-progress-text');
            const autoToggle = document.getElementById('enroll-auto-capture-toggle');
            const scanBadge = document.getElementById('enroll-scan-badge');
            const flashEl = document.getElementById('enroll-flash');

            async function frame() {
                if (!isCameraActive || currentEnrollMode !== 'camera') return;

                if (video.videoWidth > 0 && faceApiModelsLoaded && !isAutoCapturing) {
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
                            lastDetectedQualityScore = detection.detection.score;
                            const box = detection.detection.box;
                            const quality = evaluateFaceQuality(detection, video.videoWidth, video.videoHeight);

                            // Draw visual brackets and landmarks on mirror canvas
                            drawFaceTracking(ctx, box, detection.landmarks, quality.isPerfect);

                            if (quality.isPerfect) {
                                // High-confidence perfect face scan
                                saveBtn.disabled = false;
                                if (reticle) {
                                    reticle.style.border = '3px solid #22c55e';
                                    reticle.style.boxShadow = '0 0 25px rgba(34, 197, 94, 0.7), inset 0 0 15px rgba(34, 197, 94, 0.2)';
                                }
                                if (scanBadge) {
                                    scanBadge.textContent = `Perfect (${quality.score}%)`;
                                    scanBadge.style.background = '#dcfce7';
                                    scanBadge.style.color = '#15803d';
                                }

                                // Manage continuous stability timer
                                if (!stableStartTime) {
                                    stableStartTime = performance.now();
                                }
                                const elapsed = performance.now() - stableStartTime;
                                const progress = Math.min(100, Math.round((elapsed / STABLE_HOLD_MS) * 100));

                                if (progressWrap) progressWrap.style.display = 'flex';
                                if (progressBar) progressBar.style.width = `${progress}%`;
                                if (progressText) progressText.textContent = `Hold still: ${progress}%`;

                                updateEnrollStatus('ready', `🎯 Perfect face scan (${quality.score}%) • Hold still: ${progress}%`);

                                // Auto-capture trigger on reaching 100% stable hold
                                const isAutoEnabled = autoToggle ? autoToggle.checked : true;
                                if (progress >= 100 && isAutoEnabled && !isAutoCapturing) {
                                    isAutoCapturing = true;

                                    // Trigger visual shutter flash
                                    if (flashEl) {
                                        flashEl.style.opacity = '0.9';
                                        setTimeout(() => { flashEl.style.opacity = '0'; }, 200);
                                    }

                                    // Play audio shutter chime
                                    playShutterSound();

                                    updateEnrollStatus('ready', `✅ Perfect scan captured (${quality.score}%)! Registering template...`);
                                    if (scanBadge) {
                                        scanBadge.textContent = 'Captured!';
                                        scanBadge.style.background = '#dcfce7';
                                        scanBadge.style.color = '#15803d';
                                    }

                                    // Execute save
                                    await saveFaceEnrollment();
                                    return; // End frame loop after capture
                                }
                            } else {
                                // Face detected but not yet perfect
                                resetAutoCaptureState();
                                saveBtn.disabled = (quality.score < 85);
                                if (reticle) {
                                    reticle.style.border = '2px dashed #f59e0b';
                                    reticle.style.boxShadow = '0 0 15px rgba(245, 158, 11, 0.3)';
                                }
                                if (scanBadge) {
                                    scanBadge.textContent = `Adjusting (${quality.score}%)`;
                                    scanBadge.style.background = '#fef3c7';
                                    scanBadge.style.color = '#b45309';
                                }
                                updateEnrollStatus('looking', quality.message);
                            }
                        } else {
                            // No face detected
                            lastDetectedDescriptor = null;
                            saveBtn.disabled = true;
                            resetAutoCaptureState();
                            updateEnrollStatus('looking', 'Position face clearly within the oval frame...');
                        }
                    } catch (e) {
                        // Suppress frame loop transient errors
                    }
                }

                if (isCameraActive && !isAutoCapturing) {
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
                    lastDetectedQualityScore = detection.detection.score;
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
                        quality_score: parseFloat((lastDetectedQualityScore || 0.95).toFixed(4)),
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
                    isAutoCapturing = false;
                    if (isCameraActive) {
                        startCameraDetectionLoop();
                    }
                }
            } catch (err) {
                console.error('Enrollment submission error:', err);
                updateEnrollStatus('error', 'Network error while saving biometric template.');
                saveBtn.disabled = false;
                isAutoCapturing = false;
                if (isCameraActive) {
                    startCameraDetectionLoop();
                }
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
                    gender: d.gender,
                    department: d.dept,
                    designation: d.desig,
                    joining_date: d.joining,
                    contact_phone: d.phone,
                    status: d.status
                });
                return;
            }

            // 3. Enroll Face
            const faceBtn = e.target.closest('.btn-open-face');
            if (faceBtn) {
                e.preventDefault();
                const d = faceBtn.dataset;
                openFaceEnrollModal(d.id, d.empid, d.name);
                return;
            }

            // 4. Toggle Status
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
