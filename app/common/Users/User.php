<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;

/**
 * Class User
 * @package App\Common\Users
 */
class User extends AbstractAppModel
{
    public const TABLE = Users::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $id;
    /** @var int */
    public int $groupId;
    /** @var int */
    public int $archived;
    /** @var string */
    public string $status;
    /** @var string */
    public string $username;
    /** @var string|null */
    public ?string $email = null;
    /** @var int */
    public int $emailVerified = 0;
    /** @var string|null */
    public ?string $phone = null;
    /** @var int */
    public int $phoneVerified = 0;
    /** @var string */
    public string $firstName;
    /** @var string */
    public string $lastName;
    /** @var string|null */
    public ?string $country = null;
    /** @var int */
    public int $createdOn;
    /** @var int */
    public int $updatedOn;
}
