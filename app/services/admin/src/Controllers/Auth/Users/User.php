<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Database\Schema;

/**
 * Class User
 * @package App\Services\Admin\Controllers\Auth\Users
 */
class User extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    protected function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Groups');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \Exception
     */
    public function post(): void
    {
        $action = trim(strtolower($this->input()->getASCII("action")));
        switch ($action) {
            case "account":
                $this->editAccount();
                return;
            case "password":
                $this->changePassword();
                return;
            case "disable2fa":
                $this->disable2fa();
                return;
            case "checksum":
                $this->recomputeChecksum();
                return;
            case "re_credentials":
                $this->rebuildCredentials();
                return;
            case "re_params":
                $this->rebuildParams();
                return;
            case "delete":
                $this->deleteUser();
                return;
            case "restore":
                $this->restoreDeletedUser();
                return;
            default:
                throw AdminAPIException::Param("action", "Invalid action called for user account");
        }
    }

    private function editAccount(): void
    {
        $user = $this->fetchUserObject();
        $changes = 0;

        // Validators

        // Username

    }

    private function changePassword(): void
    {

    }

    private function disable2fa(): void
    {

    }

    private function recomputeChecksum(): void
    {

    }

    private function rebuildCredentials(): void
    {

    }

    private function rebuildParams(): void
    {

    }

    private function deleteUser(): void
    {

    }

    private function restoreDeletedUser(): void
    {

    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    public function get(): void
    {
        $user = $this->fetchUserObject();
        $errors = [];

        try {
            $user->validateChecksum();
        } catch (AppException $e) {
            $errors[] = $e->getMessage();
        }

        // Params
        try {
            $params = $user->params();
        } catch (AppException $e) {
            $errors[] = $e->getMessage();
        }

        $this->status(true);
        $this->response->set("user", $user);
        $this->response->set("tags", $user->tags());
        $this->response->set("params", $params ?? null);
        $this->response->set("errors", $errors);
    }

    /**
     * @return \App\Common\Users\User
     * @throws AdminAPIException
     */
    private function fetchUserObject(): \App\Common\Users\User
    {
        try {
            $userId = $this->input()->getInt("user", unSigned: true) ?? -1;
            if (!($userId > 0)) {
                throw new AdminAPIException('Invalid "user" param');
            }

            return Users::Get(id: $userId, useCache: false);
        } catch (AdminAPIException $e) {
            $e->setParam("user");
            throw $e;
        } catch (AppException $e) {
            throw AdminAPIException::Param("user", $e->getMessage(), $e->getCode());
        }
    }
}
