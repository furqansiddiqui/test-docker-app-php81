<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\AppKernel;
use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Users\Profile;
use App\Common\Users\User;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Profiles
 * @package App\Common\Database\Primary\Users
 */
class Profiles extends AbstractAppTable
{
    public const TABLE = "u_profiles";
    public const ORM_CLASS = 'App\Common\Users\Profile';
    public const CACHE_TTL = 86400;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("utf8mb4", "utf8mb4_general_ci");

        $cols->int("user_id")->bytes(4)->unSigned()->unique();
        $cols->int("is_verified")->bytes(1)->default(0);
        $cols->string("address1")->length(64)->nullable();
        $cols->string("address2")->length(64)->nullable();
        $cols->string("postal_code")->length(16)->nullable();
        $cols->string("city")->length(32)->nullable();
        $cols->string("state")->length(32)->nullable();

        $constraints->foreignKey("user_id")->table(Users::TABLE, "id");
    }

    /**
     * @param User|int $user
     * @param bool $useCache
     * @return Profile
     * @throws AppException
     * @throws AppModelNotFoundException
     */
    public static function Get(User|int $user, bool $useCache = true): Profile
    {
        $aK = AppKernel::getInstance();

        try {
            $userId = $user instanceof User ? $user->id : $user;
            $instanceId = sprintf("u_prf_%d", $userId);
            $qM = $aK->memory->query($instanceId, self::ORM_CLASS);
            if ($useCache) {
                $qM->cache(static::CACHE_TTL);
            }

            return $qM->fetch(function () use ($userId) {
                return self::Find()->col("user_id", $userId)->limit(1)->first();
            });
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw new AppModelNotFoundException('Profile does not exist for this user');
            }

            $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve user profile');
        }
    }
}
