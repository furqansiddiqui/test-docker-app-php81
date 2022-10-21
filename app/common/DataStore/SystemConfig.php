<?php
declare(strict_types=1);

namespace App\Common\DataStore;

/**
 * Class SystemConfig
 * @package App\Common\DataStore
 */
class SystemConfig extends AbstractDataStoreObject
{
    public const DB_KEY = "app.systemConfig";
    public const CACHE_KEY = "app.systemConfig";
    public const CACHE_TTL = 86400 * 7;
    public const IS_ENCRYPTED = true;

    /** @var bool */
    public bool $autoDbBackup = false;
    /** @var int Auto backup primary DB every N hours */
    public int $autoDbBackupHours = 1;
    /** @var string|null Password-protected zip archives? */
    public ?string $dbBackupPassword = null;
    /** @var int Keep last N backups, purge the rest */
    public int $dbBackupKeepLast = 24;
    /** @var int Purge admin logs older than N days */
    public int $adminLogsPurge = 30;
    /** @var int Purge admin sessions older than N days */
    public int $adminSessionsPurge = 7;
    /** @var int Purge users logs older than N days */
    public int $usersLogsPurge = 30;
    /** @var int Purge public API queries older than N days */
    public int $publicAPIQueriesPurge = 30;
    /** @var int Purge public API sessions older than N days */
    public int $publicAPISessionsPurge = 30;
}
