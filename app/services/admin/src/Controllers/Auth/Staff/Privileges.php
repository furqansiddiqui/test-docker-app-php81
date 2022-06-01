<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth\Staff;

use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;

/**
 * Class Privileges
 * @package App\Services\Admin\Controllers\Auth\Staff
 */
class Privileges extends AbstractEditStaffController
{
    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Security\Exception\CipherException
     */
    public function post(): void
    {
        // New set of permissions
        $privileges = $this->editStaff->privileges();
        $current = \App\Common\Admin\Privileges::Permissions($this->editStaff);
        $changes = 0;
        foreach ($current as $key => $permission) {
            if (property_exists($privileges, $key)) {
                if ($privileges->$key !== $permission["current"]) {
                    $changes++;
                }

                $privileges->$key = Validator::getBool($this->input()->getASCII($key));
            }
        }

        // Changes?
        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->editStaff->set("privileges", $this->editStaff->cipher()->encrypt($privileges)->raw());
            $this->editStaff->timeStamp = time();
            $this->editStaff->query()->update();

            $this->adminLogEntry(
                sprintf('Staff "%s" privileges updated', $this->editStaff->email),
                flags: ["staff:" . $this->editStaff->id, "staff-privileges"]
            );

            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
        $this->purgeCachedStaff();
    }

    /**
     * @return void
     * @throws \App\Common\Exception\AppException
     */
    public function get(): void
    {
        $this->status(true);
        $this->response->set("permissions", \App\Common\Admin\Privileges::Permissions($this->editStaff));
    }
}
