<?php
declare(strict_types=1);

namespace bin;

use App\Common\Database\DbBackup;
use App\Common\Database\Primary\Admin\Logs;
use App\Common\Database\Primary\Admin\Sessions;
use App\Common\Database\Primary\DbBackups;
use App\Common\Database\PublicAPI\Queries;
use App\Common\Database\PublicAPI\QueriesPayload;
use App\Common\DataStore\SystemConfig;
use App\Common\Kernel\CLI\AbstractCLIScript;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Utils\Time\TimeUnits;

/**
 * Class app_daemon
 * @package bin
 */
class app_daemon extends AbstractCLIScript
{
    /** @var SystemConfig */
    private SystemConfig $systemConfig;

    public function exec(): void
    {
        parent::exec(); // inheritance call for Sempahore process locking

        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\DbBackups');

        // Bootstrap next cron exec
        $nextCronExec = time() - 1;

        while (true) {
            $timeStamp = time();
            if ($timeStamp >= $nextCronExec) {
                $this->runSystemCron();

                // Set cron to run next hour at the top
                $nextCronExec = $timeStamp + (3600 - ($timeStamp % 3600));
            }


        }
    }

    private function createDbBackup(bool $isAuto, string $tabs = "\t")
    {
        $backupsDir = $this->aK->dirs->backups();

    }

    private function runSystemCron(): void
    {
        $this->print("{grey}[" . date("d M Y H:i") . "]{/}");
        $this->inline("Loading {yellow}{invert} SystemConfig {/} instance from Cache {grey}...{/} ");
        $this->systemConfig = SystemConfig::getInstance(useCache: true);
        $this->print("{green}OK{/}");
        $this->print("");

        // Run the Pruning
        $this->runPurges();

        // Print Triggered Errors
        $this->printErrors();

        // Cleanup
        $this->aK->db->flushAllQueries();
        $this->aK->errors->flush();
    }

