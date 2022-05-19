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
