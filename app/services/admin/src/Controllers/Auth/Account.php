<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Packages\GoogleAuth\GoogleAuthenticator;

/**
 * Class Account
 * @package App\Services\Admin\Controllers\Auth
 */
class Account extends AuthAdminAPIController
{
    /**
     * @return void
     */
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     */
    protected function get(): void
    {
        $this->status(true);
        $this->response->set("admin", [
            "phone" => $this->admin->phone,
        ]);

        $suggestedAuthSeed = GoogleAuthenticator::generateSecret();
        $this->response->set("suggestedAuthSeed", $suggestedAuthSeed);
        $this->response->set("suggestedAuthSeedQR", GoogleAuthenticator::getImageUrl(
            $this->admin->email,
            $this->aK->config->public->title . " " . "Admin",
            $suggestedAuthSeed
        ));
    }
}
