<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers;

use App\Common\Admin\Session;
use App\Common\Database\Primary\Admin\Logs;
use App\Common\Database\Primary\Admin\Sessions;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Common\Packages\GoogleAuth\GoogleAuthenticator;
use App\Common\Validator;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Security\Passwords;
use Comely\Security\PRNG;
use Comely\Utils\Validator\Exception\ValidatorException;

/**
 * Class Signin
 * @package App\Services\Admin\Controllers
 */
class Signin extends AbstractAdminAPIController
{
    /**
     * @return void
     * @throws AdminAPIException
     */
    protected function adminAPICallback(): void
    {
        if ($this->session) {
            throw new AdminAPIException('Do not send session token to /signin endpoint');
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws ValidatorException
     * @throws \Comely\Database\Exception\DbConnectionException
     * @throws \Comely\Database\Exception\DbQueryException
     * @throws \Comely\Database\Exception\PDO_Exception
     * @throws \Comely\Security\Exception\PRNG_Exception
     */
    public function post(): void
    {
        $emailValidator = Validator::EmailAddress();
        $passwordValidator = Validator::Password();

        try {
            $email = $emailValidator->getValidated($this->input()->getASCII("email"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("email", "Invalid e-mail address", $e->getCode());
        }

        try {
            $password = $passwordValidator->getValidated($this->input()->getASCII("password"));
        } catch (ValidatorException $e) {
            throw AdminAPIException::Param("password", "Invalid entered password", $e->getCode());
        }

        try {
            try {
                $admin = Administrators::Email($email);
            } catch (AppException) {
                throw AdminAPIException::Param("email", "Administrator account does not exist");
            }

            // Validate checksum
            $admin->validateChecksum();

            if (!$admin->credentials()->verifyPassword($password)) {
                throw AdminAPIException::Param("password", "Incorrect password");
            }
        } catch (AdminAPIException) {
            // Hide specific error against brute forcing
            throw new AdminAPIException("Incorrect e-mail address or password");
        }

        // TOTP check?
        $googleAuthSeed = $admin->credentials()->getGoogleAuthSeed();
        if ($googleAuthSeed) { // Disabled Google2FA check on login for now
            // if ($googleAuthSeed) {
            $totpCode = $this->input()->getASCII("totp");
            if (!preg_match('/^[0-9]{6}$/', $totpCode)) {
                throw AdminAPIException::Param("totp", "Invalid 2FA/TOTP code");
            }

            $googleAuth = new GoogleAuthenticator($googleAuthSeed);
            if (!$googleAuth->verify($totpCode)) {
                throw AdminAPIException::Param("totp", "Incorrect 2FA/TOTP code");
            }
        }

        // Create a new session;
        $db = $this->aK->db->primary();
        $timeStamp = time();
        $secureEntropy = PRNG::randomBytes(32);
        $hmacSecret = Passwords::Random(length: 16, allowQuotes: false);

        // Check if any token was issued in last N seconds
        $recentIssued2IPTokens = $db->query()->table(Sessions::TABLE)
            ->where('`ip_address`=? AND `issued_on`>=?', [$this->ipAddress, $timeStamp - 60])
            ->fetch();

        if ($recentIssued2IPTokens->count()) {
            throw new AdminAPIException("Creating session tokens too fast; Timed out");
        }

        $db->beginTransaction();

        try {
            $session = new Session();
            $session->id = 0;
            $session->set("checksum", "tba");
            $session->type = $this->getAccessAppDeviceType();
            $session->archived = 0;
            $session->set("token", $secureEntropy);
            $session->adminId = $admin->id;
            $session->ipAddress = $this->userClient->ipAddress;
            $session->issuedOn = $timeStamp;
            $session->lastUsedOn = $timeStamp;
            if (isset($totpCode)) {
                $session->last2faCode = $totpCode;
                $session->last2faOn = $timeStamp;
            }

            $session->query()->insert();

            $session->id = $db->lastInsertId();
            $session->set("checksum", $session->checksum()->raw());
            $session->query()->where("id", $session->id)->update();

            $admin->set($session->type . "AuthSession", $secureEntropy);
            $admin->set($session->type . "AuthSecret", $hmacSecret);
            $admin->timeStamp = time();
            $admin->query()->update();

            Logs::Insert($admin, $this->ipAddress, "Logged In", flags: ["signin", "auth"]);
            $db->commit();
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }

        $this->status(true);
        $this->response->set("token", bin2hex($secureEntropy));
        $this->response->set("secret", $hmacSecret);
    }
}
