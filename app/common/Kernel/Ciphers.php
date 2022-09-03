<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\AppKernel;
use App\Common\Exception\AppException;
use Comely\Security\Cipher;
use Comely\Security\Exception\CipherException;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Ciphers
 * @package App\Common\Kernel
 */
class Ciphers
{
    /** @var array */
    private array $ciphers = [];

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /**
     * @param AppKernel $aK
     */
    public function __construct(private readonly AppKernel $aK)
    {
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function primary(): Cipher
    {
        return $this->get("primary");
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function secondary(): Cipher
    {
        return $this->get("secondary");
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function users(): Cipher
    {
        return $this->get("users");
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function project(): Cipher
    {
        return $this->get("project");
    }

    /**
     * @return Cipher
     * @throws AppException
     */
    public function misc(): Cipher
    {
        return $this->get("misc");
    }

    /**
     * @param string $key
     * @return Cipher
     * @throws AppException
     */
    public function get(string $key): Cipher
    {
        $key = strtolower($key);
        if (array_key_exists($key, $this->ciphers)) {
            return $this->ciphers[$key];
        }

        $entropy = $this->aK->config->cipher->get($key);
        if (!$entropy) {
            throw new AppException(sprintf('Cipher key "%s" does not exist', $key));
        }

        try {
            $cipher = new Cipher($entropy);
        } catch (CipherException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to instantiate "%s" cipher', $key));
        }

        $this->ciphers[$key] = $cipher;
        return $cipher;
    }
}
