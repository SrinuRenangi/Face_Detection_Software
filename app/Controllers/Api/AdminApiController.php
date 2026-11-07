<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\EmployeeModel;
use App\Models\KioskDeviceModel;
use App\Models\AttendanceRecordModel;

class AdminApiController extends BaseController
{
    use ResponseTrait;

    /**
     * Current authenticated admin user details
     */
    public function me()
    {
        return $this->respond([
            'status' => 'success',
            'user'   => [
                'id'       => session()->get('admin_user_id'),
                'username' => session()->get('admin_username'),
                'fullName' => session()->get('admin_full_name'),
                'role'     => session()->get('admin_role'),
            ],
        ]);
    }

    /**
     * Dashboard statistics summary
     */
    public function stats()
    {
        $role = session()->get('admin_role');
        if ($role !== 'ROLE_ADMIN' && $role !== 'ADMIN') {
            return $this->failForbidden('You do not have permission to perform this administrative action.');
        }

        $employeeModel   = new EmployeeModel();
        $kioskModel      = new KioskDeviceModel();
        $attendanceModel = new AttendanceRecordModel();

        return $this->respond([
            'status' => 'success',
            'stats'  => [
                'activeEmployees' => $employeeModel->countActive(),
                'activeDevices'   => $kioskModel->countActive(),
                'todayPunches'    => $attendanceModel->countTodayPunches(),
                'serverTime'      => date('Y-m-d H:i:s T'),
            ],
        ]);
    }
}

