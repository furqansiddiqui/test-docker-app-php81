<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Admin;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Sessions
 * @package App\Common\Database\Primary\Admin
 */
class Sessions extends AbstractAppTable
{
    public const TABLE = "a_sessions";
    public const ORM_CLASS = 'App\Common\Admin\Session';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(8)->unSigned()->autoIncrement();
        $cols->binary("checksum")->fixed(20);
        $cols->enum("type")->options("web", "app");
        $cols->binary("token")->fixed(32)->unique();
        $cols->int("admin_id")->bytes(4)->unSigned();
        $cols->string("last_2fa_code")->fixed(6)->nullable();
        $cols->int("last_2fa_on")->bytes(4)->unSigned()->nullable();
        $cols->int("issued_on")->bytes(4)->unSigned();
        $cols->int("last_used_on")->bytes(4)->unSigned();
        $cols->primaryKey("id");
    }
}
