<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AdminUserModel;
use App\Models\AdminAuditLogModel;

class AuthController extends BaseController
{
    protected AdminUserModel $userModel;
    protected AdminAuditLogModel $auditModel;

    public function __construct()
    {
        $this->userModel  = new AdminUserModel();
        $this->auditModel = new AdminAuditLogModel();
    }

    /**
     * Renders Administrator Login View
     */
    public function login(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        if (session()->get('admin_logged_in')) {
            $role = session()->get('admin_role');
            if ($role === 'ROLE_MANAGER') {
                return redirect()->to('/admin/employees');
            }
            return redirect()->to('/admin');
        }

        return view('admin/login', [
            'pageTitle' => 'Administrator Sign In',
        ]);
    }

    /**
     * Processes Administrator Authentication
     */
    public function attemptLogin(): \CodeIgniter\HTTP\RedirectResponse
    {
        $rules = [
            'username' => 'required|min_length[3]|max_length[64]|alpha_numeric_punct',
            'password' => 'required|min_length[6]|max_length[128]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = $this->userModel->findByUsername($username);

        $cache = service('cache');
        $ip = $this->request->getIPAddress();
        $normUsername = strtolower(substr($username, 0, 64));
        $userIpHash = md5($ip . '_' . $normUsername);
        $globalIpHash = md5($ip);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Track failed attempts per user+IP and globally per IP
            $userAttempts = (int) $cache->get('login_uip_' . $userIpHash) + 1;
            $globalAttempts = (int) $cache->get('login_ip_' . $globalIpHash) + 1;

            $cache->save('login_uip_' . $userIpHash, $userAttempts, 900); // 15 mins
            $cache->save('login_ip_' . $globalIpHash, $globalAttempts, 900); // 15 mins

            if ($userAttempts >= 5) {
                $cache->save('lockout_uip_' . $userIpHash, time() + 900, 900);
            }
            if ($globalAttempts >= 15) {
                $cache->save('lockout_ip_' . $globalIpHash, time() + 900, 900);
            }

            $this->auditModel->log(
                $username ?: 'ANONYMOUS',
                'ADMIN_LOGIN_FAILED',
                "Failed login attempt from IP {$ip}. User failed attempts: {$userAttempts}, Network attempts: {$globalAttempts}."
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'Invalid username or password. Please verify your credentials.');
        }

        if ((int) $user['is_active'] !== 1) {
            $this->auditModel->log(
                $username,
                'ADMIN_LOGIN_SUSPENDED',
                "Attempted login to suspended admin account from IP {$ip}."
            );

            return redirect()->back()
                ->withInput()
                ->with('error', 'This administrative account has been suspended. Please contact the administrator.');
        }

        // Login Success: Clear rate-limit counters for this user on this network
        $cache->delete('login_uip_' . $userIpHash);
        $cache->delete('lockout_uip_' . $userIpHash);

        // Security: Regenerate Session ID to prevent session fixation attacks
        session()->regenerate(true);

        // Setup session
        $sessionData = [
            'admin_user_id'   => $user['id'],
            'admin_username'  => $user['username'],
            'admin_full_name' => $user['full_name'],
            'admin_role'      => $user['role'],
            'admin_logged_in' => true,
            'login_time'      => time(),
        ];
        session()->set($sessionData);

        // Update last login timestamp
        $this->userModel->update($user['id'], [
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);

        $this->auditModel->log(
            $user['username'],
            'ADMIN_LOGIN_SUCCESS',
            "Successful authentication as {$user['role']}."
        );

        $redirectUrl = ($user['role'] === 'ROLE_MANAGER') ? '/admin/employees' : '/admin';
        return redirect()->to($redirectUrl)->with('success', "Welcome back, {$user['full_name']}!");
    }

    /**
     * Terminates Administrator Session (Requires POST request)
     */
    public function logout(): \CodeIgniter\HTTP\RedirectResponse
    {
        $username = session()->get('admin_username') ?? 'UNKNOWN';

        $this->auditModel->log(
            $username,
            'ADMIN_LOGOUT',
            'Administrative session terminated.'
        );

        session()->destroy();

        return redirect()->to('/admin/login')->with('success', 'You have been safely signed out.');
    }

    /**
     * Rejects GET logout attempts to enforce state-changing POST and CSRF validation
     */
    public function rejectGetLogout(): \CodeIgniter\HTTP\ResponseInterface
    {
        return $this->response
            ->setStatusCode(405)
            ->setJSON([
                'status'  => 'error',
                'code'    => 'METHOD_NOT_ALLOWED',
                'message' => 'GET logout is rejected. Administrative session termination requires a POST request with a valid CSRF token.',
            ]);
    }
}

