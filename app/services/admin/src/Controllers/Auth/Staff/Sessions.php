<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Staff;

use App\Common\Admin\Session;
use App\Services\Admin\Controllers\Auth\AuthAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;

/**
 * Class Sessions
 * @package App\Services\Admin\Controllers\Auth\Staff
 */
class Sessions extends AuthAdminAPIController
{
    /** @var int[] */
    private const PER_PAGE_OPTS = [5, 10, 25, 50, 100];

    protected function authCallback(): void
    {
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     */
    public function get(): void
    {
        // Administrator Id
        $adminId = $this->input()->getInt("admin", unSigned: true) ?? 0;

        // Administrator Privileges
        if ($adminId !== $this->admin->id) {
            $privileges = $this->admin->privileges();
            if (!$privileges->isRoot() && !$privileges->viewAdminsLogs) {
                throw new AdminAPIException('You are not privileged to view other administrator logs');
            }
        }

        // Status
        if($this->input()->has("archived")) {
            $archivedStatus = $this->input()->getInt("archived", unSigned: true);
        }

        // Filtering
        $searchKey = strtolower($this->input()->getASCII("key"));
        if ($searchKey) {
            if (!in_array($searchKey, ["ip", "token"])) {
                throw AdminAPIException::Param("key", "Invalid sessions search key");
            }

            try {
                $searchValue = $this->input()->getASCII("value");
                if (!$searchValue) {
                    throw new AdminAPIException('Search value is required');
                } elseif (strlen($searchValue) > 128) {
                    throw new AdminAPIException('Search value cannot exceed 128 bytes');
                }
            } catch (AdminAPIException $e) {
                $e->setParam("value");
                throw $e;
            }
        }

        // Sort By
        $sortBy = strtolower($this->input()->getASCII("sort"));
        if (!in_array($sortBy, ["issued_on", "last_used_on"])) {
            throw AdminAPIException::Param("sort", "Invalid sort by column");
        }

        // Page Sorting
        $pageNum = $this->input()->getInt("page", true) ?? 1;
        $perPage = $this->input()->getInt("perPage", true) ?? self::PER_PAGE_OPTS[0];
        if (!in_array($perPage, self::PER_PAGE_OPTS)) {
            throw new AdminAPIException('Invalid value for "perPage" param');
        }

        // Result Prep
        $result = [
            "totalRows" => 0,
            "page" => null,
            "rows" => null
        ];

        // Query Builder
        $whereQuery = "1";
        $whereData = [];

        if ($adminId > 0) {
            $whereQuery .= ' AND `admin_id`=?';
            $whereData[] = $adminId;
        }

        if (isset($archivedStatus) && in_array($archivedStatus, [1, 0])) {
            $whereQuery .= ' AND `archived`=?';
            $whereData[] = $archivedStatus;
        }

        if ($searchKey && isset($searchValue)) {
            if ($searchKey === "ip") {
                $whereQuery .= ' AND `ip_address` LIKE ?';
                $whereData[] = sprintf("%%%s%%", $searchValue);
            } elseif ($searchKey === "token") {
                $whereQuery .= ' AND HEX(`token`) LIKE ?';
                $whereData[] = sprintf("%%%s%%", $searchValue);
            }
        }

        try {
            $sessionsQuery = $this->aK->db->primary()->query()
                ->table(\App\Common\Database\Primary\Admin\Sessions::TABLE)
                ->where($whereQuery, $whereData)
                ->desc($sortBy)
                ->start(($pageNum * $perPage) - $perPage)
                ->limit($perPage)
                ->paginate();

            $result["totalRows"] = $sessionsQuery->totalRows();
            $result["page"] = $pageNum;
            $result["perPage"] = $perPage;
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AdminAPIException('Failed to execute sessions fetch query');
        }

        $sessions = [];
        foreach ($sessionsQuery->rows() as $row) {
            unset($session, $sessionToken);

            try {
                $session = new Session($row);
                $session->checksumHealth = $session->checksum()->raw() === $session->private("checksum");
                $sessionToken = bin2hex($session->private("token"));
                $session->partialToken = sprintf("%s...%s", substr($sessionToken, 0, 4), substr($sessionToken, -4));
                unset($session->last2faCode);
                $sessions[] = $session;
            } catch (\Exception $e) {
                $this->aK->errors->trigger($e, E_USER_WARNING);
                continue;
            }
        }

        $result["rows"] = $sessions;

        $this->status(true);
        $this->response->set("sessions", $result);
    }
}
