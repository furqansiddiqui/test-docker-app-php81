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
