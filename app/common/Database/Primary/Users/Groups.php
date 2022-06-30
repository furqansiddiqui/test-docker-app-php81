<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\AppKernel;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Users\Group;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Groups
 * @package App\Common\Database\Primary\Users
 */
class Groups extends AbstractAppTable
{
    public const TABLE = "u_groups";
    public const ORM_CLASS = 'App\Common\Users\Group';
    public const CACHE_TTL = 86400;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->string("name")->length(32)->unique();
        $cols->int("user_count")->bytes(4)->unSigned()->default(0);
        $cols->int("updated_on")->bytes(4)->unSigned();
        $cols->primaryKey("id");
    }

    /**
     * @param int $id
     * @param bool $useCache
     * @return Group
     * @throws AppException
     * @throws AppModelNotFoundException
     */
    public static function get(int $id, bool $useCache = true): Group
    {
        $aK = AppKernel::getInstance();
        try {
            $cacheKey = sprintf('u_gr_%d', $id);
            $queryMemory = $aK->memory->query($cacheKey, self::ORM_CLASS);
            if ($useCache) {
                $queryMemory->cache(static::CACHE_TTL);
            }

            return $queryMemory->fetch(function () use ($id) {
                return self::Find()->col("id", $id)->limit(1)->first();
            });
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw new AppModelNotFoundException(sprintf('User group with ID # %d does not exist', $id));
            }

            $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to retrieve user group %d', $id));
        }
    }
}
