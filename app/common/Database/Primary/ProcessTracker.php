<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\Database\AbstractAppTable;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class ProcessTracker
 * @package App\Common\Database\Primary
 */
class ProcessTracker extends AbstractAppTable
{
    public const TABLE = "p_top";

    public const STATE_RUNNING = 0x00;
    public const STATE_END_SUCCESS = 0x01;
    public const STATE_END_ERROR = 0x02;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("id")->bytes(8)->unSigned()->autoIncrement();
        $cols->string("cmd")->length(128);
        $cols->int("state")->bytes(1)->unSigned()->default(0);
        $cols->int("is_cron")->bytes(1)->unSigned();
        $cols->int("pid")->bytes(2)->unSigned();
        $cols->int("cpu_load")->bytes(2)->unSigned();
        $cols->int("memory_usage")->bytes(8)->unSigned();
        $cols->int("memory_usage_real")->bytes(8)->unSigned();
        $cols->int("peak_memory_usage")->bytes(8)->unSigned();
        $cols->int("peak_memory_usage_real")->bytes(8)->unSigned();
        $cols->decimal("start_on")->precision(14, 4)->unSigned();
        $cols->decimal("last_update_on")->precision(14, 4)->unSigned()->nullable();
        $cols->primaryKey("id");
    }
}
