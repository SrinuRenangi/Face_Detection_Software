<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AdminAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = session();

        $path = ltrim($request->getUri()->getPath(), '/');
        if (!$session->get('admin_logged_in') || !$session->get('admin_user_id')) {
            if ($request->isAJAX() || str_starts_with($path, 'api/admin')) {
                return service('response')
                    ->setStatusCode(401)
                    ->setJSON([
                        'status'  => 'error',
                        'code'    => 'UNAUTHORIZED',
                        'message' => 'Administrator session expired or unauthorized. Please log in.',
                    ]);
            }

            return redirect()->to('/admin/login')->with('error', 'Please log in to access the administration panel.');
        }

        $userRole = (string) $session->get('admin_role');

        // SCANNER role is strictly restricted to attendance kiosk operations (/scanner and /attendance)
        // It must NEVER access the human administrative backoffice (/admin/*, /manager/*, /developer/*)
        $isScanner = in_array($userRole, ['ROLE_SCANNER', 'SCANNER'], true);
        if ($isScanner) {
            $isScannerAllowed = false;
            if ($arguments !== null && !empty($arguments)) {
                foreach ($arguments as $arg) {
                    if (in_array($arg, ['SCANNER', 'ROLE_SCANNER'], true)) {
                        $isScannerAllowed = true;
                        break;
                    }
                }
            }
            if (!$isScannerAllowed) {
                if ($request->isAJAX() || str_starts_with($path, 'api/')) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'  => 'error',
                            'code'    => 'FORBIDDEN',
                            'message' => 'Scanner accounts do not have permission to access administrative modules.',
                        ]);
                }
                return redirect()->to('/scanner');
            }
        }

        // Role check if arguments passed
        if ($arguments !== null && !empty($arguments)) {

            // SUPER_ADMIN has full administrative pass-through
            $isSuperAdmin = in_array($userRole, ['ROLE_SUPER_ADMIN', 'SUPER_ADMIN'], true);

            // Check if arguments include SUPER_ADMIN specifically
            $requiresSuperAdminOnly = true;
            foreach ($arguments as $arg) {
                if (!in_array($arg, ['SUPER_ADMIN', 'ROLE_SUPER_ADMIN'], true)) {
                    $requiresSuperAdminOnly = false;
                    break;
                }
            }

            $hasAccess = false;
            if ($isSuperAdmin) {
                $hasAccess = true;
            } elseif (!$requiresSuperAdminOnly) {
                // Check if user has any of the requested roles
                foreach ($arguments as $arg) {
                    $normalizedArg = str_replace('ROLE_', '', $arg);
                    $normalizedUser = str_replace('ROLE_', '', $userRole);
                    if ($userRole === $arg || $normalizedUser === $normalizedArg) {
                        $hasAccess = true;
                        break;
                    }
                }
            }

            if (!$hasAccess) {
                if ($request->isAJAX() || str_starts_with($path, 'api/')) {
                    return service('response')
                        ->setStatusCode(403)
                        ->setJSON([
                            'status'  => 'error',
                            'code'    => 'FORBIDDEN',
                            'message' => 'You do not have permission to perform this administrative action.',
                        ]);
                }

                return service('response')
                    ->setStatusCode(403)
                    ->setBody(view('errors/html/error_403', [
                        'message' => 'Access denied. You do not have permission to access this administrative module.',
                    ]));
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Enforce strict anti-caching headers across all authenticated administrative routes
        // Prevents browser back/forward history cache (bfcache) from exposing protected screens upon logout or role switch
        $response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, post-check=0, pre-check=0');
        $response->setHeader('Pragma', 'no-cache');
        $response->setHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
        return $response;
    }
}

