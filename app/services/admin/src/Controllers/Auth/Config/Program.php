<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Config;

use App\Common\DataStore\OAuth2Config;
use App\Common\DataStore\OAuth2Vendors;
use App\Common\DataStore\ProgramConfig;
use App\Common\DataStore\RecaptchaConfig;
use App\Common\DataStore\RecaptchaStatus;
use App\Common\Exception\AppException;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;

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
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            if (!$this->admin->privileges()->editConfig) {
                throw new AdminAPIException('You do not have privilege to edit configuration');
            }
        }

        switch (strtolower($this->input()->getASCII("action"))) {
            case "oauth2":
                $this->updateOAuth2();
                return;
            case "recaptcha":
                $this->updateReCaptcha();
                return;
            default:
                throw AdminAPIException::Param("action", "Invalid action called");
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function updateOAuth2(): void
    {
        $this->totpResourceLock();
        $changes = 0;
        $changedVendors = [];

        if (!$this->programConfig->oAuth2) {
            $this->programConfig->oAuth2 = new OAuth2Config();
        }

        $oAuth2Config = $this->programConfig->oAuth2;

        // Global trigger
        $globalStatus = Validator::getBool($this->input()->getASCII("status"));
        if ($oAuth2Config->status !== $globalStatus) {
            $oAuth2Config->status = $globalStatus;
            $changes++;
        }

        foreach (OAuth2Vendors::cases() as $vendor) {
            unset($vendorConfig, $vendorStatus, $vendorAppId, $vendorAppKey);
            $vendorConfig = $oAuth2Config->get($vendor);

            // Status
            $vendorStatus = Validator::getBool($this->input()->getASCII($vendor->value . "Status"));
            if ($vendorConfig->status !== $vendorStatus) {
                $vendorConfig->status = $vendorStatus;
                $changedVendors[$vendor->name] = 1;
                $changes++;
            }

            // App ID / Public Key
            try {
                $vendorAppId = $this->input()->getASCII($vendor->value . "AppId");
                if (!$vendorAppId) {
                    $vendorAppId = null;
                } else {
                    if (strlen($vendorAppId) < 4 || strlen($vendorAppId) > 128) {
                        throw new AdminAPIException("Invalid application ID / public key");
                    }
                }

                if ($vendorStatus && !$vendorAppId) {
                    throw new AdminAPIException("App ID / public key is required");
                }
            } catch (AdminAPIException $e) {
                $e->setParam($vendor->value . "AppId");
                throw $e;
            }

            if ($vendorAppId !== $vendorConfig->appId) {
                $vendorConfig->appId = $vendorAppId;
                $changedVendors[$vendor->name] = 1;
                $changes++;
            }

            // App Key / Private Key
            try {
                $vendorAppKey = $this->input()->getASCII($vendor->value . "AppKey");
                if (!$vendorAppKey) {
                    $vendorAppKey = null;
                } else {
                    if (strlen($vendorAppKey) < 4 || strlen($vendorAppKey) > 128) {
                        throw new AdminAPIException("Invalid application key / private key");
                    }
                }

                if ($vendorStatus && !$vendorAppKey) {
                    throw new AdminAPIException("App key / private key is required");
                }
            } catch (AdminAPIException $e) {
                $e->setParam($vendor->value . "AppKey");
                throw $e;
            }

            if ($vendorAppKey !== $vendorConfig->appKey) {
                $vendorConfig->appKey = $vendorAppKey;
                $changedVendors[$vendor->name] = 1;
                $changes++;
            }
        }

        // Changes?
        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->programConfig->save();

            $this->adminLogEntry('OAuth2 configuration updated', flags: ["config", "program-config", "oauth2-config"]);
            foreach ($changedVendors as $vendorName => $l) {
                $this->adminLogEntry(
                    sprintf('OAuth2 vendor "%s" configuration updated', ucfirst(strtolower($vendorName))),
                    flags: ["config", "program-config", "oauth2-config"]
                );
            }

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Clear Cached
        try {
            ProgramConfig::ClearCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    private function updateReCaptcha(): void
    {
        $this->totpResourceLock();
        $changes = 0;

        if (!$this->programConfig->reCaptcha) {
            $this->programConfig->reCaptcha = new RecaptchaConfig(RecaptchaStatus::DISABLED);
        }

        $reCaptchaConfig = $this->programConfig->reCaptcha;

        // Status
        try {
            $status = RecaptchaStatus::tryFrom($this->input()->getInt("status"));
            if (!$status) {
                throw new AdminAPIException('Invalid ReCaptcha status');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("status");
            throw $e;
        }

        if ($reCaptchaConfig->status->value !== $status->value) {
            $changes++;
        }

        try {
            $publicKey = $this->input()->getASCII("publicKey");
            if (!$publicKey) {
                $publicKey = null;
            }

            if ($status->value > 0 && !$publicKey) {
                throw new AdminAPIException('Public key is required');
            }

            if ($publicKey) {
                if (strlen($publicKey) < 4 || strlen($publicKey) > 128) {
                    throw new AdminAPIException('Invalid public key');
                }
            }
        } catch (AdminAPIException $e) {
            $e->setParam("publicKey");
            throw $e;
        }

        if ($publicKey !== $reCaptchaConfig->publicKey) {
            $changes++;
        }

        try {
            $privateKey = $this->input()->getASCII("privateKey");
            if (!$privateKey) {
                $privateKey = null;
            }

            if ($status->value > 0 && !$privateKey) {
                throw new AdminAPIException('Private key is required');
            }

            if ($privateKey) {
                if (strlen($privateKey) < 4 || strlen($privateKey) > 128) {
                    throw new AdminAPIException('Invalid private key');
                }
            }
        } catch (AdminAPIException $e) {
            $e->setParam("privateKey");
            throw $e;
        }

        if ($privateKey !== $reCaptchaConfig->privateKey) {
            $changes++;
        }

        // Changes?
        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->programConfig->reCaptcha = new RecaptchaConfig($status, $publicKey, $privateKey);
            $this->programConfig->save();

            $this->adminLogEntry('ReCaptcha configuration updated', flags: ["config", "program-config", "recaptcha-config"]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Clear Cached
        try {
            ProgramConfig::ClearCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
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
