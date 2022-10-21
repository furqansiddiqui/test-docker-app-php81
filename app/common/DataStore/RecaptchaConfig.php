<?php
declare(strict_types=1);

namespace App\Common\DataStore {
    /**
     * Class RecaptchaStatus
     * @package App\Common\DataStore
     */
    enum RecaptchaStatus: int
    {
        case DISABLED = 0x00;
        case ENABLED = 0x01;
        case DYNAMIC = 0x02;
    }

    /**
     * Class RecaptchaConfig
     * @package App\Common\DataStore
     */
    class RecaptchaConfig
    {
        /**
         * @param RecaptchaStatus $status
         * @param string $publicKey
         * @param string $privateKey
         */
        public function __construct(
            public readonly RecaptchaStatus $status,
            public readonly string          $publicKey,
            public readonly string          $privateKey
        )
        {
        }

        /**
         * @return string[]
         */
        public function __debugInfo(): array
        {
            return ["ReCaptcha Config"];
        }
    }
}

