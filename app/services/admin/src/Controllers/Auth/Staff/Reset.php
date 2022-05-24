<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Staff;

use App\Common\Database\Primary\Administrators;
use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Reset
 * @package App\Services\Admin\Controllers\Auth\Staff
 */
class Reset extends AbstractEditStaffController
{
    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function editAccount(): void
    {
        if (!$this->editStaff->hasChecksumValidated()) {
            throw new AdminAPIException('Checksum fail; Please re-compute staff checksum first');
        }


        // Status
        $newStatus = Validator::getBool($this->input()->getASCII("status")) ? 1 : 0;
        if ($newStatus !== $this->editStaff->status) {
            $changeStatus = true;
        }

        // Email Address
        $emailValidator = Validator::EmailAddress(32);

        try {
            $newEmail = $emailValidator->getValidated($this->input()->getASCII("email"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("email", "Invalid e-mail address", $e->getCode());
        }

        if ($newEmail !== $this->editStaff->email) {
            try {
                $dup = Administrators::Email($newEmail);
            } catch (\Exception) {
            }

            if (isset($dup)) {
                throw AdminAPIException::Param("email", "This e-mail address is already in use");
            }

            $changeEmail = $newEmail;
        }

        // Changes?
        if (!isset($changeStatus) && !isset($changeEmail)) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();
        $logs = [];

        try {

            if (isset($changeStatus)) {
                $this->editStaff->status = $this->editStaff->status === 1 ? 0 : 1;
                $logs[] = [
                    sprintf('Staff "%s" status changed to %s', $this->editStaff->email, $this->editStaff->status === 1 ? "ENABLED" : "DISABLED"),
                    ["staff:" . $this->editStaff->id, "staff-edit", "staff-status"]
                ];
            }

            if (isset($changeEmail)) {
                $existingEmail = $this->editStaff->email;
                $this->editStaff->email = $newEmail;
                $logs[] = [
                    sprintf('Staff "%s" e-mail address changed to "%s"', $existingEmail, $this->editStaff->email),
                    ["staff:" . $this->editStaff->id, "staff-edit", "staff-email"]
                ];
            }

            if ($logs) {
                $this->editStaff->set("checksum", $this->editStaff->checksum()->raw());
                $this->editStaff->timeStamp = time();
                $this->editStaff->query()->update();

                foreach ($logs as $log) {
                    $this->adminLogEntry($log[0], flags: $log[1]);
                }

                $db->commit();
            }
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function changePassword(): void
    {
        if (!$this->editStaff->hasChecksumValidated()) {
            throw new AdminAPIException('Checksum fail; Please re-compute staff checksum first');
        }

        $passwordValidator = Validator::Password(minStrength: 3);

        try {
            $tempPassword = $passwordValidator->getValidated($this->input()->getASCII("tempPassword"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("tempPassword", "Invalid entered password", $e->getCode());
        }

        $cred = $this->editStaff->credentials();
        if ($cred->verifyPassword($tempPassword)) {
            throw new AdminAPIException('There are no changes to be saved!');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $cred->changePassword($tempPassword);
            $this->editStaff->set("credentials", $this->editStaff->cipher()->encrypt($cred)->raw());
            $this->editStaff->timeStamp = time();
            $this->editStaff->query()->update();

            $this->adminLogEntry(
                sprintf('Staff "%s" password reset', $this->editStaff->email),
                flags: ["staff:" . $this->editStaff->id, "staff-password"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Security\Exception\CipherException
     */
    private function reset2fa(): void
    {
        $cred = $this->editStaff->credentials();

        // Check if GoogleAuth seed is set
        if (!$cred->getGoogleAuthSeed()) {
            throw new AdminAPIException('GoogleAuth seed is already disabled for this account');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $cred->changeGoogleAuthSeed(null);
            $this->editStaff->set("credentials", $this->editStaff->cipher()->encrypt($cred)->raw());
            $this->editStaff->timeStamp = time();
            $this->editStaff->query()->update();

            $this->adminLogEntry(
                sprintf('Staff "%s" Google 2FA seed removed', $this->editStaff->email),
                flags: ["staff:" . $this->editStaff->id, "staff-2fa"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function resetChecksum(): void
    {
        if ($this->editStaff->hasChecksumValidated()) {
            throw new AdminAPIException('Checksum is already good for this account');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->editStaff->set("checksum", $this->editStaff->checksum()->raw());
            $this->editStaff->timeStamp = time();
            $this->editStaff->query()->update();

            $this->adminLogEntry(
                sprintf('Staff "%s" checksum recomputed', $this->editStaff->email),
                flags: ["staff:" . $this->editStaff->id, "staff-checksum"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Security\Exception\CipherException
     */
    private function rebuildPrivileges(): void
    {
        try {
            $privileges = $this->editStaff->privileges();
        } catch (\Exception) {
        }

        if (isset($privileges) && $privileges->adminId == $this->editStaff->id) {
            throw new AdminAPIException('Privileges rebuild is not required');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $privileges = new \App\Common\Admin\Privileges($this->editStaff);
            $this->editStaff->set("privileges", $this->editStaff->cipher()->encrypt($privileges)->raw());
            $this->editStaff->timeStamp = time();
            $this->editStaff->query()->update();

            $this->adminLogEntry(
                sprintf('Staff "%s" privileges object rebuilt', $this->editStaff->email),
                flags: ["staff:" . $this->editStaff->id, "staff-privileges"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Security\Exception\CipherException
     */
    public function post(): void
    {
        $action = strtolower($this->input()->getASCII("action"));
        switch ($action) {
            case "account":
                $this->totpResourceLock();
                $this->editAccount();
                return;
            case "password":
                $this->totpResourceLock();
                $this->changePassword();
                return;
            case "2fa":
                $this->totpResourceLock();
                $this->reset2fa();
                return;
            case "checksum":
                $this->totpResourceLock();
                $this->resetChecksum();
                return;
            case "privileges":
                $this->totpResourceLock();
                $this->rebuildPrivileges();
                return;
            default:
                throw new AdminAPIException('Invalid action requested');
        }
    }
}
