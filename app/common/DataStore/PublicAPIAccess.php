<?php
declare(strict_types=1);

namespace App\Common\DataStore;

/**
 * Class PublicAPIAccess
 * @package App\Common\DataStore
 */
class PublicAPIAccess extends AbstractDataStoreObject
{
    public const DB_KEY = "app.publicAPIAccess";
    public const CACHE_KEY = "app.publicAPIAccess";
    public const CACHE_TTL = 86400 * 7;
    public const IS_ENCRYPTED = false;

    /** @var bool */
    public bool $globalStatus = false;
    /** @var bool */
    public bool $signUp = false;
    /** @var bool */
    public bool $signIn = false;
    /** @var bool */
    public bool $recoverPassword = false;
}
