<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// 1. Application Entry (Central Access Gateway & Role Dispatch)
$routes->get('/', 'Home::index');

// 2. Human Administrative Authentication (Admin & Manager)
$routes->get('login', 'Admin\AuthController::login');
$routes->post('login', 'Admin\AuthController::attemptLogin', ['filter' => 'rateLimit']);
$routes->post('logout', 'Admin\AuthController::logout');
$routes->get('logout', 'Admin\AuthController::rejectGetLogout');

// Backward-compatible Admin Login Aliases
$routes->get('admin/login', 'Admin\AuthController::login');
$routes->post('admin/login', 'Admin\AuthController::attemptLogin', ['filter' => 'rateLimit']);
$routes->post('admin/logout', 'Admin\AuthController::logout');
$routes->get('admin/logout', 'Admin\AuthController::rejectGetLogout');

// 2b. Scanner Device Pairing Gateway
$routes->get('kiosk/pair', 'KioskPairingController::pair');
$routes->post('kiosk/pair', 'KioskPairingController::attemptPair', ['filter' => 'rateLimit']);

// 3. Administrator Dashboard (Protected by AdminAuthFilter)
$routes->group('admin', ['filter' => 'adminAuth'], static function ($routes) {
    // Administrator Dashboard (Admin Only)
    $routes->get('', 'Admin\DashboardController::index', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    $routes->get('dashboard', 'Admin\DashboardController::index', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);

    // Employee Management Web Interface (Read-only for Manager, Mutations restricted to Admin)
    $routes->get('employees', 'Admin\EmployeeController::index');
    $routes->post('employees', 'Admin\EmployeeController::store', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    $routes->get('employees/(:segment)', 'Admin\EmployeeController::show/$1');
    $routes->post('employees/(:segment)/update', 'Admin\EmployeeController::update/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    $routes->post('employees/(:segment)/pin', 'Admin\EmployeeController::setPin/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    $routes->post('employees/(:segment)/toggle-status', 'Admin\EmployeeController::toggleStatus/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    $routes->post('employees/(:segment)/delete', 'Admin\EmployeeController::delete/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);

    // Stage 3: Isolated Biometric Feasibility Lab Prototype (Admin Only)
    $routes->get('face-lab', 'Admin\FaceLabController::index', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);

    // Stage 3.2.2: Kiosk Device Management (Protected: Only ADMIN, Manager prohibited)
    $routes->group('kiosks', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN'], static function ($routes) {
        $routes->get('', 'Admin\KioskDeviceController::index');
        $routes->post('create', 'Admin\KioskDeviceController::create');
        $routes->post('(:segment)/generate-code', 'Admin\KioskDeviceController::generatePairingCode/$1');
        $routes->post('(:segment)/revoke', 'Admin\KioskDeviceController::revoke/$1');
    });

    // Stage 3.2.4: Admin Attendance Review Queue (Protected: Only ADMIN, Manager prohibited)
    $routes->group('attendance', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN'], static function ($routes) {
        $routes->get('review', 'Admin\AttendanceReviewController::index');
        $routes->get('review/(:segment)', 'Admin\AttendanceReviewController::show/$1');
        $routes->post('review/(:segment)/approve', 'Admin\AttendanceReviewController::approve/$1');
        $routes->post('review/(:segment)/reject', 'Admin\AttendanceReviewController::reject/$1');

        // Milestone 3: Attendance History, Reports & Photo Inspection (Admin Only)
        $routes->get('history', 'Admin\AttendanceReportController::index');
        $routes->get('reports', 'Admin\AttendanceReportController::index');
        $routes->get('reports/daily', 'Admin\AttendanceReportController::daily');
        $routes->get('reports/monthly', 'Admin\AttendanceReportController::monthly');
        $routes->get('reports/employee', 'Admin\AttendanceReportController::employee');
        $routes->get('reports/employee/(:segment)', 'Admin\AttendanceReportController::employee/$1');
        $routes->get('photos/(:segment)', 'Admin\AttendanceReportController::viewPhoto/$1');
        $routes->get('events/(:segment)/photo', 'Admin\AttendanceReportController::viewEventPhoto/$1');
    });
});

// 4. Stage 3 Isolated Face Lab Shortcut (Protected: Only ADMIN)
$routes->get('face-lab', 'Admin\FaceLabController::index', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);

// 5. Attendance Kiosk Interface (Tablet-Optimized)
$routes->get('attendance', 'AttendanceController::index');



// 6. Backend API Routes
$routes->group('api', static function ($routes) {
    // Public Kiosk Device Pairing (Rate limited, uses one-time code)
    $routes->post('kiosk/pair', 'Api\KioskApiController::pairDevice');

    // Kiosk API (Protected by DeviceAuthFilter)
    $routes->group('kiosk', ['filter' => 'deviceAuth'], static function ($routes) {
        $routes->get('status', 'Api\KioskApiController::status');
        $routes->get('time-anchor', 'Api\KioskApiController::timeAnchor');
        $routes->post('time-anchor', 'Api\KioskApiController::timeAnchor');
        $routes->post('token/rotate', 'Api\KioskApiController::rotateToken');
        $routes->post('rotate-token', 'Api\KioskApiController::rotateToken');
        $routes->post('ping', 'Api\KioskApiController::ping');
        $routes->post('verify-pin', 'Api\KioskApiController::verifyPin');
        $routes->post('confirm-punch', 'Api\KioskApiController::confirmPunch');
        $routes->post('attendance/sync', 'Api\KioskApiController::syncAttendance');
        $routes->get('face-templates', 'Api\KioskApiController::getFaceTemplates');
        $routes->post('face-punch', 'Api\KioskApiController::facePunch');
    });

    // Admin API (Protected by AdminAuthFilter)
    $routes->group('admin', ['filter' => 'adminAuth'], static function ($routes) {
        $routes->get('me', 'Api\AdminApiController::me');
        $routes->get('stats', 'Api\AdminApiController::stats', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);

        // Employee Management API (Read-only for Manager, Mutations restricted to Admin)
        $routes->get('employees', 'Admin\EmployeeController::index');
        $routes->post('employees', 'Admin\EmployeeController::store', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
        $routes->get('employees/(:segment)', 'Admin\EmployeeController::show/$1');
        $routes->post('employees/(:segment)/update', 'Admin\EmployeeController::update/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
        $routes->post('employees/(:segment)/pin', 'Admin\EmployeeController::setPin/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
        $routes->post('employees/(:segment)/face-template', 'Admin\EmployeeController::enrollFace/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
        $routes->post('employees/(:segment)/toggle-status', 'Admin\EmployeeController::toggleStatus/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
        $routes->post('employees/(:segment)/delete', 'Admin\EmployeeController::delete/$1', ['filter' => 'adminAuth:ADMIN,ROLE_ADMIN']);
    });
});
