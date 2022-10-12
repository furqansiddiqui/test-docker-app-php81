<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\CachedItem;
use Comely\Cache\Exception\CacheException;

/**
 * Class Caching
 * @package App\Services\Admin\Controllers\Auth
 */
class Caching extends AuthAdminAPIController
{
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws CacheException
     * @throws \App\Common\Exception\AppException
     */
    public function post(): void
    {
        $privileges = $this->admin->privileges();
        if (!$privileges->isRoot() && !$privileges->editConfig) {
            throw new AdminAPIException('You are not privileged to delete cached objects [editConfig]');
        }

        switch (strtolower($this->input()->getASCII("action"))) {
            case "delete":
                $this->deleteObject();
                return;
            case "flush":
                $this->flushAll();
                return;
            default:
                throw AdminAPIException::Param("action", "Invalid action called");
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws CacheException
     * @throws \App\Common\Exception\AppException
     */
    private function flushAll(): void
    {
        $this->totpVerify($this->input()->getASCII("totp"));

        $this->aK->cache->flush(null);
        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws CacheException
     * @throws \App\Common\Exception\AppException
     */
    private function deleteObject(): void
    {
        try {
            $objectId = $this->input()->getASCII("object");
            if (!$objectId) {
                throw new AdminAPIException('Object ID is required');
            } elseif (!preg_match('/^[a-z\d.\-_+]{3,40}$/i', $objectId)) {
                throw new AdminAPIException('Invalid object identifier');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("object");
            throw $e;
        }

        $this->totpVerify($this->input()->getASCII("totp"));

        $this->aK->cache->delete($objectId);
        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    private function checkObjects(): void
    {
        $objectIds = explode(",", $this->input()->getASCII("objects"));
        if (!$objectIds) {
            throw new AdminAPIException('No object IDs provided to search');
        }

        $result = [];
        $cache = $this->aK->cache;
        foreach ($objectIds as $objectId) {
            unset($cachedObject);
            if (!$objectId) {
                continue;
            }

            $result[$objectId] = [
                "found" => false,
            ];

            if (!preg_match('/^[a-z\d.\-_+]{3,40}$/i', $objectId)) {
                $result[$objectId]["error"] = "Invalid object identifier";
                continue;
            }

            try {
                $cachedObject = $cache->get($objectId, returnRawObject: true);
                if ($cachedObject instanceof CachedItem) {
                    $result[$objectId]["found"] = true;
                    $result[$objectId]["cachedOn"] = $cachedObject->storedOn;
                }
            } catch (CacheException $e) {
                $result[$objectId]["error"] = $e->getMessage();
                continue;
            }
        }

        $this->status(true);
        $this->response->set("objects", $result);
    }

    /**
     * @return void
     */
    private function cacheConfig(): void
    {
        $this->status(true);
        $this->response->set("config", $this->aK->config->cache);
    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    public function get(): void
    {
        switch (strtolower($this->input()->getASCII("action"))) {
            case "objects":
                $this->checkObjects();
                return;
            default:
                $this->cacheConfig();
        }
    }
}
