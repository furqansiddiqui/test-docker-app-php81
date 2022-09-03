<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\PublicAPI;

use App\Common\DataStore\PublicAPIAccess;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;

/**
 * Class Access
 * @package App\Services\Admin\Controllers\Auth\PublicAPI
 */
class Access extends AuthAdminAPIController
{
    /** @var PublicAPIAccess */
    private PublicAPIAccess $pIA;

    /**
     * @return void
     */
    protected function authCallback(): void
    {
        try {
            $this->pIA = PublicAPIAccess::getInstance(true);
        } catch (\Exception $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
            $this->pIA = new PublicAPIAccess();
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function post(): void
    {
        if (!$this->admin->privileges()->isRoot()) {
            if (!$this->admin->privileges()->editConfig) {
                throw new AdminAPIException('You do not have privilege to edit configuration');
            }
        }

        $this->totpResourceLock();

        $changes = 0;
        $reflection = new \ReflectionClass($this->pIA);
        $triggers = $reflection->getProperties(\ReflectionProperty::IS_PUBLIC);
        foreach ($triggers as $trigger) {
            $propId = $trigger->name;
            $current = $this->pIA->$propId ?? null;
            $this->pIA->$propId = Validator::getBool($this->input()->getASCII($propId));
            if ($current !== $this->pIA->$propId) {
                $changes++;
            }
        }

        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->pIA->save();
            $this->adminLogEntry("Public API Configuration Updated", flags: ["config", "public-api"]);

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // Clear Cached
        try {
            PublicAPIAccess::ClearCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return void
     */
    public function get(): void
    {
        $this->status(true);
        $this->response->set("config", $this->pIA);
    }
}
