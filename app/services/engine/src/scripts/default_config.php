<?php
declare(strict_types=1);

namespace bin;

use App\Common\DataStore\MailConfig;
use App\Common\DataStore\MailService;
use App\Common\Exception\AppException;
use App\Common\Exception\AppModelNotFoundException;
use App\Common\Kernel\CLI\AbstractCLIScript;

/**
 * Class default_config
 * @package bin
 */
class default_config extends AbstractCLIScript
{
    public function exec(): void
    {
        $this->print("");
        $this->print("Checking for default configuration objects... ");

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
