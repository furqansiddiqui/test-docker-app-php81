<?php
declare(strict_types=1);

namespace App\Common\Database;

use App\Common\Database\Primary\DbBackups;

/**
 * Class DbBackup
 * @package App\Common\Database
 */
class DbBackup extends AbstractAppModel
{
    public const TABLE = DbBackups::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $db;
    /** @var int */
    public int $epoch;
    /** @var string */
    public string $filename;
    /** @var int */
    public int $size;
}
