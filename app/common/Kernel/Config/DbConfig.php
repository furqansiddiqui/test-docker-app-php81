<?php
declare(strict_types=1);

namespace App\Common\Kernel\Config;

use App\Common\Exception\AppConfigException;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Server\DbCredentials;
use Comely\Database\Server\DbDrivers;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\Validator\Exception\ValidatorException;
use Comely\Utils\Validator\Validator;

/**
 * Class DbConfig
 * @package App\Common\Kernel\Config
 */
class DbConfig
{
    /** @var array */
    private array $dbs = [];

    use NoDumpTrait;

    /**
     * @param array $config
     * @throws AppConfigException
     */
    public function __construct(array $config)
    {
        // Validators
        $labelValidator = Validator::ASCII()->lowerCase()->trim()->match('/^\w{3,32}$/');
        $hostnameValidator = Validator::ASCII()->setCustomFn(function (string $hostname) {
            return \App\Common\Validator::isValidHostname($hostname, allowIpAddr: true) ? $hostname : false;
        });

        $portValidator = Validator::Integer()->range(1000, 0xffff);
        $strValidator = Validator::ASCII();

        // Iterate through configured databases
        $i = -1;
        foreach ($config as $label => $args) {
            unset($dbCred, $driver, $hostname, $port, $name, $username, $password);
            $i++;

            try {
                $label = $labelValidator->getValidated($label);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[Index:%d]: Invalid label for database (0x%s)', $i, dechex($e->getCode())));
            }

            if (!is_array($args)) {
                throw new AppConfigException(sprintf('Db[%s]: Expected credentials object, got "%s"', $label, gettype($args)));
            }

            // Database credentials check
            try {
                $driver = DbDrivers::from(strval($args["driver"]));
            } catch (\Error) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid/unsupported driver', $label));
            }

            try {
                $hostname = $hostnameValidator->getValidated($args["host"]);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid hostname (0x%s)', $label, dechex($e->getCode())));
            }

            try {
                $port = $portValidator->getNullable($args["port"], zeroIsNull: true);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid hostname (0x%s)', $label, dechex($e->getCode())));
            }

            try {
                $name = $strValidator->match('/^[\w\-\.]{3,32}$/')->getValidated($args["name"]);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid db name (0x%s)', $label, dechex($e->getCode())));
            }

            try {
                $dbCred = new DbCredentials($driver, $name, $hostname, $port);
            } catch (DatabaseException $e) {
                throw new AppConfigException(sprintf('Db[%s]: %s', $label, $e->getMessage()));
            }

            try {
                $username = $strValidator->match('/^\w{3,32}$/')->getNullable($args["username"], true);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid username (0x%s)', $label, dechex($e->getCode())));
            }

            try {
                $password = $strValidator->match('/^[a-z0-9@#~_\-.$^&*()!]{4,32}$/i')->getNullable($args["password"], true);
            } catch (ValidatorException $e) {
                throw new AppConfigException(sprintf('Db[%s]: Invalid password (0x%s)', $label, dechex($e->getCode())));
            }

            if ($username) {
                $dbCred->login($username, $password);
            }

            $this->dbs[$label] = $dbCred;
        }
    }

    /**
     * @param string $label
     * @return DbCredentials|null
     */
    public function get(string $label): ?DbCredentials
    {
        return $this->dbs[strtolower($label)] ?? null;
    }

    /**
     * @return array
     */
    public function getAll(): array
    {
        return $this->dbs;
    }
}
