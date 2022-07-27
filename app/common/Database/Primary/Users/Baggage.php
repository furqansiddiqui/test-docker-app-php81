<?php
declare(strict_types=1);

namespace App\Common\Database\Primary\Users;

use App\Common\Database\AbstractAppTable;
use App\Common\Database\Primary\Users;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Baggage
 * @package App\Common\Database\Primary\Users
 */
class Baggage extends AbstractAppTable
{
    public const TABLE = "u_baggage";
    public const ORM_CLASS = null;
    public const CACHE_TTL = 86400 * 7; // Store into cache for upto 7 days

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("user")->bytes(4)->unSigned();
        $cols->string("key")->length(32);
        $cols->string("data")->length(1024);

        $constraints->foreignKey("user")->table(Users::TABLE, "id");
        $constraints->uniqueKey("u_key")->columns("user", "key");
    }
}
