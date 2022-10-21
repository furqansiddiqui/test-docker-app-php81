<?php
declare(strict_types=1);

namespace App\Common\DataStore {
    /**
     * Class OAuth2Vendors
     * @package App\Common\DataStore
     */
    enum OAuth2Vendors: string
    {
        case FACEBOOK = "fb";
        case TWITTER = "tw";
        case APPLE = "apple";
        case GOOGLE = "google";
    }

    /**
     * Class OAuth2Config
     * @package App\Common\DataStore
     */
    class OAuth2Config
    {
        /** @var bool */
        public bool $status = false;
        /** @var array */
        private array $configs = [];

        /**
         * @param OAuth2Vendors $vendor
         * @return OAuth2Vendor
         */
        public function get(OAuth2Vendors $vendor): OAuth2Vendor
        {
            if (!isset($this->configs[$vendor->value])) {
                $this->configs[$vendor->value] = new OAuth2Vendor($vendor);
            }

            return $this->configs[$vendor->value];
        }
    }

    /**
     * Class OAuth2Vendor
     * @package App\Common\DataStore
     */
    class OAuth2Vendor
    {
        /** @var bool */
        public bool $status = false;
        /** @var string|null */
        public ?string $appId = null;
        /** @var string|null */
        public ?string $appKey = null;

        /**
         * @param OAuth2Vendors $vendorId
         */
        public function __construct(public readonly OAuth2Vendors $vendorId)
        {
        }

        /**
         * @return array
         */
        public function __debugInfo(): array
        {
            return [sprintf("%s OAuth2 Config", $this->vendorId->name)];
        }
    }
}
