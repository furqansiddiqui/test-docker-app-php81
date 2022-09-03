<?php
declare(strict_types=1);

namespace App\Common\DataStore;

use App\Common\AppKernel;
use App\Common\Database\Primary\DataStore;
use App\Common\Exception\AppException;
use Comely\Buffer\Buffer;
use Comely\Security\Exception\CipherException;

/**
 * Class AbstractDataStoreObject
 * @property int $cachedOn
 * @method void beforeSave()
 * @package App\Common\DataStore
 */
abstract class AbstractDataStoreObject
{
    /** @var array */
    private static array $instances = [];

    /** @var null|string */
    public const DB_KEY = null;
    /** @var null|string */
    public const CACHE_KEY = null;
    /** @var null|int */
    public const CACHE_TTL = null;
    /** @var bool */
    public const IS_ENCRYPTED = false;

    /**
     * @param bool $useCache
     * @return static
     * @throws AppException
     */
    public static function getInstance(bool $useCache = true): self
    {
        $instanceId = static::class;
        if (isset(static::$instances[$instanceId])) {
            return static::$instances[$instanceId];
        }

        $aK = AppKernel::getInstance();

        if ($useCache && is_string(static::CACHE_KEY)) {
            try {
                $cache = $aK->cache;
                $dataStoreObject = $cache->get(static::CACHE_KEY);
            } catch (\Exception) {
            }
        }

        if (isset($dataStoreObject) && $dataStoreObject instanceof static) {
            static::$instances[$instanceId] = $dataStoreObject;
            return static::$instances[$instanceId];
        }

        if (!is_string(static::DB_KEY)) {
            throw new \UnexpectedValueException(sprintf('Invalid DataStore object "%s" DB key', $instanceId));
        }

        // Fetch from database
        try {
            $db = $aK->db->primary();
            $query = $db->query()->table(DataStore::TABLE)
                ->where('`key`=?', [static::DB_KEY])
                ->limit(1)
                ->fetch()
                ->row();

            if (!$query || !isset($query["data"]) || !is_string($query["data"])) {
                throw new AppException(sprintf('DataStore object "%s" not found in DB', static::DB_KEY));
            }

            $bytes = new Buffer($query["data"]);
            $dataStoreObject = static::IS_ENCRYPTED ?
                $aK->ciphers->project()->decrypt($bytes) : unserialize($bytes->raw());

            if (!$dataStoreObject instanceof static) {
                throw new AppException(sprintf('Failed to unserialize "%s" DataStore object', static::DB_KEY));
            }
        } catch (AppException $e) {
            throw $e;
        } catch (\Exception $e) {
            $aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AppException(sprintf('Failed to retrieve "%s" DataStore object', static::DB_KEY));
        }

        // Store in cache?
        if (isset($cache)) {
            try {
                $clonedObject = clone $dataStoreObject;
                $clonedObject->cachedOn = time();
                $cache->set(static::CACHE_KEY, $clonedObject, static::CACHE_TTL);
            } catch (\Exception $e) {
                $aK->errors->triggerIfDebug($e, E_USER_WARNING);
                trigger_error(sprintf('Failed to store "%s" DataStore object in cache', static::DB_KEY), E_USER_WARNING);
            }
        }

        static::$instances[$instanceId] = $dataStoreObject;
        return static::$instances[$instanceId];
    }

    /**
     * @return array
     */
    public function __debugInfo(): array
    {
        return [static::class];
    }

    /**
     * Use this method to set/change property values, returns TRUE if new value is different from previous one
     * @param string $prop
     * @param mixed $val
     * @param bool $checkUnInitProp
     * @return bool
     * @throws AppException
     */
    public function setValue(string $prop, mixed $val, bool $checkUnInitProp = false): bool
    {
        if (!property_exists($this, $prop)) {
            throw new AppException(sprintf('Prop "%s" does not exist in class "%s"', $prop, static::class));
        }

        if ($checkUnInitProp) {
            $change = true;
            if (isset($this->$prop)) {
                $change = $this->$prop !== $val;
            }

            if ($change) {
                $this->$prop = $val;
            }

            return $change;
        }

        if ($this->$prop !== $val) {
            $this->$prop = $val;
            return true;
        }

        return false;
    }

    /**
     * @return void
     * @throws AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public function save(): void
    {
        if (static::IS_ENCRYPTED) {
            try {
                if (method_exists($this, 'beforeSave')) {
                    $this->beforeSave();
                }

                $bytes = AppKernel::getInstance()->ciphers->project()->encrypt($this);
            } catch (CipherException) {
                throw new AppException(sprintf('Failed to encrypt "%s" DataStore object', static::class));
            }
        } else {
            $bytes = new Buffer(serialize($this));
        }

        DataStore::Save(static::DB_KEY, $bytes);
    }
}
