<?php
declare(strict_types=1);

namespace App\Common\Users;

/**
 * User tags/flags
 */
interface UserTagsInterface
{
    /** @var string[] */
    public const KNOWN_FLAGS = [
        self::TOS_ACCEPTED,
        self::PRIVACY_POLICY_ACCEPTED,
        self::SUGGEST_PASSWORD_CHANGE,
        self::FORCE_PASSWORD_CHANGE,
        self::SUGGEST_2FA_UPDATE,
        self::ACCOUNT_LIMITED_1,
        self::ACCOUNT_LIMITED_2,
        self::ACCOUNT_LIMITED_3,
        self::PROMOTIONAL_EMAILS,
    ];

    /** @var string */
    public const TOS_ACCEPTED = "tos_accepted";
    /** @var string */
    public const PRIVACY_POLICY_ACCEPTED = "pp_accepted";
    /** @var string */
    public const SUGGEST_PASSWORD_CHANGE = "suggest_pw_change";
    /** @var string */
    public const FORCE_PASSWORD_CHANGE = "force_pw_change";
    /** @var string */
    public const SUGGEST_2FA_UPDATE = "suggest_2fa_change";
    /** @var string */
    public const ACCOUNT_LIMITED_1 = "account_limited_1";
    /** @var string */
    public const ACCOUNT_LIMITED_2 = "account_limited_2";
    /** @var string */
    public const ACCOUNT_LIMITED_3 = "account_limited_3";
    /** @var string */
    public const PROMOTIONAL_EMAILS = "promo_mails";
}
