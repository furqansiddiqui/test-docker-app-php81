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
            "email" => $this->admin->email,
            "phone" => $this->admin->phone,
        ]);

        $this->response->set("suggestedAuthSeed", GoogleAuthenticator::generateSecret());
    }
}
