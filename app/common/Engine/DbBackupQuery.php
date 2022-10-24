<?php
declare(strict_types=1);

namespace App\Common\Engine;

/**
 * Class DbBackupQuery
 * @package App\Common\Engine
 */
class DbBackupQuery extends AbstractDaemonQuery
{
    /**
     * @param string $dbName
     */
    public function __construct(public readonly string $dbName)
    {
    }
}
