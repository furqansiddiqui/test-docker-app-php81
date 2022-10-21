<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Config;

use App\Common\DataStore\SystemConfig;
use App\Common\Exception\AppException;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;

/**
 * Class System
 * @package App\Services\Admin\Controllers\Auth\Config
 */
class System extends AuthAdminAPIController
{
    /** @var SystemConfig */
    private SystemConfig $sysConfig;

    /**
     * @return void
     */
    protected function authCallback(): void
    {
        try {
            $this->sysConfig = SystemConfig::getInstance(false);
        } catch (AppException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            $this->sysConfig = new SystemConfig();
        }
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $this->status(true);
        $this->response->set("config", $this->sysConfig);
    }
}
