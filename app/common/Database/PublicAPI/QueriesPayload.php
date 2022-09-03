<?php
declare(strict_types=1);

namespace App\Common\Database\PublicAPI;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class QueriesPayload
 * @package App\Common\Database\PublicAPI
 */
class QueriesPayload extends AbstractAppTable
{
    public const TABLE = "queries_pl";
    public const ORM_CLASS = null;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("query")->bytes(8)->unSigned()->unique();
        $cols->blob("encrypted")->size("medium");

        $constraints->foreignKey("query")->table(Queries::TABLE, "id");
    }
}
