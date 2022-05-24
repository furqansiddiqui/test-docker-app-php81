<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Staff;

use App\Common\Admin\Administrator;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;

/**
 * Class AbstractEditStaffController
 * @package App\Services\Admin\Controllers\Auth\Staff
 */
abstract class AbstractEditStaffController extends AuthAdminAPIController
{
    /** @var Administrator */
    protected Administrator $editStaff;

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     */
    protected function authCallback(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            throw new AdminAPIException('You are not privileged to edit administrators');
        }

        try {
            $staffId = $this->input()->getInt("id", true);
            if (!$staffId) {
                throw new AdminAPIException('Invalid staff ID to make changes to');
            }

            try {
                $this->editStaff = Administrators::Get($staffId);
            } catch (AppException $e) {
                throw new AdminAPIException($e->getMessage());
            }
        } catch (AdminAPIException $e) {
            $e->setParam("id");
            throw $e;
        }

        if ($this->admin->id === $this->editStaff->id) {
            throw new AdminAPIException('Cannot use this tool to edit your own account');
        }

        try { // Validate staff checksum
            $this->editStaff->validateChecksum();
        } catch (AppException) {
        }
    }
}
