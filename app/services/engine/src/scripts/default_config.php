<?php
declare(strict_types=1);

namespace bin;

use App\Common\DataStore\MailConfig;
use App\Common\DataStore\MailService;
use App\Common\DataStore\ProgramConfig;
use App\Common\DataStore\PublicAPIAccess;
use App\Common\DataStore\SystemConfig;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Kernel\CLI\AbstractCLIScript;

/**
 * Class default_config
 * @package bin
 */
class default_config extends AbstractCLIScript
{
    public const DISPLAY_HEADER = false;

    /**
     * @return void
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function exec(): void
    {
        $this->print("");
        $this->print("Checking for default configuration objects... ");

        // System Configuration
        $this->inline("\t{cyan}System Configuration{/} {grey}...{/} ");

        try {
            SystemConfig::getInstance(useCache: false);
            $this->print("{green}Exists");
        } catch (AppModelNotFoundException) {
            $sC = new SystemConfig();
            $sC->save();
            $this->print("{green}Created");
        } catch (AppException) {
            $this->print("{red}Error");
        }

        // Program Configuration
        $this->inline("\t{cyan}Program Configuration{/} {grey}...{/} ");

        try {
            ProgramConfig::getInstance(useCache: false);
            $this->print("{green}Exists");
        } catch (AppModelNotFoundException) {
            $pC = new ProgramConfig();
            $pC->save();
            $this->print("{green}Created");
        } catch (AppException) {
            $this->print("{red}Error");
        }

        // Mailer Configuration
        $this->inline("\t{cyan}Mailer Configuration{/} {grey}...{/} ");

        try {
            MailConfig::getInstance(useCache: false);
            $this->print("{green}Exists");
        } catch (AppModelNotFoundException) {
            $mailConfig = new MailConfig();
            $mailConfig->service = MailService::DISABLED;
            $mailConfig->senderName = "Do-Not-Reply";
            $mailConfig->senderEmail = "noreply@" . $this->aK->config->public->domain;
            $mailConfig->useTLS = true;
            $mailConfig->timeOut = 1;
            $mailConfig->serverName = $this->aK->config->public->domain;
            $mailConfig->save();
            $this->print("{green}Created");
        } catch (AppException) {
            $this->print("{red}Error");
        }

        // Public API Access
        $this->inline("\t{cyan}Public API Access{/} {grey}...{/} ");

        try {
            PublicAPIAccess::getInstance(useCache: false);
            $this->print("{green}Exists");
        } catch (AppModelNotFoundException) {
            $pAC = new PublicAPIAccess();
            $pAC->globalStatus = false;
            $pAC->save();
            $this->print("{green}Created");
        } catch (AppException) {
            $this->print("{red}Error");
        }
    }

    /**
     * @return string|null
     */
    public function processInstanceId(): ?string
    {
        return null;
    }

    /**
     * @return string|null
     */
    public function semaphoreLockId(): ?string
    {
        return null;
    }
}
