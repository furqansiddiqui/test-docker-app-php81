<?php
declare(strict_types=1);

namespace App\Common\DataStore;

/**
 * Class ProgramConfig
 * @package App\Common\DataStore
 */
class ProgramConfig extends AbstractDataStoreObject
{
    public const DB_KEY = "app.programConfig";
    public const CACHE_KEY = "app.programConfig";
    public const CACHE_TTL = 86400 * 7;
    public const IS_ENCRYPTED = true;

    /** @var RecaptchaConfig|null */
    public ?RecaptchaConfig $reCaptcha = null;
    /** @var OAuth2Config|null */
    public ?OAuth2Config $oAuth2 = null;
}
