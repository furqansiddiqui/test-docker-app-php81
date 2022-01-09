<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;

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
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     */
    protected function get(): void
    {
        $privileges = $this->admin->privileges();

        try {
            $permissions = Validator::JSON_Filter($privileges);
            unset($permissions["adminId"]);
        } catch (\JsonException) {
            throw new AdminAPIException('Failed to encode admin privileges');
        }

        $this->status(true);
        $this->response->set("type", $this->session->type);
        $this->response->set("archived", $this->session->archived !== 0);
        $this->response->set("admin", [
            "email" => $this->admin->email,
            "isRoot" => $privileges->isRoot(),
            "privileges" => $permissions
        ]);

        $this->response->set("issuedOn", $this->session->issuedOn);
        $this->response->set("lastUsedOn", $this->session->lastUsedOn);
        $this->response->set("last2faOn", $this->session->last2faOn);
    }
}
