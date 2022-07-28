<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Users\UserBaggage;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;

/**
 * Class Baggage
 * @package App\Services\Admin\Controllers\Auth\Users
 */
class Baggage extends AuthAdminAPIController
{
    /** @var UserBaggage */
    private UserBaggage $uB;

    /**
     * @return void
     * @throws AdminAPIException
     */
    protected function authCallback(): void
    {
        $userId = $this->input()->getInt("user", unSigned: true);
        if (!$userId) {
            throw AdminAPIException::Param("user", "Invalid user ID");
        }

        $this->uB = new UserBaggage($userId);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public function delete(): void
    {
        $key = $this->input()->getASCII("key");
        if (!preg_match('/^[\w\-.]{2,32}$/i', $key)) {
            throw AdminAPIException::Param("key", "Invalid baggage item key");
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $existed = $this->uB->delete($key);

        $this->status(true);
        $this->response->set("existed", (bool)$existed);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Cache\Exception\CacheException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\QueryExecuteException
     */
    public function post(): void
    {
        // Key
        try {
            $key = $this->input()->getASCII("key");
            if (!$key) {
                throw new AdminAPIException('Baggage item key is required');
            } elseif (!preg_match('/^[\w\-.]{2,32}$/i', $key)) {
                throw new AdminAPIException('Invalid key for baggage item');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("key");
            throw $e;
        }

        // Value
        try {
            $value = $this->input()->getASCII("value");
            if (!$value) {
                throw new AdminAPIException('Baggage item value is required');
            } elseif (strlen($value) > 1024) {
                throw new AdminAPIException('Baggage item cannot exceed 1024 bytes');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("value");
            throw $e;
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $this->uB->set($key, $value);
        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \App\Common\Exception\AppModelNotFoundException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function get(): void
    {
        $key = $this->input()->getASCII("key");
        if ($key) {
            if (!preg_match('/^[\w\-.]{2,32}$/i', $key)) {
                throw AdminAPIException::Param("key", "Invalid baggage item key");
            }

            $value = $this->uB->get($key, false);

            $this->status(true);
            $this->response->set("item", [
                "user" => $this->uB->userId,
                "key" => $key,
                "length" => strlen($value),
                "data" => $value
            ]);

            return;
        }

        $list = $this->uB->list();
        for ($i = 0; $i < count($list); $i++) {
            $list[$i]["length"] = strlen($list[$i]["data"]);
        }

        $this->status(true);
        $this->response->set("items", $list);
    }
}
