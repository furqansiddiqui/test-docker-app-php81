<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers\Auth;

use App\Common\Admin\Administrator;
use App\Common\Admin\Log;
use App\Common\AppConstants;
use App\Common\Database\Primary\Admin\Logs;
use App\Common\Database\Primary\Administrators;
use App\Common\Exception\AppException;
use App\Services\Admin\Controllers\AbstractAdminAPIController;
use App\Services\Admin\Exception\AdminAPIException;
use FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException;
use FurqanSiddiqui\SemaphoreEmulator\ResourceLock;

/**
 * Class AuthAdminAPIController
 * @package App\Services\Admin\Controllers\Auth
 */
abstract class AuthAdminAPIController extends AbstractAdminAPIController
{
    /** @var array */
    protected const CHECKSUM_IGNORE_PARAMS = [];

    /** @var Administrator */
    protected readonly Administrator $admin;
    /** @var ResourceLock|null */
    private ?ResourceLock $sL = null;

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     */
    final protected function adminAPICallback(): void
    {
        if (!$this->session) {
            throw new AdminAPIException('SESSION_TOKEN_REQ');
        }

        $this->admin = Administrators::Get($this->session->adminId);
        $this->admin->validateChecksum();

        // Administration is enabled?
        if ($this->admin->status !== 1) {
            throw new AdminAPIException('ADMIN_DISABLED');
        }

        // Cross-check session IDs; (Administrator may have logged in from elsewhere)
        if ($this->admin->private($this->session->type . "AuthSession") !== $this->session->private("token")) {
            throw new AdminAPIException('SESSION_REDUNDANT');
        }

        // Timely TOTP Refresh Required?
        if ($this->session->type === "web") {
            if ($this->admin->credentials()->getGoogleAuthSeed()) {
                if ((time() - $this->session->lastUsedOn) >= 600) {
                    $this->response->set("lastActivity", $this->session->lastUsedOn);
                    throw new AdminAPIException('TOTP_REFRESH_REQUIRED');
                }
            }
        }

        // Validate user signature
        $this->validateUserSignature(static::CHECKSUM_IGNORE_PARAMS);

        // Authenticated callback
        $this->authCallback();
    }

    /**
     * @param array $excludeBodyParams
     * @return void
     * @throws AdminAPIException
     */
    private function validateUserSignature(array $excludeBodyParams = []): void
    {
        $userSecret = strval($this->admin->private($this->session->type . "AuthSecret"));
        if (strlen($userSecret) !== 16) {
            throw new AdminAPIException('No secret value set for administrator HMAC');
        }

        // Prepare exclude vars
        $excludeBodyParams = array_map("strtolower", $excludeBodyParams);

        // Request params
        $payload = [];
        foreach ($this->input()->array() as $key => $value) {
            if (in_array(strtolower($key), $excludeBodyParams)) {
                $value = "";
            }

            $payload[$key] = $value;
        }

        $queryString = http_build_query($payload, "", "&", PHP_QUERY_RFC3986);

        // Calculate HMAC
        $hmac = hash_hmac("sha512", $queryString, $userSecret, false);
        if (!$hmac) {
            throw new AdminAPIException('Failed to generate cross-check HMAC signature');
        }

        if ($this->httpHeaderAuth[AppConstants::ADMIN_API_HEADER_CLIENT_SIGN] !== $hmac) {
            throw new AdminAPIException('HMAC signature validation failed');
        }

        // Timestamp
        $requestTs = $this->input()->getInt("timeStamp");
        $requestTsAge = time() - $requestTs;
        if ($requestTsAge >= 4) {
            throw new AdminAPIException(sprintf('The request query has expired, -%d seconds', $requestTsAge));
        }
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     */
    protected function totpResourceLock(): void
    {
        try {
            $this->obtainSemaphoreLock(sprintf("admin_%d_totp_controller", $this->admin->id), 0.5, 10);
        } catch (ResourceLockException) {
            throw new AdminAPIException('TOTP locked resource is busy; Please try again');
        }
    }

    /**
     * @param string $resourceId
     * @param float|null $checkEveryNSeconds
     * @param int|null $maxTimeout
     * @param bool $autoRelease
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ConcurrentRequestBlocked
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException
     */
    protected function obtainSemaphoreLock(string $resourceId, ?float $checkEveryNSeconds = null, ?int $maxTimeout = 30, bool $autoRelease = true): void
    {
        if ($this->sL) {
            throw new AdminAPIException('Semaphore resource lock already set; Cannot override');
        }

        $this->sL = $this->aK->semaphoreEmulator()->obtainLock($resourceId, $checkEveryNSeconds, $maxTimeout);
        if ($autoRelease) {
            $this->sL->setAutoRelease();
        }
    }

    /**
     * @return void
     * @throws \FurqanSiddiqui\SemaphoreEmulator\Exception\ResourceLockException
     */
    protected function releaseSemaphoreLock(): void
    {
        if ($this->sL) {
            $this->sL->release();
            $this->sL = null;
        }
    }

    /**
     * @param int $period
     * @return void
     * @throws AdminAPIException
     */
    protected function totpLastCheck(int $period = 300): void
    {
        try {
            if (!$this->session->last2faOn) {
                throw new \RuntimeException();
            }

            if ((time() - $this->session->last2faOn) >= $period) {
                throw new \RuntimeException();
            }
        } catch (\RuntimeException) {
            throw new AdminAPIException('TOTP_REQUIRED');
        }
    }

    /**
     * @param mixed $code
     * @param string|null $param
     * @return void
     * @throws AdminAPIException
     * @throws AppException
     */
    protected function totpVerify(mixed $code, ?string $param = "totp"): void
    {
        if (is_int($code)) {
            $code = strval($code);
        }

        try {
            if (!is_string($code) || !preg_match('/^[0-9]{6}$/', $code)) {
                throw new AdminAPIException('Invalid TOTP code');
            }

            if ($code === $this->session->last2faCode) {
                throw new AdminAPIException('This TOTP code has already been consumed');
            }

            if (!$this->admin->credentials()->verifyTotp($code)) {
                throw new AdminAPIException('Incorrect TOTP code');
            }
        } catch (AdminAPIException $e) {
            if ($param) {
                $e->setParam($param);
            }

            throw $e;
        }

        $this->session->last2faOn = time();
        $this->session->last2faCode = $code;
    }

    /**
     * @return void
     */
    abstract protected function authCallback(): void;

    /**
     * @param string $message
     * @param string|null $controller
     * @param int|null $line
     * @param array $flags
     * @return Log
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\DatabaseException
     */
    final protected function adminLogEntry(string $message, ?string $controller = null, ?int $line = null, array $flags = []): Log
    {
        try {
            return Logs::Insert($this->admin, $this->ipAddress, $message, $controller, $line, $flags);
        } catch (AppException $e) {
            throw new AdminAPIException($e->getMessage(), $e->getCode());
        }
    }
}
