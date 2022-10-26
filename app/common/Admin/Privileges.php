<?php
declare(strict_types=1);

namespace App\Common\Admin;

use App\Common\AppConstants;

/**
 * Class Privileges
 * @package App\Common\Admin
 */
class Privileges
{
    /** @var int */
    public readonly int $adminId;
    /** @var bool */
    public bool $viewConfig = false;
    /** @var bool */
    public bool $editConfig = false;
    /** @var bool */
    public bool $viewAdminsLogs = false;
    /** @var bool */
    public bool $viewUsers = false;
    /** @var bool */
    public bool $manageUsers = false;
    /** @var bool */
    public bool $viewAPIQueriesPayload = false;
    /** @var bool */
    public bool $downloadDbBackups = false;

    /**
     * @param Administrator $admin
     * @return array
     * @throws \App\Common\Exception\AppException
     */
    public static function Permissions(Administrator $admin): array
    {
        $privileges = $admin->privileges();
        return [
            "viewConfig" => self::PrivilegeDetail("View configured settings", $privileges->viewConfig, isSensitive: true),
            "editConfig" => self::PrivilegeDetail("Make changes to configuration setup", $privileges->editConfig, isCritical: true),
            "viewAdminsLogs" => self::PrivilegeDetail("View other administrators activity log", $privileges->viewAdminsLogs),
            "viewUsers" => self::PrivilegeDetail("Browse and view users information", $privileges->viewUsers),
            "manageUsers" => self::PrivilegeDetail("Make changes to a user account", $privileges->manageUsers, isSensitive: true),
            "viewAPIQueriesPayload" => self::PrivilegeDetail("View API queries and payloads", $privileges->viewAPIQueriesPayload, isCritical: true),
            "downloadDbBackups" => self::PrivilegeDetail("Download database backups", $privileges->viewAPIQueriesPayload, isCritical: true),
        ];
    }

    /**
     * @param string|null $desc
     * @param bool $current
     * @param bool $isCritical
     * @param bool $isSensitive
     * @return array
     */
    private static function PrivilegeDetail(?string $desc, bool $current, bool $isCritical = false, bool $isSensitive = false): array
    {
        $type = 0x00;
        if ($isSensitive) {
            $type = 0x01;
        } elseif ($isCritical) {
            $type = 0x02;
        }

        return [
            "desc" => $desc,
            "current" => $current,
            "type" => $type
        ];
    }

    /**
     * @param Administrator $admin
     */
    public function __construct(Administrator $admin)
    {
        $this->adminId = $admin->id;
    }

    /**
     * @return bool
     */
    public function isRoot(): bool
    {
        return in_array($this->adminId, AppConstants::ROOT_ADMINISTRATORS);
    }
}
