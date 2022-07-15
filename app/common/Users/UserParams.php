<?php
declare(strict_types=1);

namespace App\Common\Users;

/**
 * Class UserParams
 * @package App\Common\Users
 */
class UserParams
{
    /** @var int */
    public readonly int $userId;
    /** @var string|null */
    private ?string $secureData = null;

    /**
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->userId = $user->id;
    }

    /**
     * @param string|null $data
     * @return bool
     */
    public function setSecureData(?string $data = null): bool
    {
        if ($data === $this->secureData) {
            return false;
        }

        $this->secureData = $data;
        return true;
    }

    /**
     * @return string|null
     */
    public function getSecureData(): ?string
    {
        return $this->secureData;
    }
}
