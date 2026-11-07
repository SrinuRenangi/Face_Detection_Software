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

        // Role check if arguments passed
        if ($arguments !== null && !empty($arguments)) {
            $userRole = $session->get('admin_role');
            if (!in_array($userRole, $arguments, true)) {
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
        // Do nothing
    }
}

