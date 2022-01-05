<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Admin;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Administrators;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Logs
 * @package App\Common\Database\Primary\Admin
 */
class Logs extends AbstractAppTable
{
    public const TABLE = "a_logs";
    public const ORM_CLASS = 'App\Common\Admin\Log';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->int("admin")->bytes(4)->unSigned();
        $cols->string("flags")->length(255)->nullable();
        $cols->string("controller")->length(255)->nullable();
        $cols->string("log")->length(255);
        $cols->string("ip_address")->length(45);
        $cols->int("time_stamp")->bytes(4)->unSigned();
        $cols->primaryKey("id");

        $constraints->foreignKey("admin")->table(Administrators::TABLE, "id");
    }
}
