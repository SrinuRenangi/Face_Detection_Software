<?php

namespace App\Filters;

use App\Models\SystemSettingModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SystemKillSwitchFilter implements FilterInterface
{
    /**
     * Inspects global killswitch state and halts non-developer traffic when engaged
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        $settingModel = new SystemSettingModel();
        if (!$settingModel->isKillswitchEngaged()) {
            return;
        }

        $session = session();
        $userRole = $session->get('admin_role');

        // Super-Admin sessions are unconditionally exempt from the killswitch
        if ($session->get('admin_logged_in') && in_array($userRole, ['ROLE_SUPER_ADMIN', 'SUPER_ADMIN'], true)) {
            return;
        }

        $path = trim($request->getUri()->getPath(), '/');

        // Developer & Authentication routes must remain operational so Super-Admin can log in and toggle it back ON
        $exemptPaths = [
            'login',
            'logout',
            'admin/login',
            'admin/logout',
            'developer',
            'developer/control',
        ];

        if (
            in_array($path, $exemptPaths, true) ||
            str_starts_with($path, 'developer/') ||
            str_starts_with($path, 'api/developer/')
        ) {
            return;
        }

        // Static asset requests are exempt
        if (
            str_starts_with($path, 'css/') ||
            str_starts_with($path, 'js/') ||
            str_starts_with($path, 'images/') ||
            $path === 'favicon.ico'
        ) {
            return;
        }

        $message = $settingModel->getSetting(
            'killswitch_message',
            'Application access is temporarily suspended. Please contact the system provider.'
        );

        // API & AJAX requests receive standard 503 JSON payload
        if ($request->isAJAX() || str_starts_with($path, 'api/')) {
            return service('response')
                ->setStatusCode(503)
                ->setJSON([
                    'status'  => 'error',
                    'code'    => 'APPLICATION_SUSPENDED',
                    'message' => $message,
                ]);
        }

        // Standard web browser requests receive HTTP 503 Maintenance / Suspended View
        return service('response')
            ->setStatusCode(503)
            ->setBody(view('errors/html/maintenance_suspended', [
                'message' => $message,
            ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Do nothing
    }
}

