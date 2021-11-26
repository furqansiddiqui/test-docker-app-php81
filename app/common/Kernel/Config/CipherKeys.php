<?php
declare(strict_types=1);

namespace App\Common\Kernel\Config;

use App\Common\Exception\AppConfigException;
use Comely\Utils\OOP\Traits\NoDumpTrait;

/**
 * Class CipherKeys
 * @package App\Common\Kernel\Config
 */
class CipherKeys
{
    /** @var string */
    public const DEFAULT_KEY = "enter some random words or PRNG entropy here";

    /** @var array */
    private array $keys = [];

    use NoDumpTrait;

    /**
     * @param array $keys
     * @throws AppConfigException
     */
    public function __construct(array $keys)
    {
        $defaultEntropy = hash("sha256", self::DEFAULT_KEY, true);

        $index = -1;
        foreach ($keys as $label => $entropy) {
            $index++;
            if (!preg_match('/^\w{2,16}$/', $label)) {
                throw new AppConfigException(sprintf('Invalid cipher key label at index %d', $index));
            }

            if (!is_string($entropy) || !$entropy) {
                throw new AppConfigException(sprintf('Invalid entropy for cipher key "%s"', $label));
            }

            if (!preg_match('/^[a-f0-9]{64}$/i', $entropy)) {
                $entropy = hash("sha256", $entropy, true);
            }

            if ($entropy === $defaultEntropy) {
                throw new AppConfigException(sprintf('Entropy for cipher key "%s" is insecure', $label));
            }

            $this->keys[strtolower($label)] = $entropy;
        }
    }

    /**
     * @param string $cipher
     * @return string|null
     */
    public function get(string $cipher): ?string
    {
        return $this->keys[strtolower($cipher)] ?? null;
    }
}