    private function runDbBackups(): void
    {
        $this->print("");
        $this->inline("{cyan}Auto Database Backups{/} ... ");
        if (!$this->systemConfig->autoDbBackup || $this->systemConfig->autoDbBackupHours < 1) {
            $this->print("{red}Disabled{/}");
            return;
        }

        $timeStamp = time();
        $dbBackupEvery = $this->systemConfig->autoDbBackupHours * 3600;

        try {
            /** @var DbBackup $lastDbBackupOn */
            $lastDbBackupOn = DbBackups::Find()->query('WHERE `manual`=0 ORDER BY `epoch` DESC LIMIT 1')->first();
        } catch (ORM_ModelNotFoundException) {
            $lastDbBackupOn = new DbBackup(); // No backups in table, create first one now
            $lastDbBackupOn->epoch = $timeStamp - $dbBackupEvery;
        }

        if (($timeStamp - $lastDbBackupOn->epoch) >= ($dbBackupEvery - 60)) {
            $this->createDbBackup(isAuto: true);
            return;
        }

        $timeUnits = new TimeUnits();
        $timeLeft = $timeUnits->timeToUnits($dbBackupEvery - ($timeStamp - $lastDbBackupOn->epoch));
        $this->print(
            sprintf("in {green}{invert} %d h %d m %s s{/}",
                $timeLeft->hours + ($timeLeft->days * 24),
                $timeLeft->minutes,
                $timeLeft->seconds)
        );
    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function runPurges(): void
    {
        $db = $this->aK->db->primary();
        $timeStamp = time();

        // Admin Sessions Purge
        $this->inline("Purging {magenta}Administration Sessions{/} ");
        if ($this->systemConfig->adminSessionsPurge > 0) {
            $this->inline(sprintf("older than {green}{invert} %d {/} days.", $this->systemConfig->adminSessionsPurge));
            $query = $db->exec(
                sprintf("DELETE " . "FROM `%s` WHERE `last_used_on`<=?", Sessions::TABLE),
                [$timeStamp - ($this->systemConfig->adminSessionsPurge * 86400)]
            );

            $this->print("\t {cyan}{invert} " . $query->rows() . " {/}");
        } else {
            $this->print("... {red}Disabled{/}");
        }

        // Admin Logs Purge
        $this->inline("Purging {magenta}Administration Log{/} ");
        if ($this->systemConfig->adminLogsPurge > 0) {
            $this->inline(sprintf("older than {green}{invert} %d {/} days.", $this->systemConfig->adminLogsPurge));
            $query = $db->exec(
                sprintf("DELETE " . "FROM `%s` WHERE `time_stamp`<=?", Logs::TABLE),
                [$timeStamp - ($this->systemConfig->adminLogsPurge * 86400)]
            );

            $this->print("\t {cyan}{invert} " . $query->rows() . " {/}");
        } else {
            $this->print("... {red}Disabled{/}");
        }

        // Users Logs Purge
        $this->inline("Purging {magenta}Users Log{/} ");
        if ($this->systemConfig->usersLogsPurge > 0) {
            $this->inline(sprintf("older than {green}{invert} %d {/} days.", $this->systemConfig->usersLogsPurge));
            $this->print("\t {red}{invert} TODO {/}");
        } else {
            $this->print("... {red}Disabled{/}");
        }

        // Public API Queries and Payloads
        $apiLogsDb = $this->aK->db->apiLogs();
        $this->inline("Purging {magenta}Public API Queries{/} ");
        if ($this->systemConfig->publicAPIQueriesPurge > 0) {
            $this->inline(sprintf("older than {green}{invert} %d {/} days.", $this->systemConfig->publicAPIQueriesPurge));
            // $pruneTs = $timeStamp - ($this->systemConfig->publicAPIQueriesPurge * 86400);
            $pruneTs = $timeStamp - 300; //Todo: temp 5-min setting
            $pruneFromId = $apiLogsDb->fetch(
                sprintf("SELECT " . "* FROM `%s` WHERE `start_on`<=? ORDER BY `id` DESC LIMIT 1", Queries::TABLE),
                [$pruneTs]
            )->row();

            $pruneFromId = intval($pruneFromId["id"] ?? 0);
            if ($pruneFromId) {
                // Delete queries payload
                $delQP = $apiLogsDb->exec(
                    sprintf("DELETE " . "FROM `%s` WHERE `query`<=?", QueriesPayload::TABLE),
                    [$pruneFromId]
                );

                // Delete queries
                $delQ = $apiLogsDb->exec(
                    sprintf("DELETE " . "FROM `%s` WHERE `id`<=?", Queries::TABLE),
                    [$pruneFromId]
                );

                $this->print("\t {cyan}{invert} " . $delQP->rows() . " {/} {yellow}{invert} " . $delQ->rows() . " {/}");
            } else {
                $this->print("\t {cyan}{invert} 0 {/}");
            }
        } else {
            $this->print("... {red}Disabled{/}");
        }

        // Public API Sessions
        $this->inline("Purging {magenta}Public API Sessions{/} ");
        if ($this->systemConfig->publicAPISessionsPurge > 0) {
            $this->inline(sprintf("older than {green}{invert} %d {/} days.", $this->systemConfig->publicAPISessionsPurge));
            $query = $db->exec(
                sprintf(
                    "SET FOREIGN_KEY_CHECKS=0; DELETE FROM `%s` WHERE `last_used_on`<=?; SET FOREIGN_KEY_CHECKS=1;",
                    \App\Common\Database\PublicAPI\Sessions::TABLE
                ),
                [$timeStamp - ($this->systemConfig->adminLogsPurge * 86400)]
            );

            $this->print("\t {cyan}{invert} " . $query->rows() . " {/}");
        } else {
            $this->print("... {red}Disabled{/}");
        }
    }

    /**
     * @return string|null
     */
    public function processInstanceId(): ?string
    {
        return "app_daemon";
    }

    /**
     * @return string|null
     */
    public function semaphoreLockId(): ?string
    {
        return "app_daemon";
    }
}
