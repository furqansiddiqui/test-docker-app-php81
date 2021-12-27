<?php
declare(strict_types=1);

namespace App\Common\Kernel;

use App\Common\AppKernel;

/**
 * Class AbstractHttpApp
 * @package App\Common\Kernel
 */
abstract class AbstractHttpApp extends AppKernel
{
    /** @var Http */
    public readonly Http $http;

    /**
     * @throws \App\Common\Exception\AppConfigException
     * @throws \App\Common\Exception\AppDirException
     * @throws \Comely\Yaml\Exception\ParserException
     */
    protected function __construct()
    {
        parent::__construct();
        $this->http = new Http();
    }
}
