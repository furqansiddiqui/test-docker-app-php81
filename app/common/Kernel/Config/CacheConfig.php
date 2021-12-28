<?php
declare(strict_types=1);

namespace App\Common\Kernel\Config;

use App\Common\Exception\AppConfigException;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\Validator\Exception\ValidatorException;
use Comely\Utils\Validator\Validator;

/**
 * Class CacheConfig
 * @package App\Common\Kernel\Config
 */
class CacheConfig
{
    /** @var string|null */
    public readonly ?string $engine;
    /** @var string */
    public readonly string $host;
    /** @var int */
    public readonly int $port;
    /** @var int */
    public readonly int $timeOut;

    use NoDumpTrait;

    /**
     * @param array $config
     * @throws AppConfigException
     */
    public function __construct(array $config)
    {
        $engine = $config["engine"] ?? null;
        if (!is_null($engine)) {
            if (strtolower(trim(strval($engine))) !== "redis") {
                throw new AppConfigException('Cache[engine]: Invalid caching engine');
            }
        }

        $this->engine = $engine;

        // Validators
        $hostnameValidator = Validator::ASCII()->setCustomFn(function (string $hostname) {
            return \App\Common\Validator::isValidHostname($hostname, allowIpAddr: true) ? $hostname : false;
        });

        $portValidator = Validator::Integer()->range(1000, 0xffff);

        // Hostname/IP Addr
        try {
            $this->host = $hostnameValidator->getValidated($config["host"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Cache[host]: Invalid hostname (0x%s)', dechex($e->getCode())));
        }

        // Port
        try {
            $this->port = $portValidator->getValidated($config["port"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Cache[port]: Invalid port (0x%s)', dechex($e->getCode())));
        }

        // Timeout
        try {
            $this->timeOut = Validator::Integer()->range(1, 30)->getValidated($config["time_out"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Cache[time_out]: Invalid timeout value (0x%s)', dechex($e->getCode())));
        }
    }
}
