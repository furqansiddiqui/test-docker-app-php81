<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers;

use App\Common\AppConstants;
use App\Common\Database\Primary\Admin\Sessions;
use App\Common\Exception\AppControllerException;
use App\Common\Kernel\Http\Controllers\AbstractAppController;
use App\Common\Validator;
use App\Services\Admin\AdminAPIService;
use App\Services\Admin\Exception\AdminAPIException;
use Comely\Database\Exception\ORM_ModelNotFoundException;
use Comely\Database\Schema;

/**
 * Class AbstractAdminAPIController
 * @method get(): void
 * @method post(): void
 * @method put(): void
 * @method delete(): void
 * @package App\Services\Public
 */
abstract class AbstractAdminAPIController extends AbstractAppController
{
    /** @var AdminAPIService */
    protected readonly AdminAPIService $aK;
    /** @var array */
    protected readonly array $httpHeaderAuth;
    /** @var \App\Common\Admin\Session|null */
    protected readonly ?\App\Common\Admin\Session $session;
    /** @var string */
    protected readonly string $ipAddress;

    /**
     * @return void
     * @throws \Comely\Database\Exception\DbConnectionException
     */
    public function callback(): void
    {
        // AppKernel instance
        $this->aK = AdminAPIService::getInstance();

        // Database tables
        $db = $this->aK->db->primary();
        Schema::Bind($db, 'App\Common\Database\Primary\Administrators');
        Schema::Bind($db, 'App\Common\Database\Primary\Admin\Logs');
        Schema::Bind($db, 'App\Common\Database\Primary\Admin\Sessions');

        // Http Authorization Headers
        $authHeaders = [];
        $authTokens = explode(",", strval($this->request->headers->get("authorization")));
        foreach ($authTokens as $authToken) {
            $authToken = explode(" ", trim($authToken));
            $authHeaders[strtolower($authToken[0])] = trim(strval($authToken[1] ?? null));
        }

        $this->httpHeaderAuth = $authHeaders;

        // Default response type (despite any ACCEPT header)
        $this->response->header("content-type", "application/json");

        // Prepare response
        $this->response->set("status", false);

        // Controller method
        $httpRequestMethod = strtolower($this->request->method->toString());

        // Execute
        try {
            if (!method_exists($this, $httpRequestMethod)) {
                if ($httpRequestMethod === "options") {
                    $this->response->set("status", true);
                    $this->response->set("options", []);
                    return;
                } else {
                    throw new AppControllerException(
                        sprintf('Endpoint "%s" does not support "%s" method', static::class, strtoupper($httpRequestMethod))
                    );
                }
            }

            $this->onLoad(); // Event callback: onLoad
            call_user_func([$this, $httpRequestMethod]);
        } catch (\Exception $e) {
            $this->response->set("status", false);
            $exception = [
                "message" => $e->getMessage()
            ];

            if ($e instanceof AdminAPIException) {
                $param = $e->getParam();
                if ($param) {
                    $exception["param"] = $param;
                }
            }

            if ($this->aK->isDebug()) {
                $exception["caught"] = get_class($e);
                $exception["code"] = $e->getCode();
                $exception["file"] = $e->getFile();
                $exception["line"] = $e->getLine();
                $exception["trace"] = $this->getExceptionTrace($e);
            }

            $this->response->set("exception", $exception);
        }

        $displayErrors = $this->aK->isDebug() ?
            $this->aK->errors->all() :
            $this->aK->errors->triggered()->array();

        if ($displayErrors) {
            $this->response->set("warnings", $displayErrors); // Errors
        }

        $this->onFinish(); // Event callback: onFinish
    }

    /**
     * @param bool $status
     * @return $this
     */
    protected function status(bool $status): static
    {
        $this->response->set("status", $status);
        return $this;
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     */
    final protected function onLoad(): void
    {
        // Validate IP Address
        if (!Validator::isValidIP($this->userClient->ipAddress)) {
            throw new AdminAPIException('INVALID_IP_ADDRESS');
        }

        // Determine which IP address should be used everywhere (ipAddress vs realIpAddress prop)
        $this->ipAddress = $this->userClient->ipAddress;

        // Initiate the session
        $this->initSession();

        // Public API callback
        $this->adminAPICallback();
    }

    /**
     * @return string
     */
    final protected function getAccessAppDeviceType(): string
    {
        return "web";
    }

    /**
     * @return void
     * @throws AdminAPIException
     * @throws \App\Common\Exception\AppException
     * @throws \Comely\Database\Exception\ORM_ModelQueryException
     */
    private function initSession(): void
    {
        $sessionToken = $this->httpHeaderAuth[strtolower(AppConstants::ADMIN_API_HEADER_SESS_TOKEN)] ?? null;
        if (!$sessionToken) {
            $this->session = null;
            return;
        }

        if (!preg_match('/^[a-f0-9]{64}$/i', $sessionToken)) {
            throw new AdminAPIException('SESSION_TOKEN_INVALID');
        }

        try {
            /** @var \App\Common\Admin\Session $session */
            $session = Sessions::Find()->query('WHERE `token`=?', [hex2bin($sessionToken)])->first();
        } catch (ORM_ModelNotFoundException) {
            throw new AdminAPIException('SESSION_NOT_FOUND');
        } catch (\Exception $e) {
            $this->aK->errors->triggerIfDebug($e, E_USER_WARNING);
            throw new AdminAPIException('SESSION_RETRIEVE_ERROR');
        }

        // Validate Checksum
        if ($session->checksum()->raw() !== $session->private("checksum")) {
            throw new AdminAPIException('SESSION_CHECKSUM_FAIL');
        }

        if ($session->type !== $this->getAccessAppDeviceType()) {
            throw new AdminAPIException('SESSION_APP_TYPE_ERROR');
        }

        // Archived?
        if ($session->archived !== 0) {
            throw new AdminAPIException('SESSION_ARCHIVED');
        }

        // Match IP Address
        if ($session->ipAddress !== $this->userClient->ipAddress) {
            throw new AdminAPIException('SESSION_IP_ERROR');
        }

        // Validity
        if ($session->type !== "app" && (time() - $session->lastUsedOn) >= 3600) {
            throw new AdminAPIException('SESSION_TIMED_OUT');
        }

        // Update the session lastUsedOn & checksum
        if (static::class !== 'App\Services\Admin\Controllers\Auth\Session') {
            $session->lastUsedOn = time();
            $session->set("checksum", $session->checksum()->raw());
        }

        // Update session on query end
        register_shutdown_function([$session->query(), "update"]);

        // Set the instance
        $this->session = $session;
    }

    /**
     * @return void
     */
    abstract protected function adminAPICallback(): void;

    /**
     * @return void
     */
    protected function onFinish(): void
    {
    }
}
