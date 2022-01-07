<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Admin\Administrator;
use App\Common\AppKernel;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Administrators
 * @package App\Common\Database\Primary
 */
class Administrators extends AbstractAppTable
{
    public const TABLE = "admins";
    public const ORM_CLASS = 'App\Common\Admin\Administrator';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->int("status")->bytes(1)->unSigned()->default(1);
        $cols->string("email")->length(32)->unique();
        $cols->string("phone")->length(32)->nullable();
        $cols->binary("credentials")->length(4096);
        $cols->binary("privileges")->length(4096)->nullable();
        $cols->binary("web_auth_session")->fixed(32)->nullable();
        $cols->string("web_auth_secret")->fixed(16)->nullable();
        $cols->binary("app_auth_session")->fixed(32)->nullable();
        $cols->string("app_auth_secret")->fixed(16)->nullable();
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");
    }

    /**
     * @param int $id
     * @return Administrator
     * @throws AppException
     */
    public static function Get(int $id): Administrator
    {
        $aK = AppKernel::getInstance();
        try {
            return $aK->memory->query(sprintf('admin_%d', $id), self::ORM_CLASS)
                ->fetch(function () use ($id) {
                    return self::Find()->col("id", $id)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            }

            throw new AppException('Failed to retrieve Administrator account');
        }
    }

    /**
     * @param string $email
     * @return Administrator
     * @throws AppException
     */
    public static function Email(string $email): Administrator
    {
        $aK = AppKernel::getInstance();
        try {
            $emailHash = md5($email);
            return $aK->memory->query(sprintf('admin_em_%s', $emailHash), self::ORM_CLASS)
                ->fetch(function () use ($email) {
                    return self::Find()->col("email", $email)->limit(1)->first();
                });
        } catch (\Exception $e) {
            if (!$e instanceof ORM_ModelNotFoundException) {
                $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            }

            throw new AppException('Failed to retrieve Administrator account using e-mail address');
        }
    }
}
