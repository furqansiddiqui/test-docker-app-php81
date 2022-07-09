<?php
declare(strict_types=1);

namespace App\Common\Database\Primary;

use App\Common\AppKernel;
use App\Common\Countries\Country;
use App\Common\Database\AbstractAppTable;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema\Table\Columns;
use Comely\Database\Schema\Table\Constraints;

/**
 * Class Countries
 * @package App\Common\Database\Primary
 */
class Countries extends AbstractAppTable
{
    public const TABLE = "countries";
    public const ORM_CLASS = 'App\Common\Countries\Country';
    public const CACHE_TTL = 86400;

    /**
     * @param Columns $cols
     * @param Constraints $constraints
     * @return void
     */
    public function structure(Columns $cols, Constraints $constraints): void
    {
        $cols->defaults("ascii", "ascii_general_ci");

        $cols->int("available")->bytes(1)->default(0);
        $cols->string("name")->length(32);
        $cols->string("code")->fixed(3)->unique();
        $cols->string("code_short")->fixed(2)->unique();
        $cols->int("dial_code")->bytes(3)->unSigned()->nullable();
    }

    /**
     * @param string $code
     * @param bool $useCache
     * @return Country
     * @throws AppException
     * @throws AppModelNotFoundException
     */
    public static function Get(string $code, bool $useCache = true): Country
    {
        if (strlen($code) !== 3) {
            throw new \RuntimeException('Invalid country code');
        }

        $aK = AppKernel::getInstance();

        try {
            $instanceId = sprintf("cntr_%s", strtolower($code));
            $qM = $aK->memory->query($instanceId, self::ORM_CLASS);
            if ($useCache) {
                $qM->cache(static::CACHE_TTL);
            }

            return $qM->fetch(function () use ($code) {
                return self::Find()->col("code", $code)->limit(1)->first();
            });
        } catch (\Exception $e) {
            if ($e instanceof ORM_ModelNotFoundException) {
                throw new AppModelNotFoundException(sprintf('Selected country %s does not exist', strtoupper($code)));
            }

            $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException('Failed to retrieve country');
        }
    }
}
