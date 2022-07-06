<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Users\Profile;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Database\Schema;

/**
 * Class Profiles
 * @package App\Services\Admin\Controllers\Auth\Users
 */
class Profiles extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Profiles');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        $profile = $this->fetchUserProfile();
        $changes = 0;

        if ($this->inputSetProfileVar($profile, "address1", 64, "Address line 1")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "address2", 64, "Address line 2")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "postalCode", 16, "Postal Code")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "state", 32, "State/Province")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "city", 32, "City")) {
            $changes++;
        }

        // Changes
        if (!($changes > 0)) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $profile->query()->where("user_id", $profile->userId)->save();

            // Admin Log Entry
            $this->adminLogEntry(
                sprintf('User %d profile updated', $profile->userId),
                flags: ["users", "user-profile", "user:" . $profile->userId]
            );

            $profile->isRegistered = true;
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
        $this->response->set("profile", $profile);
    }

    /**
     * @param Profile $profile
     * @param string $param
     * @param int $maxLen
     * @param string|null $label
     * @return bool
     * @throws AdminAPIException
     */
    private function inputSetProfileVar(Profile $profile, string $param, int $maxLen, ?string $label = null): bool
    {
        if (is_string($profile->$param) && !$profile->$param) {
            $profile->$param = null;
        }

        $value = $this->input()->getASCII($param);
        if (!$value) {
            $value = null;
        }

        if ($value && strlen($value) > $maxLen) {
            throw AdminAPIException::Param($param, sprintf("%s cannot exceed length of %d bytes", $label ?? $param, $maxLen));
        }

        if ($profile->$param !== $value) {
            $profile->$param = $value;
            return true;
        }

        return false;
    }

    /**
     * @return Profile
     * @throws AdminAPIException
     */
    private function fetchUserProfile(): Profile
    {
        $userId = $this->input()->getInt("user", unSigned: true) ?? -1;
        if (!($userId > 0)) {
            throw AdminAPIException::Param("user", 'Invalid user id');
        }

        try {
            $profile = \App\Common\Database\Primary\Users\Profiles::Get($userId);
            $profile->isRegistered = true;
        } catch (AppModelNotFoundException) {
            $profile = new Profile();
            $profile->userId = $userId;
            $profile->isRegistered = false;
        } catch (AppException $e) {
            throw AdminAPIException::Param("user", $e->getMessage(), $e->getCode());
        }

        return $profile;
    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    public function get(): void
    {
        $profile = $this->fetchUserProfile();

        $this->status(true);
        $this->response->set("profile", $profile);
    }
}
