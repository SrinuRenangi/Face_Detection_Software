<?php

namespace App\Controllers;

use App\Models\AdminAuditLogModel;
use App\Models\AdminUserModel;
use App\Models\SystemSettingModel;

class SettingsController extends BaseController
{
    protected AdminUserModel $userModel;
    protected SystemSettingModel $settingModel;
    protected AdminAuditLogModel $auditModel;

    public function __construct()
    {
        $this->userModel    = new AdminUserModel();
        $this->settingModel = new SystemSettingModel();
        $this->auditModel   = new AdminAuditLogModel();
    }

    /**
     * Renders User & Organization Settings
     */
    public function index(): string
    {
        $userId = session()->get('admin_user_id');
        $user = $this->userModel->find($userId);

        if (!$user) {
            return redirect()->to('/login')->with('error', 'Session expired. Please log in.');
        }

        $userRole = (string) session()->get('admin_role');
        $isAdmin  = in_array($userRole, ['ROLE_ADMIN', 'ADMIN', 'ROLE_SUPER_ADMIN', 'SUPER_ADMIN'], true);
        $settings = $this->settingModel->getAllAsKeyValue();

        return view('admin/settings/index', [
            'pageTitle'  => 'User Settings & Configuration',
            'user'       => $user,
            'isAdmin'    => $isAdmin,
            'userRole'   => $userRole,
            'settings'   => $settings,
            'activeNav'  => 'settings',
        ]);
    }

    /**
     * Updates personal user profile (Username, Email, Full Name)
     */
    public function updateProfile()
    {
        $userId = session()->get('admin_user_id');
        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/login')->with('error', 'Session expired.');
        }

        $username = trim((string) $this->request->getPost('username'));
        $email    = trim((string) $this->request->getPost('email'));
        $fullName = trim((string) $this->request->getPost('full_name'));

        if (empty($username) || empty($fullName)) {
            return redirect()->back()->with('error', 'Username and Full Name are required.');
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->with('error', 'Please enter a valid email address.');
        }

        // Check if username taken by another user
        $existing = $this->userModel->where('username', $username)->where('id !=', $userId)->first();
        if ($existing) {
            return redirect()->back()->with('error', "Username '{$username}' is already in use by another account.");
        }

        $this->userModel->update($userId, [
            'username'   => $username,
            'email'      => !empty($email) ? $email : null,
            'full_name'  => $fullName,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        session()->set([
            'admin_username'  => $username,
            'admin_full_name' => $fullName,
        ]);

        $this->auditModel->log(
            $username,
            'SETTINGS_PROFILE_UPDATED',
            "Profile information updated for user '{$username}'."
        );

        return redirect()->to('/admin/settings')->with('success', 'Profile details updated successfully.');
    }

    /**
     * Updates password, invalidates session, and prompts re-login
     */
    public function updatePassword()
    {
        $userId = session()->get('admin_user_id');
        $user = $this->userModel->find($userId);
        if (!$user) {
            return redirect()->to('/login')->with('error', 'Session expired.');
        }

        $currentPassword = (string) $this->request->getPost('current_password');
        $newPassword     = (string) $this->request->getPost('new_password');
        $confirmPassword = (string) $this->request->getPost('confirm_password');

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return redirect()->back()->with('error', 'Current password verification failed. Please try again.');
        }

        if (strlen($newPassword) < 8) {
            return redirect()->back()->with('error', 'New password must be at least 8 characters long.');
        }

        if (strlen($newPassword) > 72) {
            return redirect()->back()->with('error', 'Password cannot exceed 72 characters due to Bcrypt cryptographic constraints.');
        }

        if ($newPassword !== $confirmPassword) {
            return redirect()->back()->with('error', 'New password and confirmation do not match.');
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $this->userModel->update($userId, [
            'password_hash' => $hash,
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);

        $this->auditModel->log(
            $user['username'],
            'SETTINGS_PASSWORD_CHANGED',
            "Password changed successfully. Active session invalidated."
        );

        // Security: Invalidate current session and prompt immediate re-login
        session()->remove(['admin_logged_in', 'admin_user_id', 'admin_username', 'admin_role']);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session()->regenerate(true);
        }

        return redirect()->to('/login')->with('success', 'Password successfully updated! For security, please sign in with your new password.');
    }

    /**
     * Updates Organization / Tenant settings (Admin Only)
     */
    public function updateOrganization()
    {
        $userRole = (string) session()->get('admin_role');
        $isAdmin  = in_array($userRole, ['ROLE_ADMIN', 'ADMIN', 'ROLE_SUPER_ADMIN', 'SUPER_ADMIN'], true);

        if (!$isAdmin) {
            return redirect()->to('/admin/settings')->with('error', 'Unauthorized. Only Administrators can configure organization settings.');
        }

        $adminUsername = session()->get('admin_username') ?? 'admin';
        $schoolName    = trim((string) $this->request->getPost('school_name'));
        $academicYear  = trim((string) $this->request->getPost('academic_year'));
        $timezone      = trim((string) $this->request->getPost('system_timezone'));
        $cooldown      = (int) $this->request->getPost('kiosk_cooldown_seconds');

        if (empty($schoolName)) {
            return redirect()->back()->with('error', 'Institution Name is required.');
        }

        if ($cooldown < 5 || $cooldown > 600) {
            $cooldown = 60;
        }

        $this->settingModel->setSetting('school_name', $schoolName, $adminUsername, 'Institution Name');
        if (!empty($academicYear)) {
            $this->settingModel->setSetting('academic_year', $academicYear, $adminUsername, 'Operational Academic Year');
        }
        if (!empty($timezone)) {
            if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
                return redirect()->back()->with('error', "Invalid timezone '{$timezone}'. Please select an official timezone from the list (e.g. Asia/Kolkata).");
            }
            $this->settingModel->setSetting('system_timezone', $timezone, $adminUsername, 'System Timezone');
        }
        $this->settingModel->setSetting('kiosk_cooldown_seconds', (string) $cooldown, $adminUsername, 'Kiosk Punch Cooldown Seconds');

        $this->auditModel->log(
            $adminUsername,
            'SETTINGS_ORGANIZATION_UPDATED',
            "Organization settings updated by {$adminUsername}."
        );

        return redirect()->to('/admin/settings')->with('success', 'Organization and tenant settings updated successfully.');
    }
}

