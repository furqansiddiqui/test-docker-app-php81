<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\AppKernel;
use App\Common\Kernel\Config\CacheConfig;
use App\Common\Kernel\Config\CipherKeys;
use App\Common\Kernel\Config\DbConfig;
use App\Common\Kernel\Config\EnvConfig;
use App\Common\Kernel\Config\PublicConfig;
use Comely\Yaml\Yaml;

/**
 * Class Config
 * @package App\Common\Kernel
 */
class Config
{
    /** @var EnvConfig */
    public readonly EnvConfig $env;
    /** @var CacheConfig */
    public readonly CacheConfig $cache;
    /** @var CipherKeys */
    public readonly CipherKeys $cipher;
    /** @var DbConfig */
    public readonly DbConfig $db;
    /** @var PublicConfig */
    public readonly PublicConfig $public;

    /**
     * @param AppKernel $aK
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    public function __construct(private AppKernel $aK)
    {
        // Environment Configuration Vars
        $this->env = new EnvConfig();

        // App YAML Configurations Files
        $configsPath = $this->aK->dirs->config()->path();

        $this->cache = new CacheConfig(Yaml::Parse($configsPath . "/cache.yml")->generate());
        $this->cipher = new CipherKeys(Yaml::Parse($configsPath . "/cipher.yml")->generate());
        $this->db = new DbConfig(Yaml::Parse($configsPath . "/databases.yml")->generate());
        $this->public = new PublicConfig(Yaml::Parse($configsPath . "/public.yml")->generate());
    }
}
