<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users\Groups;

/**
 * Class Group
 * @package App\Common\Users
 */
class Group extends AbstractAppModel
{
    public const TABLE = Groups::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $id;
    /** @var string */
    public string $name;
    /** @var int */
    public int $usersCount = 0;
}
