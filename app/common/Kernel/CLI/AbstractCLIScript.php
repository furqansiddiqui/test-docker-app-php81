<?php
declare(strict_types=1);

namespace App\Common\Kernel\CLI;

use App\Common\AppKernel;
use App\Common\Kernel\CLI;
use App\Common\Kernel\ErrorHandler\ErrorMsg;
use Comely\CLI\Abstract_CLI_Script;
use Comely\Database\Schema;
use Comely\Utils\OOP\OOP;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestBlocked;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestTimeout;
use FurqanSiddiqui\SemaphoreEmulator\ResourceLock;

/**
 * Class AbstractCLIScript
 * @package App\Common\Kernel\CLI
 */
abstract class AbstractCLIScript extends Abstract_CLI_Script
{
    /** @var bool */
    public const DISPLAY_HEADER = true;
    /** @var bool */
    public const DISPLAY_LOADED_NAME = true;
    /** @var bool */
    public const DISPLAY_TRIGGERED_ERRORS = true;

    /** @var AppKernel */
    protected readonly AppKernel $aK;
    /** @var string */
    protected readonly string $scriptClassname;
    /** @var string|null */
    protected readonly ?string $semaphoreLockId;
    /** @var ResourceLock|null */
    protected ?ResourceLock $semaphoreLock = null;

    /**
     * @param CLI $cli
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function __construct(CLI $cli)
    {
        $this->aK = AppKernel::getInstance();
        parent::__construct($cli);

        $this->scriptClassname = OOP::baseClassName(static::class);

        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\ProcessTracker');
    }

    /**
     * @return string|null
     */
    abstract public function processInstanceId(): ?string;

    /**
     * @return string|null
     */
    abstract public function semaphoreLockId(): ?string;

    /**
     * @return void
     * @throws \App\Common\Exception\AppException
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException
     */
    public function exec(): void
    {
        // Sempahore Lock Check
        $this->semaphoreLockId = $this->semaphoreLockId();
        if ($this->semaphoreLockId) {
            try {
                $this->semaphoreLock = $this->aK->semaphoreEmulator()->obtainLock($this->semaphoreLockId, null, 10);
                $this->semaphoreLock->setAutoRelease();
            } catch (ConcurrentRequestBlocked|ConcurrentRequestTimeout) {
                $this->print(sprintf('{red}Another process for this {cyan}%s{/}{red} is running...{/}', $this->scriptClassname));
                return;
            }
        }
    }

    /**
     * @return void
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException
     */
    final public function releaseSemaphoreLock(): void
    {
        $this->semaphoreLock?->release();
    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     */
    public function updateExecTracker(): void
    {
        /** @var CLI $cli */
        $cli = $this->cli;
        $cli->updateExecTracker();
    }

    /**
     * @param \Throwable $t
     * @param int $tabIndex
     * @return string
     */
    protected function exceptionMsg2Str(\Throwable $t, int $tabIndex = 0): string
    {
        $tabs = str_repeat("\t", $tabIndex);
        return $tabs . "{red}[{/}{yellow}" . get_class($t) . "{/}{red}][{yellow}" . $t->getCode() . "{/}{red}] " .
            $t->getMessage();
    }

    /**
     * @param int $tabIndex
     */
    protected function printErrors(int $tabIndex = 0): void
    {
        $tabs = str_repeat("\t", $tabIndex);
        $errorLog = $this->aK->errors;
        if ($errorLog->count()) {
            $this->print("");
            $this->print($tabs . "Caught triggered errors:");
            /** @var ErrorMsg $errorMsg */
            foreach ($errorLog->all() as $errorMsg) {
                $this->print($tabs . sprintf('{red}[{b}%s{/}]{red} %s{/}', $errorMsg->typeStr, $errorMsg->message));
                $this->print($tabs . sprintf('⮤ in {magenta}%s{/} on line {magenta}%d{/}', $errorMsg->file, $errorMsg->line));
            }

            $this->print("");
        }
    }
}
