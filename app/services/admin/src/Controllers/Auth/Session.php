<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

/**
 * Class Session
 * @package App\Services\Admin\Controllers\Auth
 */
class Session extends AuthAdminAPIController
{
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     */
    protected function get(): void
    {
        $this->status(true);
        $this->response->set("type", $this->session->type);
        $this->response->set("archived", $this->session->archived !== 0);
        $this->response->set("issuedOn", $this->session->issuedOn);
        $this->response->set("lastUsedOn", $this->session->lastUsedOn);
        $this->response->set("last2faOn", $this->session->last2faOn);
    }
}
