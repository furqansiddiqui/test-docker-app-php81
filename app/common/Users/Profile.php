<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users\Profiles;

/**
 * Class Profile
 * @package App\Common\Users
 * @property bool $isRegistered
 */
class Profile extends AbstractAppModel
{
    public const TABLE = Profiles::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $userId;
    /** @var int */
    public int $isVerified = 0;
    /** @var string|null */
    public ?string $address1 = null;
    /** @var string|null */
    public ?string $address2 = null;
    /** @var string|null */
    public ?string $postalCode = null;
    /** @var string|null */
    public ?string $city = null;
    /** @var string|null */
    public ?string $country = null;

    /**
     * @return void
     */
    public function beforeQuery(): void
    {
        $this->isVerified = $this->isVerified === 1 ? 1 : 0;
    }

    /**
     * @return void
     * @throws \Comely\Cache\Exception\CacheException
     */
    public function deleteCached(): void
    {
        $this->aK->cache->delete(sprintf("u_prf_%d", $this->userId));
    }
}
