<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\AppKernel;
use Comely\Database\Database;
use Comely\Utils\OOP\Traits\NoDumpTrait;
use Comely\Utils\OOP\Traits\NotCloneableTrait;
use Comely\Utils\OOP\Traits\NotSerializableTrait;

/**
 * Class Databases
 * @package App\Common\Kernel
 */
class Databases
{
    /** @var string */
    public const PRIMARY = "primary";
    /** @var string */
    public const API_LOG = "api_log";

    use NoDumpTrait;
    use NotCloneableTrait;
    use NotSerializableTrait;

    /** @var array */
    private array $dbs = [];

    /**
     * @param AppKernel $aK
     */
    public function __construct(private AppKernel $aK)
    {
    }

    /**
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function primary(): Database
    {
        return $this->get(self::PRIMARY);
    }

    /**
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function apiLogs(): Database
    {
        return $this->get(self::API_LOG);
    }

    /**
     * @param string $label
     * @return Database
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function get(string $label): Database
    {
        $label = strtolower($label);
        if (isset($this->dbs[$label])) {
            return $this->dbs[$label];
        }

        $mySqlRootPassword = $this->aK->config->env->mysqlRootPassword;
        $dbCred = $this->aK->config->db->get($label);
        if (!$dbCred) {
            throw new \UnexpectedValueException(sprintf('Database "%s" is not configured', $label));
        }

        if ($dbCred->driver === "mysql") {
            if ($dbCred->username() && !$dbCred->password() && $mySqlRootPassword) {
                $dbCred->login($dbCred->username(), $mySqlRootPassword);
            }
        }

        $db = new Database($dbCred);
        $this->dbs[$label] = $db;
        return $db;
    }

    /**
     * @param string $name
     * @param Database $db
     */
    public function append(string $name, Database $db): void
    {
        $this->dbs[strtolower($name)] = $db;
    }

    /**
     * @return array
     */
    public function getAllQueries(): array
    {
        $queries = [];

        /**
         * @var string $dbName
         * @var Database $dbInstance
         */
        foreach ($this->dbs as $dbName => $dbInstance) {
            foreach ($dbInstance->queries() as $query) {
                $queries[] = [
                    "db" => $dbName,
                    "query" => $query
                ];
            }
        }

        return $queries;
    }

    /**
     * @return int
     */
    public function flushAllQueries(): int
    {
        $flushed = 0;

        /**
         * @var string $name
         * @var Database $db
         */
        foreach ($this->dbs as $db) {
            $flushed += $db->queries()->count();
            $db->queries()->flush();
        }

        return $flushed;
    }
}
