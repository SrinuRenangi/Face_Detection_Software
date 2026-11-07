<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

class FaceLabController extends BaseController
{
    /**
     * Isolated Browser-Based Face Recognition Feasibility Laboratory (Stage 3 Prototype)
     * Strictly isolated development environment. Zero production biometric data modified.
     */
    public function index()
    {
        $role = session()->get('admin_role');
        if ($role !== 'ROLE_ADMIN' && $role !== 'ADMIN') {
            return $this->response->setStatusCode(403)->setBody(view('errors/html/error_403', [
                'message' => 'Access denied. You do not have permission to access the Face Recognition Feasibility Lab.',
            ]));
        }

        return view('admin/face_lab', [
            'pageTitle'     => 'Face Recognition Feasibility Lab (Stage 3 Prototype)',
            'userRole'      => session()->get('admin_role'),
            'adminFullName' => session()->get('admin_full_name'),
            'adminUsername' => session()->get('admin_username'),
            'serverTime'    => date('Y-m-d H:i:s T'),
            'modelBasePath' => '/models',
        ]);
    }
}

