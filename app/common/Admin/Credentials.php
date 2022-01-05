<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\Packages\GoogleAuth\GoogleAuthenticator;
use Comely\Utils\OOP\Traits\NoDumpTrait;

/**
 * Class Credentials
 * @package App\Common\Admin
 */
class Credentials
{
    /** @var int */
    public readonly int $adminId;
    /** @var string|null */
    private ?string $password = null;
    /** @var string|null */
    private ?string $googleAuthSeed = null;

    use NoDumpTrait;

    /**
     * @param Administrator $admin
     */
    public function __construct(Administrator $admin)
    {
        $this->adminId = $admin->id;
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
        if (!$this->password) {
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
