<?php
declare(strict_types=1);

namespace App\Common;

use App\Common\Exception\AppConfigException;
use App\Common\Exception\AppDirException;
use App\Common\Exception\AppException;
use App\Common\Kernel\Ciphers;
use App\Common\Kernel\Config;
use App\Common\Kernel\Databases;
use App\Common\Kernel\Directories;
use App\Common\Kernel\ErrorHandler\AbstractErrorHandler;
use App\Common\Kernel\ErrorHandler\Errors;
use App\Common\Kernel\ErrorHandler\StdErrorHandler;
use Comely\Cache\Cache;
use Comely\Cache\Exception\CacheException;
use Comely\Cache\Memory;
use Comely\Filesystem\Exception\PathNotExistException;
use Comely\Filesystem\Filesystem;
use FurqanSiddiqui\SemaphoreEmulator\Exception\SemaphoreEmulatorException;
use FurqanSiddiqui\SemaphoreEmulator\SemaphoreEmulator;

/**
 * Class AppKernel
 * @package App\Common
 */
class AppKernel implements AppConstants
{
    /** @var AppKernel|null */
    protected static ?AppKernel $instance = null;

    /**
     * @return static
     */
    public static function getInstance(): static
    {
        if (!static::$instance) {
            throw new \UnexpectedValueException('App kernel not bootstrapped');
        }

        return static::$instance;
    }

    /**
     * @return static
     */
    public static function Bootstrap(): static
    {
        if (static::$instance) {
            throw new \RuntimeException('App kernel is already bootstrapped');
        }

        return static::$instance = new static();
    }

    /** @var Cache */
    public readonly Cache $cache;
    /** @var Ciphers */
    public readonly Ciphers $ciphers;
    /** @var Config */
    public readonly Config $config;
    /** @var Databases */
    public readonly Databases $db;
    /** @var bool */
    public readonly bool $debug;
    /** @var Directories */
    public readonly Directories $dirs;
    /** @var Errors */
    public readonly Errors $errors;
    /** @var Memory */
    public readonly Memory $memory;
    /** @var string */
    private string $timeZone;

    /** @var AbstractErrorHandler */
    private AbstractErrorHandler $eH;
    /** @var \Closure|null */
    private ?\Closure $debugCheckCb = null;
    /** @var SemaphoreEmulator|null */
    private ?SemaphoreEmulator $sE = null;

    /**
     * @throws AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function __construct()
    {
        $this->debug = Validator::getBool(trim(strval(getenv("COMELY_APP_DEBUG"))));
        $this->dirs = new Directories();
        $this->errors = new Errors($this);
        $this->switchErrorHandler(new StdErrorHandler($this));

        $this->ciphers = new Ciphers($this);
        $this->db = new Databases($this);
        $this->memory = new Memory();

        // Configurations
        $this->initConfig(Validator::getBool(trim(strval(getenv("COMELY_APP_CACHED_CONFIG")))));
        $this->timeZone = $this->config->env->timeZone;
        date_default_timezone_set($this->timeZone);

        // Initialize Caching Engine
        $this->cache = new Cache();
        if ($this->config->cache->engine) {
            try {
                $this->cache->pool()->addRedisServer(
                    "primary",
                    $this->config->cache->host,
                    $this->config->cache->port,
                    $this->config->cache->timeOut
                );

                $cacheConnectErrors = [];
                $this->cache->connect($cacheConnectErrors);
                $this->memory->useCache($this->cache); // Assigned newly connected server to Memory holder
            } catch (CacheException $e) {
                $this->errors->trigger($e, E_USER_WARNING);

                // Redis connection debugging
                if (isset($cacheConnectErrors) && $this->debug) {
                    foreach ($cacheConnectErrors as $cacheConnectError) {
                        $this->errors->trigger($cacheConnectError);
                    }
                }
            }
        }
    }

    /**
     * @param AbstractErrorHandler $eH
     * @return $this
     */
    public function switchErrorHandler(AbstractErrorHandler $eH): static
    {
        $this->eH = $eH;
        return $this;
    }

    /**
     * @return AbstractErrorHandler
     */
    public function errorHandler(): AbstractErrorHandler
    {
        return $this->eH;
    }

    /**
     * @param string $tz
     * @return $this
     * @throws AppConfigException
     */
    public function changeTimezone(string $tz): static
    {
        if (!in_array($tz, \DateTimeZone::listIdentifiers())) {
            throw new AppConfigException('Invalid/unsupported argument timezone');
        }

        $this->timeZone = $tz;
        date_default_timezone_set($this->timeZone);
        return $this;
    }

    /**
     * @return string
     */
    public function timeZone(): string
    {
        return $this->timeZone;
    }

    /**
     * @param bool $cachedConfig
     * @throws Exception\AppConfigException
     * @throws Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    private function initConfig(bool $cachedConfig): void
    {
        $tmp = $this->dirs->tmp();
        Filesystem::clearStatCache($tmp->suffix("comely-appConfig.php.cache"));

        if ($cachedConfig) {
            try {
                $cachedConfigObj = $tmp->file("comely-appConfig.php.cache", false)->read();
            } catch (PathNotExistException) {
            } catch (\Exception $e) {
                trigger_error('Failed to load cached configuration', E_USER_WARNING);
                if ($this->debug) {
                    Errors::Exception2Error($e, E_USER_WARNING);
                }
            }
        }

        if (isset($cachedConfigObj) && $cachedConfigObj) {
            $appConfig = unserialize($cachedConfigObj, [
                "allowed_classes" => [
                    'App\Common\Kernel\Config',
                    'App\Common\Kernel\Config\CacheConfig',
                    'App\Common\Kernel\Config\CipherKeys',
                    'App\Common\Kernel\Config\DbConfig',
                    'Comely\Database\Server\DbCredentials',
                    'App\Common\Kernel\Config\EnvConfig',
                    'App\Common\Kernel\Config\PublicConfig',
                ]
            ]);

            if ($appConfig instanceof Config) {
                $this->config = $appConfig;
                return; // Found cached configurations; return
            }
        }

        // Draft new configuration
        $appConfig = new Config($this);
        if ($cachedConfig) {
            try {
                $this->dirs->tmp()
                    ->file("comely-appConfig.php.cache", true)
                    ->edit(serialize($appConfig), true);
            } catch (\Exception $e) {
                trigger_error('Failed to write cached configuration', E_USER_WARNING);
                if ($this->debug) {
                    Errors::Exception2Error($e, E_USER_WARNING);
                }
            }
        }

        $this->config = $appConfig;
    }

    /**
     * @param \Closure $method
     * @return $this
     */
    public function debugCheckCallback(\Closure $method): static
    {
        $this->debugCheckCb = $method;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        if ($this->debugCheckCb) {
            return call_user_func_array($this->debugCheckCb, [$this->debug]);
        }

        return $this->debug;
    }

    /**
     * @return SemaphoreEmulator
     * @throws AppException
     */
    public function semaphoreEmulator(): SemaphoreEmulator
    {
        if (!$this->sE) {
            try {
                $this->sE = new SemaphoreEmulator($this->dirs->sempahore());
            } catch (AppDirException|SemaphoreEmulatorException $e) {
                $this->errors->trigger($e, E_USER_WARNING);
                throw new AppException('Failed to get SemaphoreEmulator');
            }
        }

        return $this->sE;
    }

    /**
     * @param string $const
     * @return mixed
     */
    final public function constant(string $const): mixed
    {
        return @constant('static::' . strtoupper($const));
    }
}
