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

    /**
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->userId = $user->id;
    }
}
