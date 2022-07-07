<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Database\Primary\Countries;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Utils\Validator\Exception\ValidatorException;

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

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws AppModelNotFoundException
     * @throws ValidatorException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function editAccount(): void
    {
        $user = $this->fetchUserObject(true);
        $changes = 0;

        // Validators
        $usernameValidator = Validator::Username();
        $emailValidator = Validator::EmailAddress();

        // Group
        try {
            $group = Users\Groups::get($this->input()->getInt("groupId", unSigned: true), true);
        } catch (AppModelNotFoundException) {
            throw AdminAPIException::Param("groupId", "Select a users group");
        }

        if ($group->id !== $user->groupId) {
            $changes++;
        }

        // Status
        $status = strtolower(trim($this->input()->getASCII("status")));
        if (!in_array($status, ["active", "disabled"])) {
            throw AdminAPIException::Param("status", "Invalid user account status");
        }

        if ($status !== $user->status) {
            $oldStatus = $user->status;
            $user->status = $status;
            $changes++;
        }

        // Username
        try {
            $username = $usernameValidator->getValidated($this->input()->getASCII("username"));
        } catch (ValidatorException $e) {
            $errStr = match ($e->getCode()) {
                \Comely\Utils\Validator\Validator::LENGTH_UNDERFLOW_ERROR => 'Username must be 6 characters long',
                \Comely\Utils\Validator\Validator::LENGTH_OVERFLOW_ERROR => 'Username cannot exceed 16 characters',
                \Comely\Utils\Validator\Validator::REGEX_MATCH_ERROR => 'Username contains illegal character',
                default => 'Invalid username',
            };

            throw AdminAPIException::Param("username", $errStr, $e->getCode());
        }

        if (strtolower($username) !== strtolower($user->username)) {
            // Duplicate check
            try {
                $dupUsername = Users\Groups::Find()->query('WHERE `username`=?', [$username])->first();
            } catch (ORM_ModelNotFoundException) {
            }

            if (isset($dupUsername)) {
                throw AdminAPIException::Param("username", "This username is already registered");
            }

            $oldUsername = $user->username;
            $user->username = $username;
            $changes++;
        }

        // E-mail Address
        try {
            $email = $emailValidator->getNullable($this->input()->getASCII("email"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("email", "Invalid e-mail address", $e->getCode());
        }

        if ($email !== $user->email) {
            if ($email) {
                // Duplicate check
                try {
                    $dupEmail = Users\Groups::Find()->query('WHERE `email`=?', [$email])->first();
                } catch (ORM_ModelNotFoundException) {
                }

                if (isset($dupEmail)) {
                    throw AdminAPIException::Param("email", "This e-mail address is already registered");
                }

                $oldEmail = $user->email ?: "NULL";
                $user->email = $email;
            } else {
                $user->email = null;
            }

            $user->emailVerified = 0;
            $changes++;
        }

        // Phone
        $phone = trim($this->input()->getASCII("phone"));
        if ($phone) {
            if (!Validator::isValidPhone($phone)) {
                throw AdminAPIException::Param("phone", "Invalid phone number");
            }
        } else {
            $phone = null;
        }

        if ($phone !== $user->phone) {
            if ($phone) {
                // Duplicate check
                try {
                    $dupPhone = Users\Groups::Find()->query('WHERE `phone`=?', [$phone])->first();
                } catch (ORM_ModelNotFoundException) {
                }

                if (isset($dupPhone)) {
                    throw AdminAPIException::Param("phone", "This phone number is already registered");
                }

                $oldPhone = $user->phone ?: "NULL";
                $user->phone = $phone;
            } else {
                $user->phone = null;
            }

            $user->phoneVerified = 0;
            $changes++;
        }

        // Country
        $country = trim($this->input()->getASCII("country"));
        if ($country) {
            if (strlen($country) !== 3) {
                throw new AdminAPIException('Invalid selected country');
            }

            $country = Countries::Get($country, true);
        } else {
            $country = null;
        }

        if (is_null($country) && $user->country) {
            $user->country = null;
            $changes++;
        }

        if ($country) {
            if ($user->country !== $country->code) {
                $user->country = $country->code;
                $changes++;
            }
        }

        // Changes
        if (!($changes > 0)) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Admin Logs
        $adminLogs = [];
        if (isset($oldUsername)) {
            $adminLogs[] = sprintf('User %d username changed from "%s" to "%s"', $user->id, $oldUsername, $user->username);
        }

        if (isset($oldStatus)) {
            $adminLogs[] = sprintf('User "%s" status changed from %s to %s', $user->username, strtoupper($oldStatus), strtoupper($user->status));
        }

        if (isset($oldEmail)) {
            $adminLogs[] = sprintf('User "%s" e-mail address changed from %s to %s', $user->username, $oldEmail, $user->email);
        }

        if (isset($oldPhone)) {
            $adminLogs[] = sprintf('User "%s" phone no. changed from %s to %s', $user->username, $oldPhone, $user->phone);
        }

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $user->updatedOn = time();
            $user->set("checksum", $user->checksum()->raw());
            $user->query()->update();

            if (!$adminLogs) {
                $adminLogs[] = sprintf('User "%s" account updated', $user->username);
            }

            foreach ($adminLogs as $adminLog) {
                $this->adminLogEntry($adminLog, flags: ["users", "user-account", "user:" . $user->id]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $user->deleteCached(false);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
        $this->response->set("user", $user);
    }

    private function changeReferrer(): void
    {

    }

    private function updateVerifications(): void
    {

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

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function deleteUser(): void
    {
        $user = $this->fetchUserObject(true);
        if ($user->archived === 1) {
            throw new AdminAPIException('This user account is already deleted');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $user->archived = 1;
            $user->updatedOn = time();
            $user->set("checksum", $user->checksum()->raw());
            $user->query()->update();

            $this->adminLogEntry(
                sprintf('User "%s" marked as archived', $user->username),
                flags: ["users", "user-account", "user:" . $user->id]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $user->deleteCached(false);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function restoreDeletedUser(): void
    {
        $user = $this->fetchUserObject(true);
        if ($user->archived === 0) {
            throw new AdminAPIException('This user account is already active');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $user->archived = 0;
            $user->updatedOn = time();
            $user->set("checksum", $user->checksum()->raw());
            $user->query()->update();

            $this->adminLogEntry(
                sprintf('User "%s" restored from archived', $user->username),
                flags: ["users", "user-account", "user:" . $user->id]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $user->deleteCached(false);
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    public function get(): void
    {
        $user = $this->fetchUserObject();
        $errors = [];

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
     * @param bool $validateChecksum
     * @return \App\Common\Users\User
     * @throws AdminAPIException
     */
    private function fetchUserObject(bool $validateChecksum = false): \App\Common\Users\User
    {
        try {
            $userId = $this->input()->getInt("user", unSigned: true) ?? -1;
            if (!($userId > 0)) {
                throw new AdminAPIException('Invalid "user" param');
            }

            $user = Users::Get(id: $userId, useCache: false);
            try {
                $user->validateChecksum();
            } catch (AppException) {
                if ($validateChecksum) {
                    throw new AdminAPIException(sprintf('User %d checksum does not validate', $user->id));
                }
            }

            return $user;
        } catch (AdminAPIException $e) {
            $e->setParam("user");
            throw $e;
        } catch (AppException $e) {
            throw AdminAPIException::Param("user", $e->getMessage(), $e->getCode());
        }
    }
}
