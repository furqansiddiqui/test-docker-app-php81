<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Admin\Administrator;
use App\Common\AppConstants;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use Comely\Database\Exception\ORM_Exception;
use Comely\Database\Exception\ORM_ModelNotFoundException;

/**
 * Class Staff
 * @package App\Services\Admin\Controllers\Auth
 */
class Staff extends AuthAdminAPIController
{
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     * @throws ORM_Exception
     * @throws ORM_ModelNotFoundException
     * @throws \Comely\Database\Exception\SchemaTableException
     */
    public function get(): void
    {
        $result = [];

        $admins = Administrators::Find()->query('WHERE 1')->all();
        /** @var Administrator $admin */
        foreach ($admins as $admin) {
            try {
                $admin->validateChecksum();
            } catch (AppException) {
            }

            $result[] = [
                "id" => $admin->id,
                "status" => $admin->status === 1,
                "email" => $admin->email,
                "phone" => $admin->phone,
                "checksum" => $admin->hasChecksumValidated(),
                "isRoot" => in_array($admin->id, AppConstants::ROOT_ADMINISTRATORS),
            ];
        }

        $this->status(true);
        $this->response->set("staff", $result);
    }
}
