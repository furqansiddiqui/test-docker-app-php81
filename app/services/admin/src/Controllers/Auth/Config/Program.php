<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Config;

use App\Common\DataStore\OAuth2Config;
use App\Common\DataStore\OAuth2Vendors;
use App\Common\DataStore\ProgramConfig;
use App\Common\Exception\AppException;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;

/**
 * Class Program
 * @package App\Services\Admin\Controllers\Auth\Config
 */
class Program extends AuthAdminAPIController
{
    /** @var ProgramConfig */
    private ProgramConfig $programConfig;

    /**
     * @return void
     */
    protected function authCallback(): void
    {
        try {
            $this->programConfig = ProgramConfig::getInstance(false);
        } catch (AppException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            $this->programConfig = new ProgramConfig();
        }
    }

    /**
     * @return void
     */
    public function get(): void
    {
        if (!$this->programConfig->oAuth2) {
            $this->programConfig->oAuth2 = new OAuth2Config();
            $this->programConfig->oAuth2->vendors = [];
        }

        foreach (OAuth2Vendors::cases() as $vendor) {
            $this->programConfig->oAuth2->vendors[$vendor->value] = $this->programConfig->oAuth2->get($vendor);
        }

        $this->status(true);
        $this->response->set("config", $this->programConfig);
    }
}
