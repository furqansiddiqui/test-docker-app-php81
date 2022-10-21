<?php
declare(strict_types=1);

namespace App\Common\DataStore;

/**
 * Class ProgramConfig
 * @package App\Common\DataStore
 */
class ProgramConfig extends AbstractDataStoreObject
{
    /** @var RecaptchaConfig|null */
    public ?RecaptchaConfig $reCaptcha = null;
    /** @var OAuth2Config|null */
    public ?OAuth2Config $oAuth2 = null;
}
