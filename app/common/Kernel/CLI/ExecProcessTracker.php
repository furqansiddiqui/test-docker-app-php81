<?php
declare(strict_types=1);

namespace App\Common\Kernel\CLI;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\ProcessTracker;

/**
 * Class ExecProcessTracker
 * @package App\Common\Kernel\CLI
 */
class ExecProcessTracker extends AbstractAppModel
{
    public const TABLE = ProcessTracker::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $cmd;
    /** @var int */
    public int $state = ProcessTracker::STATE_RUNNING;
    /** @var int */
    public int $isCron;
    /** @var int */
    public int $pid;
    /** @var int */
    public int $cpuLoad;
    /** @var int */
    public int $memoryUsage;
    /** @var int */
    public int $memoryUsageReal;
    /** @var int */
    public int $peakMemoryUsage;
    /** @var int */
    public int $peakMemoryUsageReal;
    /** @var string */
    public string $startOn;
    /** @var string|null */
    public ?string $lastUpdateOn = null;
}
