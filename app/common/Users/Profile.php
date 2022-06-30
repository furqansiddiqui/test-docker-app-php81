<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users\Profiles;

/**
 * Class Profile
 * @package App\Common\Users
 */
class Profile extends AbstractAppModel
{
    public const TABLE = Profiles::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $userId;
    /** @var int */
    public int $isVerified = 0;
    /** @var string */
    public string $address1;
    /** @var string */
    public string $address2;
    /** @var string */
    public string $postalCode;
    /** @var string */
    public string $city;
    /** @var string */
    public string $country;
}
