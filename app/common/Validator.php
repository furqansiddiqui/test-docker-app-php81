<?php
declare(strict_types=1);

namespace App\Common;

/**
 * Class Validator
 * @package App\Common
 */
class Validator
{
    /**
     * @param mixed $port
     * @param int $min
     * @return bool
     */
    public static function isValidPort(mixed $port, int $min = 0x03e8): bool
    {
        return is_int($port) && $port >= $min && $port <= 0xffff;
    }

    /**
     * @param mixed $val
     * @return bool
     */
    public static function getBool(mixed $val): bool
    {
        if (is_bool($val)) {
            return $val;
        }

        if ($val === 1) {
            return true;
        }

        if (is_string($val) && in_array(strtolower($val), ["1", "true", "on", "yes"])) {
            return true;
        }

        return false;
    }
}
