<?php
declare(strict_types=1);

namespace App\Common;

/**
 * Class Security
 * @package App\Common
 */
class Security
{
    /** @var int Minimum number of PBKDF2 iterations */
    private const MINIMUM_ITERATIONS = 0x3e9;
    /** @var int Maximum number of iterations per final HASH value affordable by CPU */
    private const CPU_MAX_HASH_COST = 0x3e80;

    /**
     * Find a different PBKDF2 iterations value for each individual row, but stay with in the CPU budget
     * @param int $rowId
     * @param string $tableName
     * @return int
     */
    public static function PBKDF2_Iterations(int $rowId, string $tableName): int
    {
        if (!$tableName) {
            throw new \RuntimeException('Second argument cannot be empty');
        }

        $itr = static::MINIMUM_ITERATIONS + ord($tableName[0]) + $rowId;
        if ($itr > static::CPU_MAX_HASH_COST) {
            while (true) {
                $itr -= static::CPU_MAX_HASH_COST;
                if ($itr <= static::CPU_MAX_HASH_COST) {
                    break;
                }
            }
        }

        if ($itr < static::MINIMUM_ITERATIONS) {
            $itr += static::MINIMUM_ITERATIONS;
        }

        return $itr;
    }
}
