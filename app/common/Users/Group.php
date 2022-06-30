<?php
declare(strict_types=1);

namespace App\Common\Users;

use App\Common\Database\AbstractAppModel;
use App\Common\Database\Primary\Users;
use App\Common\Database\Primary\Users\Groups;
use App\Common\Exception\AppException;
use Comely\Database\Exception\DatabaseException;

/**
 * Class Group
 * @package App\Common\Users
 */
class Group extends AbstractAppModel
{
    public const TABLE = Groups::TABLE;
    public const SERIALIZABLE = true;

    /** @var int */
    public int $id;
    /** @var string */
    public string $name;
    /** @var int */
    public int $usersCount = 0;
    /** @var int */
    public int $updatedOn;

    /**
     * @return int
     * @throws AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    public function getLiveUsersCount(): int
    {
        $db = $this->aK->db->primary();
        $queryUsersCount = $db->fetch(sprintf('SELECT count(*) ' . 'FROM `%s` WHERE `group_id`=?', Users::TABLE), [$this->id]);
        $usersCountRow = $queryUsersCount->next();
        $newUserCount = intval($usersCountRow["count(*)"] ?? -1);
        if ($newUserCount >= 0) {
            return $newUserCount;
        }

        throw new AppException(sprintf('Failed to retrieve live users count for group %d', $this->id));
    }

    /**
     * @return bool
     * @throws AppException
     * @throws DatabaseException
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     */
    public function updateUsersCount(): bool
    {
        $liveUsersCount = $this->getLiveUsersCount();
        if ($liveUsersCount > 0 && $liveUsersCount !== $this->usersCount) {
            $this->usersCount = $liveUsersCount;
            $this->updatedOn = time();
            $this->query()->update();
            return true;
        }

        return false;
    }

    /**
     * @return void
     * @throws \Comely\Cache\Exception\CacheException
     */
    public function deleteCached(): void
    {
        $this->aK->cache->delete(sprintf('u_gr_%d', $this->id));
    }
}
