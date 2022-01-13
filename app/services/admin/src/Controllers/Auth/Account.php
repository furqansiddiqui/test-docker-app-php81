<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Packages\GoogleAuth\GoogleAuthenticator;
use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Cache\Exception\CacheException;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Account
 * @package App\Services\Admin\Controllers\Auth
 */
class Account extends AuthAdminAPIController
{
    /**
     * @return void
     */
    protected function authCallback(): void
    {
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function editAccount(): void
    {
        $changes = 0;

        // Phone number
        try {
            $phone = $this->input()->getASCII("phone");
            if (!$phone) {
                throw new AdminAPIException('Your phone number is required');
            }

            if (!Validator::isValidPhone($phone)) {
                throw new AdminAPIException('Phone number is in invalid format');
            }

            if ($this->admin->phone !== $phone) {
                $this->admin->phone = $phone;
                $changes++;
            }
        } catch (AdminAPIException $e) {
            $e->setParam("phone");
            throw $e;
        }

        // Changes?
        if (!$changes) {
            throw new AdminAPIException('There are no changes to be saved');
        }

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->admin->timeStamp = time();
            $this->admin->set("checksum", $this->admin->checksum()->raw());
            $this->admin->query()->update();
            $this->adminLogEntry("Phone number updated", flags: ["account"]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function changePassword(): void
    {
        $credentials = $this->admin->credentials();
        $passwordValidator = Validator::Password();

        // TOTP Semaphore Lock
        $this->totpResourceLock();

        // New password
        try {
            try {
                $newPassword = $passwordValidator->getValidated($this->input()->getUnsafe("newPassword"));
            } catch (ValidatorException $e) {
                throw match ($e->getCode()) {
                    \Comely\Utils\Validator\Validator::LENGTH_UNDERFLOW_ERROR => new AdminAPIException('Entered password is too small'),
                    \Comely\Utils\Validator\Validator::LENGTH_OVERFLOW_ERROR => new AdminAPIException('Entered password is too long'),
                    \Comely\Utils\Validator\Validator::CALLBACK_TYPE_ERROR => new AdminAPIException('Entered password is not strong enough'),
                    default => new AdminAPIException('Invalid new password'),
                };
            }

            if ($credentials->verifyPassword($newPassword)) {
                throw new AdminAPIException('Entered new password is same as existing');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("newPassword");
            throw $e;
        }

        // Retype New Password
        try {
            $retypeNewPassword = $this->input()->getASCII("retypeNewPassword");
            if (!$retypeNewPassword) {
                throw new AdminAPIException('Retype your new password');
            }

            if ($retypeNewPassword !== $newPassword) {
                throw new AdminAPIException('Passwords do not match');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("retypeNewPassword");
            throw $e;
        }

        // Verify TOTP
        $this->totpVerify($this->input()->getASCII("totp"));

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $credentials->changePassword($newPassword);
            $this->admin->set("credentials", $this->admin->cipher()->encrypt($credentials)->raw());
            $this->admin->timeStamp = time();
            $this->admin->set("checksum", $this->admin->checksum()->raw());
            $this->admin->query()->update();
            $this->adminLogEntry("Password changed", flags: ["account", "pw-change"]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    private function changeAuthSeed(): void
    {
        $credentials = $this->admin->credentials();
        $suggestedSeedKey = sprintf("admin_%d_suggestedSeed", $this->admin->id);

        // TOTP Semaphore Lock
        $this->totpResourceLock();

        try {
            $suggestedSeed = $this->aK->cache->get($suggestedSeedKey);
            if ($suggestedSeed === $credentials->getGoogleAuthSeed()) {
                throw new \RuntimeException();
            }
        } catch (CacheException) {
            throw new AdminAPIException('Suggested GoogleAuth seed has expired');
        }

        // GoogleAuth
        $googleAuth = new GoogleAuthenticator($suggestedSeed);

        // Verify new TOTP seed
        try {
            $totpCode = $this->input()->getASCII("newTotp");
            if (!$totpCode) {
                throw new AdminAPIException('Enter TOTP code from new seed');
            }

            if (!preg_match('/^[0-9]{6}$/', $totpCode)) {
                throw new AdminAPIException('Invalid TOTP code');
            }

            if (!$googleAuth->verify($totpCode)) {
                throw new AdminAPIException('Incorrect TOTP code; Enter the code from new seed');
            }
        } catch (AdminAPIException $e) {
            $e->setParam("newTotp");
            throw $e;
        }

        // Verify Existing TOTP
        $this->totpVerify($this->input()->getASCII("currentTotp"), "currentTotp");

        // Save Changes
        $db = $this->aK->db->primary();
        $db->beginTransaction();

        try {
            $this->aK->cache->delete($suggestedSeedKey);

            $credentials->changeGoogleAuthSeed($suggestedSeed);
            $this->admin->set("credentials", $this->admin->cipher()->encrypt($credentials)->raw());
            $this->admin->timeStamp = time();
            $this->admin->set("checksum", $this->admin->checksum()->raw());
            $this->admin->query()->update();
            $this->adminLogEntry("GoogleAuth 2FA seed changed", flags: ["account", "2fa-change"]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws ValidatorException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\PDO_Exception
     */
    protected function post(): void
    {
        $method = $this->input()->getASCII("action");
        switch ($method) {
            case "account":
                $this->editAccount();
                return;
            case "password":
                $this->changePassword();
                return;
            case "auth_seed":
                $this->changeAuthSeed();
                return;
            default:
                throw new AdminAPIException('Invalid action');
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     */
    protected function get(): void
    {
        // Suggested Seed
        $suggestedSeed = GoogleAuthenticator::generateSecret();

        try {
            $this->aK->cache->set(sprintf("admin_%d_suggestedSeed", $this->admin->id), $suggestedSeed, 300);
        } catch (CacheException) {
            throw new AdminAPIException('Failed to store suggested seed in cache');
        }

        $this->status(true);
        $this->response->set("admin", [
            "email" => $this->admin->email,
            "phone" => $this->admin->phone,
        ]);

        $this->response->set("suggestedAuthSeed", $suggestedSeed);
    }
}
