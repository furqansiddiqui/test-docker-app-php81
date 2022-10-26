<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Database\DbBackup;
use App\Common\Database\Primary\DbBackups;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Database\Server\DbCredentials;

/**
 * Class Dbs
 * @package App\Services\Admin\Controllers\Auth
 */
class Dbs extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    protected function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\DbBackups');
    }

    /**
     * @return void
     */
    private function getDbsConfig(): void
    {
        $dbConfigs = $this->aK->config->db->getAll();
        $result = [];

        /** @var DbCredentials $dbConfig */
        foreach ($dbConfigs as $label => $dbConfig) {
            $result[$label] = [
                "driver" => $dbConfig->driver,
                "host" => $dbConfig->host,
                "port" => $dbConfig->port,
                "name" => $dbConfig->dbname,
            ];
        }

        $this->status(true);
        $this->response->set("config", $result);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ORM_ModelNotFoundException
     * @throws \App\Common\Exception\AppDirException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Filesystem\Exception\FilesystemException
     */
    private function downloadBackup(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            if (!$this->admin->privileges()->downloadDbBackups) {
                throw new AdminAPIException('You do not have privilege to download DB backups');
            }
        }

        try {
            $backupId = $this->input()->getInt("id");
            if (!($backupId > 0)) {
                throw new AdminAPIException('Invalid backup ID');
            }

            /** @var DbBackup $backup */
            $backup = DbBackups::Find()->query('WHERE `id`=?', [$backupId])->first();
        } catch (AdminAPIException $e) {
            $e->setParam("id");
            throw $e;
        }

        // Read backup file
        $backupsDir = $this->aK->dirs->backups();
        $backupFile = $backupsDir->file($backup->filename . ".zip", createIfNotExists: false);

        if (!$backupFile->permissions()->readable()) {
            throw new AdminAPIException('Backup file is not readable; Permission error');
        }

        set_time_limit(0);
        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=" . $backup->db . "_" . date("d-m-Y_H:i", $backup->epoch) . ".zip");
        header("Pragma: no-cache");
        header("Expires: 0");

        readfile($backupFile->path());
        exit;
    }

    /**
     * @return void
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function getBackups(): void
    {
        try {
            $backups = DbBackups::Find()->query('WHERE 1 ORDER BY `epoch` DESC')->limit(100)->all();
        } catch (ORM_ModelNotFoundException) {
            $backups = [];
        }

        $this->status(true);
        $this->response->set("backups", $backups);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ORM_ModelNotFoundException
     * @throws \App\Common\Exception\AppDirException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Filesystem\Exception\FilesystemException
     */
    public function get(): void
    {
        switch (strtolower($this->input()->getASCII("action"))) {
            case "config":
                $this->getDbsConfig();
                return;
            case "backups":
                $this->getBackups();
                return;
            case "download":
                $this->downloadBackup();
                return;
            default:
                throw AdminAPIException::Param("action", "Invalid action called");
        }
    }
}
