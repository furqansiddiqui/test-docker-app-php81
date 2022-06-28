<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\Database\AbstractAppTable;
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
}
