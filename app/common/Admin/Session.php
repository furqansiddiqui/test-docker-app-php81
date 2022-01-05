<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Admin\Sessions;

/**
 * Class Session
 * @package App\Common\Admin
 */
class Session extends AbstractAppModel
{
    public const TABLE = Sessions::TABLE;
    public const SERIALIZABLE = false;

    /** @var int */
    public int $id;
    /** @var string */
    public string $type;
    /** @var int */
    public int $adminId;
    /** @var string|null */
    public ?string $last2faCode = null;
    /** @var int|null */
    public ?int $last2faOn = null;
    /** @var int */
    public int $issuedOn;
    /** @var int */
    public int $lastUsedOn;
}
