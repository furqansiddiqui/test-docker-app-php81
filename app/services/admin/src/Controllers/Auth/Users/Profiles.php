<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Users\Profile;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Database\Schema;
use Comely\Utils\Validator\Exception\ValidatorException;
use Comely\Utils\Validator\UTF8_Validator;

/**
 * Class Profiles
 * @package App\Services\Admin\Controllers\Auth\Users
 */
class Profiles extends AuthAdminAPIController
{
    /** @var UTF8_Validator */
    private UTF8_Validator $utf8Validator;

    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Profiles');
        Schema::Bind($db, 'App\Common\Database\Primary\Users');

        $this->utf8Validator = Validator::UTF8();
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws AppModelNotFoundException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        $action = trim(strtolower($this->input()->getASCII("action")));
        switch ($action) {
            case "update":
                $this->editProfile();
                return;
            case "verifications":
                $this->updateVerifications();
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
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function updateVerifications(): void
    {
        $profile = $this->fetchUserProfile();
        $user = Users::Get(id: $profile->userId, useCache: true);
        $changes = 0;
        $adminLogs = [];

        // Identity verification
        $idVerified = Validator::getBool(trim($this->input()->getASCII("idVerified")));
        if ($idVerified !== (bool)$profile->idVerified) {
            $profile->idVerified = $idVerified ? 1 : 0;
            $adminLogs[] = sprintf('User "%s" identity marked as %s', $user->username, $idVerified ? "VERIFIED" : "UNVERIFIED");
            $changes++;
        }

        // Address verification
        $addressVerified = Validator::getBool(trim($this->input()->getASCII("addressVerified")));
        if ($addressVerified !== (bool)$profile->addressVerified) {
            $profile->addressVerified = $addressVerified ? 1 : 0;
            $adminLogs[] = sprintf('User "%s" address marked as %s', $user->username, $addressVerified ? "VERIFIED" : "UNVERIFIED");
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
            $profile->set("checksum", $profile->checksum()->raw());
            $profile->query()->update();

            foreach ($adminLogs as $adminLog) {
                $this->adminLogEntry($adminLog, flags: ["users", "user-profile", "user:" . $profile->userId]);
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $profile->deleteCached();
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
    private function editProfile(): void
    {
        $profile = $this->fetchUserProfile();
        $changes = 0;

        // First and last name
        if ($this->inputSetProfileVar($profile, "first_name", 3, 64, "First name")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "last_name", 3, 64, "Last name")) {
            $changes++;
        }

        // Gender
        if (is_string($profile->gender) && !$profile->gender) {
            $profile->gender = null;
        }

        $gender = $this->input()->getASCII("gender");
        if ($gender) {
            if (!in_array($gender, ["m", "f", "o"])) {
                throw AdminAPIException::Param("gender", "Invalid gender selection");
            }
        } else {
            $gender = null;
        }

        if ($profile->gender !== $gender) {
            $profile->gender = $gender;
            $changes++;
        }

        // Date of birth
        $currentDob = $profile->private("dob");
        if (!is_null($currentDob) && !$currentDob) {
            $profile->set("dob", null);
        }

        $dob = $this->input()->getASCII("dob");
        if ($dob) {
            $dobTs = strtotime($dob);
            if (!$dobTs) {
                throw AdminAPIException::Param("gender", "Invalid date of birth format");
            }

            if ($profile->setDob((int)date("j", $dobTs), (int)date("n", $dobTs), (int)date("Y", $dobTs))) {
                $changes++;
            }
        }

        // Complete Address
        if ($this->inputSetProfileVar($profile, "address1", 3, 64, "Address line 1")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "address2", 3, 64, "Address line 2")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "postalCode", 2, 16, "Postal Code")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "state", 2, 32, "State/Province")) {
            $changes++;
        }

        if ($this->inputSetProfileVar($profile, "city", 2, 32, "City")) {
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
            $profile->set("checksum", $profile->checksum()->raw());
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

        try {
            $profile->deleteCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
        $this->response->set("profile", $profile);
    }

    /**
     * @param Profile $profile
     * @param string $param
     * @param int $minLen
     * @param int $maxLen
     * @param string|null $label
     * @param bool $isRequired
     * @return bool
     * @throws AdminAPIException
     * @noinspection PhpSameParameterValueInspection
     */
    private function inputSetProfileVar(Profile $profile, string $param, int $minLen, int $maxLen, ?string $label = null, bool $isRequired = false): bool
    {
        if (is_string($profile->$param) && !$profile->$param) {
            $profile->$param = null;
        }

        $value = $this->input()->getUnsafe($param);
        if (is_string($value) && $value) {
            $this->utf8Validator->len(min: $minLen, max: $maxLen);

            try {
                $value = $this->utf8Validator->getValidated($value);
            } catch (ValidatorException $e) {
                $error = match ($e->getCode()) {
                    \Comely\Utils\Validator\Validator::LENGTH_UNDERFLOW_ERROR => sprintf('%s must be at least %d characters long', $label, $minLen),
                    \Comely\Utils\Validator\Validator::LENGTH_OVERFLOW_ERROR => sprintf('%s cannot exceed %d characters', $label, $maxLen),
                    \Comely\Utils\Validator\Validator::UTF8_CHARSET_ERROR => sprintf('%s contains an illegal character', $label),
                    default => sprintf('UTF-8 validation failed [#%d] for "%s"', $e->getCode(), $label)
                };

                throw AdminAPIException::Param($param, $error, $e->getCode());
            }
        } else {
            $value = null;
        }

        if (!$value && $isRequired) {
            throw AdminAPIException::Param($param, sprintf("%s is required", $label));
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
            $profile = \App\Common\Database\Primary\Users\Profiles::Get($userId, false);
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

        try {
            $profile->validateChecksum();
        } catch (AppException) {
        }

        $profile->dobTs = $profile->dob();
        if ($profile->dobTs) {
            $profile->dobDate = [
                "d" => date("j", $profile->dobTs),
                "m" => date("n", $profile->dobTs),
                "Y" => date("Y", $profile->dobTs)
            ];
        }

        $this->status(true);
        $this->response->set("profile", $profile);
    }
}
