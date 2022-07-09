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
     * @param string $data
     * @return $this
     */
    public function setSecureData(string $data): static
    {
        $this->secureData = $data;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getSecureData(): ?string
    {
        return $this->secureData;
    }
}
