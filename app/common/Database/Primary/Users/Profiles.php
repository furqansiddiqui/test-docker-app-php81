<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
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
        $cols->string("city")->length(32);
        $cols->string("state")->length(32);

        $constraints->foreignKey("user_id")->table(Users::TABLE, "id");
    }
}
