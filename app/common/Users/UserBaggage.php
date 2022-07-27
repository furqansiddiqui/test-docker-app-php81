<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\AppKernel;
use App\Common\Database\Primary\Users\Baggage;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Validator;
use Comely\Cache\Exception\CacheException;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\QueryExecuteException;

/**
 * Class UserBaggage
 * @package App\Common\Users
 */
class UserBaggage
{
    /** @var AppKernel */
    private AppKernel $aK;
    /** @var int */
    public readonly int $userId;

    /**
     * @param User|int $user
     */
    public function __construct(User|int $user)
    {
        $this->aK = AppKernel::getInstance();
        $this->userId = $user instanceof User ? $user->id : $user;
    }

    /**
     * @return array
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function list(): array
    {
        $query = $this->aK->db->primary()->query()
            ->table(Baggage::TABLE)
            ->where("`user`=?", [$this->userId])
            ->asc("key")
            ->fetch();

        return $query->count() > 0 ? $query->all() : [];
    }

    /**
     * @param string $key
     * @param bool $useCache
     * @param int|null $cacheTTL
     * @return string
     * @throws AppException
     * @throws AppModelNotFoundException
     * @throws DatabaseException
     */
    public function get(string $key, bool $useCache = true, ?int $cacheTTL = Baggage::CACHE_TTL): string
    {
        $key = trim(strtolower($key));
        if (!$key) {
            throw new AppException('Invalid key for user baggage data');
        }

        if ($useCache) {
            $cache = $this->aK->cache;
            $cacheKey = sprintf("ub%d_%s", $this->userId, $key);

            try {
                $cached = $cache->get($cacheKey);
            } catch (CacheException) {
            }
        }

        if (isset($cached)) {
            return $cached; // Return the value found from cache
        }

        // Check in DB
        $db = $this->aK->db->primary();
        $queryStr = 'SELECT ' . '* FROM `%s` WHERE `user`=? AND `key`=?';
        $fetched = $db->fetch(sprintf($queryStr, Baggage::TABLE), [$this->userId, $key])->next();
        if (!is_array($fetched)) {
            throw new AppModelNotFoundException(
                sprintf('User %d baggage field value "%s" does not exist', $this->userId, $key)
            );
        }

        $value = (string)$fetched["value"];
        if (isset($cache, $cacheKey) && $cacheTTL > 0) {
            try {
                $cache->set($cacheKey, $value, $cacheTTL);
            } catch (CacheException $e) {
                $this->aK->errors->trigger($e, E_USER_WARNING);
            }
        }

        return $value;
    }

    /**
     * @param string $key
     * @param bool $useCache
     * @param int|null $cacheTTL
     * @return int
     * @throws AppException
     * @throws AppModelNotFoundException
     * @throws DatabaseException
     */
    public function getInt(string $key, bool $useCache = true, ?int $cacheTTL = Baggage::CACHE_TTL): int
    {
        $str = $this->get($key, $useCache, $cacheTTL);
        if (preg_match('/^-?\d+$/', $str)) {
            throw new AppException(sprintf('Cannot retrieve value for "%s" as integer', $key));
        }

        return intval($str);
    }

    /**
     * @param string $key
     * @param bool $useCache
     * @param int|null $cacheTTL
     * @return bool
     * @throws AppException
     * @throws AppModelNotFoundException
     * @throws DatabaseException
     */
    public function getBool(string $key, bool $useCache = true, ?int $cacheTTL = Baggage::CACHE_TTL): bool
    {
        return Validator::getBool($this->get($key, $useCache, $cacheTTL));
    }

    /**
     * @param string $key
     * @return int
     * @throws AppException
     * @throws QueryExecuteException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function delete(string $key): int
    {
        $key = trim(strtolower($key));
        if (!$key) {
            throw new AppException('Invalid key for user baggage data');
        }

        $query = $this->aK->db->primary()->exec(
            sprintf('DELETE ' . 'FROM `%s` WHERE `user`=? AND `key`=?', Baggage::TABLE),
            [
                $this->userId,
                $key
            ]
        );

        return $query->rows();
    }

    /**
     * @param string $key
     * @param string|int|bool $value
     * @param int $cacheTTL
     * @return void
     * @throws AppException
     * @throws QueryExecuteException
     * @throws \Comely\Cache\Exception\CacheException
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function set(string $key, string|int|bool $value, int $cacheTTL = Baggage::CACHE_TTL): void
    {
        $key = trim(strtolower($key));
        if (!$key) {
            throw new AppException('Invalid key for user baggage data');
        }

        $cacheKey = sprintf("ub%d_%s", $this->userId, $key);
        if (is_string($value) && strlen($value) > 1024) {
            throw new AppException(sprintf('User baggage data for key "%s" exceeds limit of 1024 bytes', $cacheKey));
        }

        if (is_bool($value)) {
            $value = $value ? "true" : "false";
        }

        if (is_int($value)) {
            $value = (string)$value;
        }

        $db = $this->aK->db->primary();
        $queryStr = 'INSERT ' . 'INTO `%s` (`user`, `key`, `data`) VALUES (:user, :key, :data) ON DUPLICATE KEY UPDATE ' .
            '`data`=:data';

        try {
            $db->exec(sprintf($queryStr, Baggage::TABLE), [
                "user" => $this->userId,
                "key" => $key,
                "data" => $value
            ]);
        } catch (QueryExecuteException $e) {
            if ($e->error()) {
                $this->aK->errors->triggerIfDebug($e->queryString(), E_USER_WARNING);
            }

            throw $e;
        }

        if ($cacheTTL > 0) {
            $this->aK->cache->set($cacheKey, $value, $cacheTTL);
        }
    }
}
