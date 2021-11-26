<?php
declare(strict_types=1);

namespace App\Common\Kernel\Config;

use App\Common\Exception\AppConfigException;
use Comely\Utils\Validator\Exception\ValidatorException;
use Comely\Utils\Validator\Validator;

/**
 * Class PublicConfig
 * @package App\Common\Kernel\Config
 */
class PublicConfig
{
    /** @var string */
    public readonly string $title;
    /** @var string */
    public readonly string $domain;
    /** @var bool */
    public readonly bool $secure;
    /** @var string */
    public readonly string $email;

    /**
     * @param array $config
     * @throws AppConfigException
     */
    public function __construct(array $config)
    {
        $emailValidator = \App\Common\Validator::EmailAddress();
        $titleValidator = \App\Common\Validator::Name(20, allowDashes: true);
        $hostnameValidator = Validator::ASCII()->setCustomFn(function (string $hostname) {
            return \App\Common\Validator::isValidHostname($hostname, allowIpAddr: false) ? $hostname : false;
        });

        // Title
        try {
            $this->title = $titleValidator->getValidated($config["title"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Public[title]: Invalid project title (0x%s)', dechex($e->getCode())));
        }

        // Domain
        try {
            $this->domain = $hostnameValidator->getValidated($config["domain"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Public[domain]: Invalid domain name (0x%s)', dechex($e->getCode())));
        }

        // HTTPS
        $this->secure = \App\Common\Validator::getBool($config["secure"]);

        // E-mail
        try {
            $this->email = $emailValidator->getValidated($config["email"]);
        } catch (ValidatorException $e) {
            throw new AppConfigException(sprintf('Public[email]: Invalid e-mail address (0x%s)', dechex($e->getCode())));
        }
    }
}
