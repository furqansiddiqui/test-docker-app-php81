<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\AppConstants;
use App\Common\AppKernel;
use App\Common\Database\Primary\ProcessTracker;
use App\Common\Kernel\CLI\AbstractCLIScript;
use App\Common\Kernel\CLI\AbstractCronScript;
use App\Common\Kernel\CLI\ExecProcessTracker;
use App\Common\Kernel\ErrorHandler\ErrorMsg;
use Comely\CLI\ASCII\Banners;
use Comely\Filesystem\Directory;
use Comely\Utils\OOP\OOP;

/**
 * Class CLI
 * @package App\Common\Kernel
 */
class CLI extends \Comely\CLI\CLI
{
    /** @var string|null */
    protected readonly ?string $processInstanceId;
    /** @var ExecProcessTracker|null */
    public ?ExecProcessTracker $processTracker = null;

    /**
     * @param AppKernel $aK
     * @param Directory $bin
     * @param array $args
     * @throws \Comely\CLI\Exception\BadArgumentException
     */
    public function __construct(protected readonly AppKernel $aK, Directory $bin, array $args)
    {
        parent::__construct($bin, $args);

        // Events
        $this->events->scriptNotFound()->listen(function (self $cli, string $scriptClassName) {
            $this->printAppHeader();
            $cli->print(sprintf("CLI script {red}{invert} %s {/} not found", OOP::baseClassName($scriptClassName)));
            $cli->print("");
        });

        $this->events->scriptLoaded()->listen(function (self $cli, AbstractCLIScript $script) {
            // Headers & Loaded Script Name
            $displayHeader = @constant($this->execClassName . "::DISPLAY_HEADER") ?? true;
            if ($displayHeader) {
                $this->printAppHeader();
            }

            $displayLoadedName = @constant($this->execClassName . "::DISPLAY_LOADED_NAME") ?? true;
            if ($displayLoadedName) {
                $cli->inline(sprintf('CLI script {green}{invert} %s {/} loaded', OOP::baseClassName(get_class($script))));
                $cli->repeat(".", 3, 100, true);
                $cli->print("");
            }

            // Process execution tracker
            $this->processInstanceId = $script->processInstanceId();
            if ($this->processInstanceId) {
                $this->processTracker = new ExecProcessTracker();
                $this->processTracker->id = 0;
                $this->processTracker->state = ProcessTracker::STATE_RUNNING;
                $this->processTracker->cmd = $this->processInstanceId;
                $this->processTracker->isCron = $script instanceof AbstractCronScript ? 1 : 0;
                $this->processTracker->pid = getmypid();
                $this->processTracker->startOn = strval(microtime(true));
                $this->trackerSetLatestData();

                $this->processTracker->query()->insert();
                $this->processTracker->id = $this->aK->db->primary()->lastInsertId();
            }
        });

        $this->events->afterExec()->listen(function (AbstractCLIScript $script, bool $isSuccess) {
            // Process tracker
            if ($this->processTracker) {
                $this->processTracker->state = $isSuccess ? ProcessTracker::STATE_END_SUCCESS : ProcessTracker::STATE_END_ERROR;
                $this->trackerSetLatestData();
                $this->processTracker->query()->update();
            }

            // Display Triggered Errors?
            $displayErrors = @constant($this->execClassName . "::DISPLAY_TRIGGERED_ERRORS") ?? true;
            if ($displayErrors) {
                $errors = $this->aK->errors->all();
                $errorsCount = $this->aK->errors->count();

                $this->print("");
                if ($errorsCount) {
                    $this->repeat(".", 10, 50, true);
                    $this->print("");
                    $this->print(sprintf("{red}{invert} %d {/}{red}{b} triggered errors!{/}", $errorsCount));
                    /** @var ErrorMsg $error */
                    foreach ($errors as $error) {
                        $this->print(sprintf('{grey}│  ┌ {/}{yellow}Type:{/} {magenta}%s{/}', strtoupper($error->typeStr)));
                        $this->print(sprintf('{grey}├──┼ {/}{yellow}Message:{/} %s', $error->message));
                        $this->print(sprintf("{grey}│  ├ {/}{yellow}File:{/} {cyan}%s{/}", $error->file));
                        $this->print(sprintf("{grey}│  └ {/}{yellow}Line:{/} %d", $error->line ?? -1));
                        $this->print("{grey}│{/}");
                    }

                    $this->print("");
                } else {
                    $this->print("{grey}No triggered errors!{/}");
                }
            }
        });
    }

    /**
     * @return void
     */
    public function printAppHeader(): void
    {
        $this->print(sprintf("{yellow}{invert}Comely App Kernel{/} {grey}v%s{/}", AppConstants::VERSION), 200);
        $this->print(sprintf("{cyan}{invert}Comely CLI{/} {grey}v%s{/}", \Comely\CLI\CLI::VERSION), 200);

        // App Introduction
        $this->print("");
        $this->repeat("~", 5, 100, true);
        foreach (Banners::Digital($this->aK->constant("name") ?? "Untitled App")->lines() as $line) {
            $this->print("{magenta}{invert}" . $line . "{/}");
        }

        $this->repeat("~", 5, 100, true);
        $this->print("");
    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     */
    public function updateExecTracker(): void
    {
        if (!$this->processTracker) {
            return;
        }

        $this->trackerSetLatestData();
        $this->processTracker->query()->update();
    }

    /**
     * @return void
     */
    public function trackerSetLatestData(): void
    {
        if (!$this->processTracker) {
            return;
        }

        $this->processTracker->cpuLoad = sys_getloadavg()[0] * 10 ^ 2;
        $this->processTracker->memoryUsage = memory_get_usage();
        $this->processTracker->memoryUsageReal = memory_get_usage(true);
        $this->processTracker->peakMemoryUsage = memory_get_peak_usage();
        $this->processTracker->peakMemoryUsageReal = memory_get_peak_usage(true);
        $this->processTracker->lastUpdateOn = strval(microtime(true));
    }
}
