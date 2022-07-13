<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Packages\GoogleAuth\GoogleAuthenticator;

/**
 * Class Credentials
 * @package App\Common\Users
 */
class Credentials
{
    /** @var int */
    public readonly int $userId;
    /** @var string|null */
    private ?string $password = null;
    /** @var string|null */
    private ?string $googleAuthSeed = null;

    /**
     * @param User $user
     */
    public function __construct(User $user)
    {
        $this->userId = $user->id;
    }

    /**
     * @param string $password
     * @return void
     */
    public function changePassword(string $password): void
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
    }

    /**
     * @param string $input
     * @return bool
     */
    public function verifyPassword(string $input): bool
    {
        if (!isset($this->password)) {
            return false;
        }

        return password_verify($input, $this->password);
    }

    /**
     * @param string|null $seed
     * @return void
     */
    public function changeGoogleAuthSeed(?string $seed): void
    {
        $this->googleAuthSeed = $seed;
    }

    /**
     * @return string|null
     */
    public function getGoogleAuthSeed(): ?string
    {
        return $this->googleAuthSeed;
    }

    /**
     * @param string $code
     * @return bool
     */
    public function verifyTotp(string $code): bool
    {
        if (!$code || !$this->googleAuthSeed) {
            return false;
        }

        $gA = new GoogleAuthenticator($this->googleAuthSeed);
        return $gA->verify($code);
    }
}
