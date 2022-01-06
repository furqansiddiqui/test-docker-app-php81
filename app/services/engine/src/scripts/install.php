<?php
declare(strict_types=1);

namespace bin;

use App\Common\Admin\Administrator;
use App\Common\Admin\Credentials;
use App\Common\Admin\Privileges;
use App\Common\AppConstants;
use App\Common\Database\Primary\Administrators;
use App\Common\Validator;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Utils\ASCII;

/**
 * Class install
 * @package bin
 */
class install extends abstract_db_builder_script
{
    /**
     * @return void
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     * @throws \Comely\Security\Exception\CipherException
     */
    public function exec(): void
    {
        // Create database tables
        $this->createDbTables();
        $this->print("");

        // Check if administrator account exists
        try {
            $admins = Administrators::Find()->query("WHERE 1")->limit(1)->all();
        } catch (ORM_ModelNotFoundException) {
            $admins = [];
        }

        if ($admins) { // Already exists?
            $this->print("{yellow}Administration accounts already exists!");
            $this->print("{grey}Skip installation process...");
            return;
        }

        // Create admin account
        $this->print("Creating an administration account...");
        $adminEmail = null;
        $adminPassword = null;
        while (!$adminEmail) {
            $inputEmail = trim(strval($this->requireInput("{yellow}Administrator E-mail Address?{/} {cyan}")));
            if (Validator::isValidEmailAddress($inputEmail)) {
                $adminEmail = $inputEmail;
                break;
            }

            $this->print("{red}Invalid e-mail address")->eol();
        }

        while (!$adminPassword) {
            $inputPassword = trim(strval($this->requireInput("{yellow}Password:{/} {cyan}")));
            $pwLen = strlen($inputPassword);
            try {
                if (!ASCII::isPrintableOnly($inputPassword)) {
                    throw new \RuntimeException('Password contains an invalid character');
                } elseif ($pwLen < 8 || $pwLen > 32) {
                    throw new \RuntimeException('Password must be between 8 to 32 characters log');
                }
            } catch (\RuntimeException $e) {
                $this->print("{red}" . $e->getMessage())->eol();
                continue;
            }

            $adminPassword = $inputPassword;
            break;
        }

        // Admin account
        $admin = new Administrator();
        $admin->id = AppConstants::ROOT_ADMINISTRATORS[0];
        $admin->status = 1;
        $admin->email = $adminEmail;
        $admin->timeStamp = time();

        $credentials = new Credentials($admin);
        $credentials->changePassword($adminPassword);
        $privileges = new Privileges($admin);

        $admin->set("credentials", $admin->cipher()->encrypt($credentials)->raw());
        $admin->set("privileges", $admin->cipher()->encrypt($privileges)->raw());
        $admin->set("checksum", $admin->checksum()->raw());
        $admin->query()->insert();

        $this->print("");
        $this->print("{grey}Administrator account has been created!");
        $this->print("{green}Installation finished!");
    }
}
