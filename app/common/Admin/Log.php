<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Admin\Logs;

/**
 * Class Log
 * @package App\Common\Admin
 */
class Log extends AbstractAppModel
{
    public const TABLE = Logs::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var int */
    public int $admin;
    /** @var string|null */
    public ?string $flags;
    /** @var string|null */
    public ?string $controller;
    /** @var string */
    public string $log;
    /** @var string */
    public string $ipAddress;
    /** @var int */
    public int $timeStamp;
}
