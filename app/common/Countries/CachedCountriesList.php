<?php
declare(strict_types=1);

namespace App\Common\Countries {

    use App\Common\AppKernel;
    use App\Common\Countries\CachedCountriesList\CountryEntry;
    use App\Common\Database\Primary\Countries;
    use Comely\Cache\Exception\CacheException;
    use Comely\Database\Exception\ORM_ModelNotFoundException;

    /**
     * Class CachedCountriesList
     * @package App\Common\Countries
     */
    class CachedCountriesList
    {
        public const CACHE_TTL = 86400 * 7;

        /** @var int */
        public readonly int $count;
        /** @var array */
        public readonly array $countries;
        /** @var bool|null */
        public readonly ?bool $available;
        /** @var int */
        public readonly int $cachedOn;

        /**
         * @param bool $purgeAll
         * @param bool|null $availableOnly
         * @return void
         * @throws CacheException
         */
        public static function Purge(bool $purgeAll = false, ?bool $availableOnly = null): void
        {
            $aK = AppKernel::getInstance();
            $instances = [
                "countries_list_all",
                "countries_list_av1",
                "countries_list_av0"
            ];

            if (!$purgeAll) {
                $instances = [$instances[match ($availableOnly) {
                    true => 1,
                    false => 2,
                    default => 0
                }]];
            }

            foreach ($instances as $instanceId) {
                $aK->cache->delete($instanceId);
            }
        }

        /**
         * @param bool $useCache
         * @param bool|null $availableOnly
         * @return static
         * @throws \Comely\Database\Exception\ORM_Exception
         * @throws \Comely\Database\Exception\SchemaTableException
         */
        public static function getInstance(bool $useCache = true, ?bool $availableOnly = null): static
        {
            $aK = AppKernel::getInstance();
            $instanceId = match ($availableOnly) {
                true => "countries_list_av1",
                false => "countries_list_av0",
                default => "countries_list_all"
            };

            if ($useCache) {
                try {
                    $cache = $aK->cache;
                    $cachedList = $cache->get($instanceId);
                } catch (CacheException $e) {
                    $aK->errors->triggerIfDebug($e, E_USER_WARNING);
                }

                if (isset($cachedList) && $cachedList instanceof static) {
                    if ((time() - $cachedList->cachedOn) < static::CACHE_TTL) {
                        return $cachedList; // Return cached list
                    }

                    // Expired or Invalid
                    try {
                        $cache->delete($instanceId);
                    } catch (CacheException) {
                    }

                    unset($cachedList);
                }
            }

            $countriesList = new static($availableOnly);

            if (isset($cache)) {
                try {
                    $cache->set($instanceId, $countriesList);
                } catch (CacheException $e) {
                    $aK->errors->triggerIfDebug($e, E_USER_WARNING);
                }
            }

            return $countriesList;
        }

        /**
         * @param bool|null $availableOnly
         * @throws \Comely\Database\Exception\ORM_Exception
         * @throws \Comely\Database\Exception\SchemaTableException
         */
        public function __construct(?bool $availableOnly = null)
        {
            if (!is_bool($availableOnly)) {
                $whereQuery = 'WHERE 1';
                $whereData = [];
            } else {
                $whereQuery = 'WHERE `available`=?';
                $whereData[] = (int)$availableOnly;
            }

            $whereQuery .= ' ORDER BY `name` ASC';

            try {
                $countriesModels = Countries::Find()->query($whereQuery, $whereData)->all();
            } catch (ORM_ModelNotFoundException) {
            }

            $countriesList = [];
            $count = 0;
            if (isset($countriesModels) && is_array($countriesModels)) {
                /** @var Country $countryModel */
                foreach ($countriesModels as $countryModel) {
                    $countriesList[strtoupper($countryModel->codeShort)] = new CountryEntry($countryModel);
                    $count++;
                }
            }

            $this->count = $count;
            $this->countries = $countriesList;
            $this->available = $availableOnly;
            $this->cachedOn = time();
        }

        /**
         * @return array
         */
        public function __serialize(): array
        {
            return [$this->count, $this->countries, $this->available, $this->cachedOn];
        }

        /**
         * @param array $data
         * @return void
         */
        public function __unserialize(array $data): void
        {
            list($this->count, $this->countries, $this->available, $this->cachedOn) = $data;
        }
    }
}

namespace App\Common\Countries\CachedCountriesList {

    use App\Common\Countries\Country;

    /**
     * Class CountryEntry
     * @package App\Common\Countries\CachedCountriesList
     */
    class CountryEntry
    {
        /** @var string */
        public readonly string $name;
        /** @var string */
        public readonly string $code;
        /** @var string */
        public readonly string $codeShort;
        /** @var int|null */
        public readonly ?int $dialCode;

        /**
         * @param Country $country
         */
        public function __construct(Country $country)
        {
            $this->name = $country->name;
            $this->code = $country->code;
            $this->codeShort = $country->codeShort;
            $this->dialCode = $country->dialCode;
        }

        /**
         * @return array
         */
        public function __serialize(): array
        {
            return [$this->name, $this->code, $this->codeShort, $this->dialCode];
        }

        /**
         * @param array $data
         * @return void
         */
        public function __unserialize(array $data): void
        {
            list($this->name, $this->code, $this->codeShort, $this->dialCode) = $data;
        }
    }
}
