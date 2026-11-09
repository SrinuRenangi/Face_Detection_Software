<?php

namespace App\Controllers;

use App\Models\AdminAuditLogModel;
use App\Models\AdminUserModel;
use App\Models\AttendanceRecordModel;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Models\SystemSettingModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class DeveloperController extends BaseController
{
    protected SystemSettingModel $settingModel;
    protected AdminUserModel $userModel;
    protected AdminAuditLogModel $auditModel;

    public function __construct()
    {
        $this->settingModel = new SystemSettingModel();
        $this->userModel    = new AdminUserModel();
        $this->auditModel   = new AdminAuditLogModel();
    }

    /**
     * Renders Developer / Super-Admin Control Panel
     */
    public function control(): string
    {
        $isKillswitchActive = $this->settingModel->isKillswitchEngaged();
        $killswitchMessage  = $this->settingModel->getSetting(
            'killswitch_message',
            'Application access is temporarily suspended. Please contact the system provider.'
        );

        $users = $this->userModel->orderBy('created_at', 'ASC')->findAll();

        $employeeModel   = new EmployeeModel();
        $attendanceModel = new AttendanceRecordModel();
        $deviceModel     = new KioskDeviceModel();

        $stats = [
            'total_users'      => count($users),
            'total_employees'  => $employeeModel->countAllResults(),
            'total_punches'    => $attendanceModel->countAllResults(),
            'active_devices'   => $deviceModel->where('status', 'ACTIVE')->countAllResults(),
            'server_time_ist'  => (new \DateTimeImmutable('now', new \DateTimeZone('Asia/Kolkata')))->format('d M Y, h:i:s A T'),
            'php_version'      => PHP_VERSION,
            'db_driver'        => config('Database')->default['DBDriver'] ?? 'MySQLi',
        ];

        $recentLogs = $this->auditModel->orderBy('created_at', 'DESC')->findAll(15);
        $settings   = $this->settingModel->getAllAsKeyValue();

        return view('developer/control', [
            'pageTitle'          => 'Developer Control Panel & System Override',
            'isKillswitchActive' => $isKillswitchActive,
            'killswitchMessage'  => $killswitchMessage,
            'users'              => $users,
            'stats'              => $stats,
            'recentLogs'         => $recentLogs,
            'settings'           => $settings,
            'currentUser'        => $this->userModel->find(session()->get('admin_user_id')),
        ]);
    }

    /**
     * Toggles Global Application Killswitch
     */
    public function toggleKillSwitch()
    {
        $adminUsername = session()->get('admin_username') ?? 'superadmin';
        $state   = trim((string) $this->request->getPost('state')); // '1' or '0'
        $message = trim((string) $this->request->getPost('message'));

        $isEngaged = in_array($state, ['1', 'true', 'on'], true) ? '1' : '0';

        if (empty($message)) {
            $message = 'Application access is temporarily suspended. Please contact the system provider.';
        }

        $this->settingModel->setSetting('app_killswitch_active', $isEngaged, $adminUsername, 'Emergency Killswitch Flag');
        $this->settingModel->setSetting('killswitch_message', $message, $adminUsername, 'Emergency Lockout Public Notice');

        $action = ($isEngaged === '1') ? 'KILLSWITCH_ACTIVATED' : 'KILLSWITCH_DEACTIVATED';
        $desc   = ($isEngaged === '1')
            ? "Emergency Killswitch ENGAGED by {$adminUsername}. Non-developer routes locked."
            : "Emergency Killswitch DISENGAGED by {$adminUsername}. Normal access restored.";

        $this->auditModel->log(
            $adminUsername,
            $action,
            $desc
        );

        $flashMsg = ($isEngaged === '1')
            ? 'Emergency Killswitch has been ACTIVATED. All non-developer routes are locked.'
            : 'Emergency Killswitch has been DEACTIVATED. Full application access restored.';

        return redirect()->to('/developer/control')->with('success', $flashMsg);
    }

    /**
     * Emergency Override: Reset password for any Admin or Manager account
     */
    public function resetUserPassword(string $userId)
    {
        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            throw PageNotFoundException::forPageNotFound('Target user account not found.');
        }

        $adminUsername = session()->get('admin_username') ?? 'superadmin';
        $newPassword   = (string) $this->request->getPost('new_password');
        $confirmPass   = (string) $this->request->getPost('confirm_password');

        if (strlen($newPassword) < 8) {
            return redirect()->back()->with('error', 'Password must be at least 8 characters long.');
        }

        if (strlen($newPassword) > 72) {
            return redirect()->back()->with('error', 'Password cannot exceed 72 characters due to Bcrypt cryptographic constraints.');
        }

        if ($newPassword !== $confirmPass) {
            return redirect()->back()->with('error', 'New password and confirmation do not match.');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->update($userId, [
            'password_hash' => $hash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->auditModel->log(
            $adminUsername,
            'DEVELOPER_PASSWORD_OVERRIDE',
            "Password for user '{$targetUser['username']}' ({$targetUser['role']}) was forcefully reset by {$adminUsername}."
        );

        return redirect()->to('/developer/control')->with('success', "Credentials for user '{$targetUser['username']}' updated successfully.");
    }

    /**
     * Emergency Override: Toggle user account active / suspended status
     */
    public function toggleUserStatus(string $userId)
    {
        $targetUser = $this->userModel->find($userId);
        if (!$targetUser) {
            throw PageNotFoundException::forPageNotFound('Target user account not found.');
        }

        // Prevent Super-Admin from suspending their own account
        if ($targetUser['id'] === session()->get('admin_user_id')) {
            return redirect()->back()->with('error', 'You cannot suspend your own active Super-Administrator session.');
        }

        $adminUsername = session()->get('admin_username') ?? 'superadmin';
        $newStatus = $targetUser['is_active'] ? 0 : 1;

        $this->userModel->update($userId, [
            'is_active'  => $newStatus,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $statusLabel = $newStatus ? 'ACTIVATED' : 'SUSPENDED';
        $this->auditModel->log(
            $adminUsername,
            'DEVELOPER_ACCOUNT_STATUS_CHANGE',
            "Account '{$targetUser['username']}' status changed to {$statusLabel} by {$adminUsername}."
        );

        return redirect()->to('/developer/control')->with('success', "Account '{$targetUser['username']}' has been {$statusLabel}.");
    }

    /**
     * Self-service credential update for the Super-Admin / Developer
     */
    public function updateSelfCredentials()
    {
        $currentUserId = session()->get('admin_user_id');
        $user = $this->userModel->find($currentUserId);
        if (!$user) {
            return redirect()->to('/login')->with('error', 'Session expired.');
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return redirect()->back()->with('error', 'Current password verification failed. Please try again.');
        }

        $username = trim((string) $this->request->getPost('username'));
        $email    = trim((string) $this->request->getPost('email'));
        $fullName = trim((string) $this->request->getPost('full_name'));
        $newPass  = (string) $this->request->getPost('new_password');

        if (empty($username) || empty($fullName)) {
            return redirect()->back()->with('error', 'Username and Full Name are required.');
        }

        // Check if username taken by another user
        $existing = $this->userModel->where('username', $username)->where('id !=', $currentUserId)->first();
        if ($existing) {
            return redirect()->back()->with('error', "Username '{$username}' is already taken by another account.");
        }

        $updateData = [
            'username'   => $username,
            'email'      => !empty($email) ? $email : null,
            'full_name'  => $fullName,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($newPass)) {
            if (strlen($newPass) < 8) {
                return redirect()->back()->with('error', 'New password must be at least 8 characters long.');
            }
            if (strlen($newPass) > 72) {
                return redirect()->back()->with('error', 'Password cannot exceed 72 characters due to Bcrypt cryptographic constraints.');
            }
            $updateData['password_hash'] = password_hash($newPass, PASSWORD_BCRYPT);
        }

        $this->userModel->update($currentUserId, $updateData);

        session()->set([
            'admin_username'  => $username,
            'admin_full_name' => $fullName,
        ]);

        $this->auditModel->log(
            $username,
            'DEVELOPER_SELF_UPDATE',
            "Super-Admin profile & credentials updated."
        );

        return redirect()->to('/developer/control')->with('success', 'Your developer credentials have been saved successfully.');
    }
}

