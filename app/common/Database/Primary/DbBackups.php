<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class DbBackups
 * @package App\Common\Database\Primary
 */
class DbBackups extends AbstractAppTable
{
    public const TABLE = 'db_backups';
    public const ORM_CLASS = 'App\Common\Database\DbBackup';

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(4)->unSigned()->autoIncrement();
        $cols->int("manual")->bytes(1)->unSigned()->default(0);
        $cols->string("db")->length(32);
        $cols->int("epoch")->bytes(4)->unSigned();
        $cols->string("filename")->fixed(40);
        $cols->int("size")->bytes(4)->unSigned();
        $cols->primaryKey("id");
    }
}
