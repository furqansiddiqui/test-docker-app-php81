<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Config;

use App\Common\DataStore\SystemConfig;
use App\Common\Exception\AppException;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class System
 * @package App\Services\Admin\Controllers\Auth\Config
 */
class System extends AuthAdminAPIController
{
    /** @var SystemConfig */
    private SystemConfig $sysConfig;

    /**
     * @return void
     */
    protected function authCallback(): void
    {
        try {
            $this->sysConfig = SystemConfig::getInstance(false);
        } catch (AppException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            $this->sysConfig = new SystemConfig();
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            if (!$this->admin->privileges()->editConfig) {
                throw new AdminAPIException('You do not have privilege to edit configuration');
            }
        }

        $this->totpResourceLock();
        $changes = 0;

        // Auto-db backups
        $autoBackupStatus = Validator::getBool($this->input()->getASCII("autoDbBackup"));
        if ($this->sysConfig->setValue("autoDbBackup", $autoBackupStatus)) {
            $changes++;
        }

        try {
            $autoDbBackupHours = $this->input()->getInt("autoDbBackupHours", unSigned: true);
            if (!$autoDbBackupHours || $autoDbBackupHours < 1) {
                throw new AdminAPIException('Minimum value is 1 hour');
            } elseif ($autoDbBackupHours > 168) {
                throw new AdminAPIException('Maximum value cannot exceed 168 hours');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("autoDbBackupHours");
            throw $e;
        }

        if ($this->sysConfig->setValue("autoDbBackupHours", $autoDbBackupHours)) {
            $changes++;
        }

        try {
            $dbBackupPassword = $this->input()->getASCII("dbBackupPassword");
            if (!$dbBackupPassword) {
                $dbBackupPassword = null;
            } else {
                try {
                    $dbBackupPassword = Validator::Password(minStrength: 3)->getValidated($dbBackupPassword);
                } catch (ValidatorException $e) {
                    $errMsg = match ($e->getCode()) {
                        \Comely\Utils\Validator\Validator::ASCII_CHARSET_ERROR,
                        \Comely\Utils\Validator\Validator::ASCII_PRINTABLE_ERROR => 'Password contains an illegal character',
                        \Comely\Utils\Validator\Validator::LENGTH_UNDERFLOW_ERROR => 'Password must be 8 characters long',
                        \Comely\Utils\Validator\Validator::LENGTH_OVERFLOW_ERROR => 'Password cannot exceed 32 characters',
                        \Comely\Utils\Validator\Validator::CALLBACK_TYPE_ERROR => 'Password is not strong enough',
                        default => 'Invalid password'
                    };

                    throw new AdminAPIException($errMsg);
                }
            }
        } catch (AdminAPIException $e) {
            $e->setParam("dbBackupPassword");
            throw $e;
        }

        if ($this->sysConfig->setValue("dbBackupPassword", $dbBackupPassword)) {
            $changes++;
        }

        try {
            $dbBackupKeepLast = $this->input()->getInt("dbBackupKeepLast", unSigned: true);
            if (!$dbBackupKeepLast || $dbBackupKeepLast < 10) {
                throw new AdminAPIException('Minimum value is 10 backups');
            } elseif ($dbBackupKeepLast > 100) {
                throw new AdminAPIException('Maximum value cannot exceed 100 backups');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("dbBackupKeepLast");
            throw $e;
        }

        if ($this->sysConfig->setValue("dbBackupKeepLast", $dbBackupKeepLast)) {
            $changes++;
        }

        // Purges
        try {
            $adminLogsPurge = $this->input()->getInt("adminLogsPurge", unSigned: true);
            if (!$adminLogsPurge || $adminLogsPurge < 30) {
                throw new AdminAPIException('Minimum value is 30 days');
            } elseif ($adminLogsPurge > 180) {
                throw new AdminAPIException('Maximum value cannot exceed 180 days');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("adminLogsPurge");
            throw $e;
        }

        if ($this->sysConfig->setValue("adminLogsPurge", $adminLogsPurge)) {
            $changes++;
        }

        try {
            $adminSessionsPurge = $this->input()->getInt("adminSessionsPurge", unSigned: true);
            if (!$adminSessionsPurge || $adminSessionsPurge < 7) {
                throw new AdminAPIException('Minimum value is 7 days');
            } elseif ($adminSessionsPurge > 180) {
                throw new AdminAPIException('Maximum value cannot exceed 180 days');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("adminSessionsPurge");
            throw $e;
        }

        if ($this->sysConfig->setValue("adminSessionsPurge", $adminSessionsPurge)) {
            $changes++;
        }

        try {
            $usersLogsPurge = $this->input()->getInt("usersLogsPurge", unSigned: true);
            if (!$usersLogsPurge || $usersLogsPurge < 30) {
                throw new AdminAPIException('Minimum value is 30 days');
            } elseif ($usersLogsPurge > 180) {
                throw new AdminAPIException('Maximum value cannot exceed 180 days');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("usersLogsPurge");
            throw $e;
        }

        if ($this->sysConfig->setValue("usersLogsPurge", $usersLogsPurge)) {
            $changes++;
        }

        try {
            $publicAPIQueriesPurge = $this->input()->getInt("publicAPIQueriesPurge", unSigned: true);
            if (!$publicAPIQueriesPurge || $publicAPIQueriesPurge < 1) {
                throw new AdminAPIException('Minimum value is 1 day');
            } elseif ($publicAPIQueriesPurge > 180) {
                throw new AdminAPIException('Maximum value cannot exceed 180 days');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("publicAPIQueriesPurge");
            throw $e;
        }

        if ($this->sysConfig->setValue("publicAPIQueriesPurge", $publicAPIQueriesPurge)) {
            $changes++;
        }

        try {
            $publicAPISessionsPurge = $this->input()->getInt("publicAPISessionsPurge", unSigned: true);
            if (!$publicAPISessionsPurge || $publicAPISessionsPurge < 7) {
                throw new AdminAPIException('Minimum value is 7 days');
            } elseif ($publicAPISessionsPurge > 180) {
                throw new AdminAPIException('Maximum value cannot exceed 180 days');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("publicAPISessionsPurge");
            throw $e;
        }

        if ($this->sysConfig->setValue("publicAPISessionsPurge", $publicAPISessionsPurge)) {
            $changes++;
        }

        // Changes?
        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->sysConfig->save();

            $this->adminLogEntry('System configuration updated', flags: ["config", "sys-config"]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Clear Cached
        try {
            SystemConfig::ClearCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $this->status(true);
        $this->response->set("config", $this->sysConfig);
    }
}
