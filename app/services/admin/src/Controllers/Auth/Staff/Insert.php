<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Staff;

use App\Common\Admin\Administrator;
use App\Common\Admin\Credentials;
use App\Common\Admin\Privileges;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Insert
 * @package App\Services\Admin\Controllers\Auth\Staff
 */
class Insert extends AuthAdminAPIController
{
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws ValidatorException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function post(): void
    {
        $emailValidator = Validator::EmailAddress();
        $passwordValidator = Validator::Password();

        $this->totpResourceLock();

        // Arguments
        try {
            $email = $emailValidator->getValidated($this->input()->getASCII("email"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("email", "Invalid e-mail address", $e->getCode());
        }

        try {
            $tempPassword = $passwordValidator->getValidated($this->input()->getASCII("tempPassword"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("tempPassword", "Invalid entered password", $e->getCode());
        }

        // Check if account exists
        try {
            $dup = Administrators::Email($email);
        } catch (AppException) {
        }

        if (isset($dup)) {
            throw AdminAPIException::Param("email", "This e-mail address is already registered");
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Insert new administrator account
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            // New administrator account
            $admin = new Administrator();
            $admin->id = 0;
            $admin->set("checksum", "tba");
            $admin->status = 0;
            $admin->email = $email;
            $admin->phone = null;
            $admin->set("credentials", "tba");
            $admin->set("privileges", "tba");
            $admin->timeStamp = time();
            $admin->query()->insert();

            // Finish administrator account
            $admin->id = $db->lastInsertId();
            $admin->set("checksum", $admin->checksum()->raw());
            $credentials = new Credentials($admin);
            $credentials->changePassword($tempPassword);
            $admin->set("credentials", $admin->cipher()->encrypt($credentials)->raw());
            $privileges = new Privileges($admin);
            $admin->set("privileges", $admin->cipher()->encrypt($privileges)->raw());
            $admin->query()->where("id", $admin->id)->update();

            // Admin Log Entry
            $this->adminLogEntry(
                sprintf('Admin account "%s" created', $email),
                flags: ["staff:" . $admin->id, "staff-create"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
        $this->response->set("adminId", $admin->id);
    }
}
