<?php
declare(strict_types=1);

namespace App\Common\Kernel\Docker {

    use App\Common\AppKernel;
    use App\Common\Exception\DockerConfigException;
    use App\Common\Kernel\Docker\DockerConfig\DockerConfigNetwork;
    use App\Common\Kernel\Docker\DockerConfig\DockerConfigService;
    use App\Common\Validator;
    use Comely\Cache\Exception\CacheException;
    use Comely\Utils\OOP\OOP;
    use Comely\Yaml\Yaml;

    /**
     * Class DockerConfig
     * @property int $cachedOn
     * @package App\Common\Kernel\Docker
     */
    class DockerConfig
    {
        public const CACHE_KEY = "app_dockerConfigReflect";
        public const CACHE_EOL = 86400;

        /** @var self |null */
        private static ?self $instance = null;

        /**
         * @param AppKernel $aK
         * @param bool $useCache
         * @return static
         * @throws DockerConfigException
         * @throws \Comely\Filesystem\Exception\PathException
         * @throws \Comely\Yaml\Exception\ParserException
         */
        public static function getInstance(AppKernel $aK, bool $useCache = true): self
        {
            if (static::$instance) {
                return static::$instance;
            }

            if ($useCache) {
                try {
                    $cache = $aK->cache;
                    $dockerConfig = $cache->get(self::CACHE_KEY);
                } catch (CacheException) {
                }
            }

            if (isset($dockerConfig) && $dockerConfig instanceof self) {
                if (isset($dockerConfig->cachedOn) && time() < ($dockerConfig->cachedOn + self::CACHE_EOL)) {
                    static::$instance = $dockerConfig;
                    return $dockerConfig;
                }
            }

            $dockerConfig = new self($aK);

            if (isset($cache)) {
                try {
                    $cloneConfig = clone $dockerConfig;
                    $cloneConfig->cachedOn = time();
                    $cache->set(self::CACHE_KEY, $cloneConfig);
                    unset($cloneConfig);
                } catch (CacheException $e) {
                    $aK->errors->triggerIfDebug($e, E_USER_WARNING);
                }
            }

            static::$instance = $dockerConfig;
            return $dockerConfig;
        }

        /** @var DockerConfigNetwork|null */
        public ?DockerConfigNetwork $network = null;
        /** @var array */
        public array $volumes = [];
        /** @var array */
        public array $services = [];

        /**
         * @param AppKernel $aK
         * @throws DockerConfigException
         * @throws \Comely\Filesystem\Exception\PathException
         * @throws \Comely\Yaml\Exception\ParserException
         */
        public function __construct(AppKernel $aK)
        {
            $config = [];

            // Read all docker-compose (.yml) files
            $files = $aK->dirs->root->dir("docker")->glob("*.yml");
            foreach ($files as $file) {
                $fileConfig = Yaml::Parse($file)->generate();
                $config = array_merge_recursive($fileConfig, $config);
            }

            // Network
            if (isset($config["networks"]) && is_array($config["networks"])) {
                foreach ($config["networks"] as $nId => $network) {
                    if (is_array($network)) {
                        $this->network = new DockerConfigNetwork();
                        $this->network->name = $nId;
                        $this->network->driver = $network["driver"] ?? "";

                        $nCsn = null;
                        $nC = $network["ipam"]["config"] ?? "";
                        if (is_array($nC)) {
                            foreach ($nC as $nCE) {
                                if (is_string($nCE) && preg_match('/^subnet:/i', $nCE)) {
                                    $nCsn = preg_replace('/[^0-9.\/]/', '', $nCE);
                                    break;
                                }
                            }
                        }

                        if (!$nCsn || !preg_match('/^[0-9]+(\.[0-9]+){3}\/[0-9]+$/', $nCsn)) {
                            throw new DockerConfigException('Could not retrieve docker subnet configuration');
                        }

                        $this->network->subnet = $nCsn;
                        break;
                    }
                }
            }

            // Volumes
            if (isset($config["volumes"]) && is_array($config["volumes"])) {
                foreach ($config["volumes"] as $volume => $vt) {
                    $this->volumes[] = $volume;
                }
            }

            // Services
            if (isset($config["services"]) && is_array($config["services"])) {
                foreach ($config["services"] as $sId => $sC) {
                    unset($service, $sCPin, $sCPout, $sCip);
                    $service = new DockerConfigService();
                    $service->id = $sId;

                    // IPv4
                    if ($this->network?->name) {
                        $sCip = $sC["networks"][$this->network->name]["ipv4_address"] ?? null;
                        if ($sCip && is_string($sCip) && preg_match('/^[0-9]+(\.[0-9]+){3}$/', $sCip)) {
                            $service->ipAddress = $sCip;
                        }
                    }

                    // Ports
                    if (isset($sC["ports"][0]) && is_string($sC["ports"][0]) && $sC["ports"][0]) {
                        preg_match('/:[0-9]+$/', $sC["ports"][0], $sCPin);
                        if ($sCPin) {
                            $service->internalPort = intval(trim(substr($sCPin[0], 1)));
                            $sCPout = trim(substr($sC["ports"][0], 0, -1 * strlen($sCPin[0])));
                            if ($sCPout) {
                                if (preg_match('/^[0-9]{2,5}$/i', $sCPout)) {
                                    $service->externalPort = intval($sCPout);
                                } elseif (preg_match('/^[0-9]+(\.[0-9]+){3}:[0-9]{2,5}$/', $sCPout)) {
                                    $service->externalPort = $sCPout;
                                } elseif (preg_match('/^\${\w+}$/i', $sCPout)) {
                                    $sCPout = OOP::camelCase(substr($sCPout, 2, -1));
                                    if (isset($aK->config->env->$sCPout)) {
                                        $service->externalPort = $aK->config->env->$sCPout;
                                    }
                                }
                            }
                        }
                    }

                    // Environments
                    if (isset($sC["environment"]) && is_array($sC["environment"])) {
                        if (isset($sC["environment"]["COMELY_APP_DEBUG"])) {
                            $service->appDebug = Validator::getBool($sC["environment"]["COMELY_APP_DEBUG"]);
                        }

                        if (isset($sC["environment"]["COMELY_APP_CACHED_CONFIG"])) {
                            $service->appCachedConfig = Validator::getBool($sC["environment"]["COMELY_APP_CACHED_CONFIG"]);
                        }
                    }

                    // Append
                    $this->services[$service->id] = $service;
                }
            }

        }
    }
}

namespace App\Common\Kernel\Docker\DockerConfig {
    /**
     * Class DockerConfigNetwork
     * @package App\Common\Kernel\Docker\DockerConfig
     */
    class DockerConfigNetwork
    {
        /** @var string */
        public string $name;
        /** @var string */
        public string $driver;
        /** @var string */
        public string $subnet;
    }

    /**
     * Class DockerConfigService
     * @package App\Common\Kernel\Docker\DockerConfig
     */
    class DockerConfigService
    {
        /** @var string */
        public string $id;
        /** @var bool|null */
        public ?bool $status = null;
        /** @var int|null */
        public ?int $internalPort = null;
        /** @var string|int|null */
        public null|string|int $externalPort = null;
        /** @var bool|null */
        public ?bool $appDebug = null;
        /** @var bool|null */
        public ?bool $appCachedConfig = null;
        /** @var string|null */
        public ?string $ipAddress = null;
    }
}
