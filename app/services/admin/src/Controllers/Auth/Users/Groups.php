<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Users;

use App\Common\Database\Primary\Users;
use App\Common\Exception\AppException;
use App\Common\Users\Group;
use App\Common\Validator;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Database\Exception\DatabaseException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Groups
 * @package App\Services\Admin\Controllers\Auth\Users
 */
class Groups extends AuthAdminAPIController
{
    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    protected function authCallback(): void
    {
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Users\Groups');
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws ValidatorException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function put(): void
    {
        $nameValidator = Validator::Name(32, true);

        // Group name
        try {
            $name = $nameValidator->getValidated($this->input()->getASCII("name"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("name", sprintf('Group name validation error (#%d)', $e->getCode()));
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();
        try {
            $group = new Group();
            $group->id = 0;
            $group->name = $name;
            $group->usersCount = 0;
            $group->updatedOn = time();
            $group->query()->insert();

            $group->id = $db->lastInsertId();
            if (!$group->id) {
                throw new AppException('Expected uint inserted group id');
            }

            // Admin Log Entry
            $this->adminLogEntry(
                sprintf('New users group "%s" created', $group->name),
                flags: ["user-groups", "user-group:" . $group->id]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
        $this->response->set("group", $group);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws ValidatorException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function post(): void
    {
        $group = $this->fetchGroupObject();
        $nameValidator = Validator::Name(32, true);

        // Editing name
        try {
            $name = $nameValidator->getValidated($this->input()->getASCII("name"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("name", sprintf('Group name validation error (#%d)', $e->getCode()));
        }

        if ($name === $group->name) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db = $this->aK->db->primary();
        $db->beginTransaction();
        try {
            $oldName = $group->name;

            $group->name = $name;
            $group->usersCount = $group->getLiveUsersCount();
            $group->updatedOn = time();
            $group->query()->update();

            // Admin Log Entry
            $this->adminLogEntry(
                sprintf('User group "%s" renamed to "%s"', $oldName, $group->name),
                flags: ["user-groups", "user-group:" . $group->id]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $group->deleteCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
        $this->response->set("group", $group);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws DatabaseException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    public function delete(): void
    {
        $group = $this->fetchGroupObject();
        $db = $this->aK->db->primary();

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        $db->beginTransaction();

        try {
            if ($group->getLiveUsersCount() !== 0) {
                throw new AdminAPIException('Cannot delete group with a positive users count');
            }

            $group->query()->delete();

            // Admin Log Entry
            $this->adminLogEntry(
                sprintf('User group "%s" deleted', $group->name),
                flags: ["user-groups", "user-group:" . $group->id]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        try {
            $group->deleteCached();
        } catch (CacheException $e) {
            $this->aK->errors->trigger($e, E_USER_WARNING);
        }

        $this->status(true);
    }

    /**
     * @return Group
     * @throws AdminAPIException
     */
    private function fetchGroupObject(): Group
    {
        try {
            $groupId = $this->input()->getInt("id", unSigned: true);
            if (!$groupId) {
                throw new AdminAPIException('Invalid group Id');
            }

            return Users\Groups::get($groupId, useCache: false);
        } catch (AdminAPIException $e) {
            $e->setParam("id");
            throw $e;
        } catch (AppException $e) {
            throw  AdminAPIException::Param("id", $e->getMessage(), $e->getCode());
        }
    }

    /**
     * @return void
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\ORM_Exception
     * @throws \Comely\Database\Exception\SchemaTableException
     */
    public function get(): void
    {
        try {
            $groups = \App\Common\Database\Primary\Users\Groups::Find()->query('WHERE 1')->all();
        } catch (ORM_ModelNotFoundException) {
            $groups = [];
        }

        if ($groups) {
            $timeStamp = time();

            /** @var Group $group */
            foreach ($groups as $group) {
                if (($timeStamp - $group->updatedOn) >= 1800) {
                    try {
                        if ($group->updateUsersCount()) {
                            $group->deleteCached();
                        }
                    } catch (DatabaseException|CacheException $e) {
                        $this->aK->errors->trigger($e, E_USER_WARNING);
                    }
                }
            }
        }

        $this->status(true);
        $this->response->set("groups", $groups);
    }
}
