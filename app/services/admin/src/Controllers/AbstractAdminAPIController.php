<?php
declare(strict_types=1);

namespace App\Services\Admin\Controllers;

use App\Common\Exception\AppControllerException;
use App\Common\Kernel\Http\Controllers\AbstractAppController;
use App\Common\Validator;
use App\Services\Admin\AdminAPIService;
use App\Services\Admin\Exception\AdminAPIException;

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

    /**
     * @return void
     */
    public function callback(): void
    {
        // AppKernel instance
        $this->aK = AdminAPIService::getInstance();

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
            $this->response->set("error", $e->getMessage());

            if ($e instanceof AdminAPIException) {
                $param = $e->getParam();
                if ($param) {
                    $this->response->set("param", $param);
                }
            }

            if ($this->aK->isDebug()) {
                $this->response->set("caught", get_class($e));
                $this->response->set("file", $e->getFile());
                $this->response->set("line", $e->getLine());
                $this->response->set("trace", $this->getExceptionTrace($e));
            }
        }

        $displayErrors = $this->aK->isDebug() ?
            $this->aK->errors->all() :
            $this->aK->errors->triggered()->array();

        if ($displayErrors) {
            $this->response->set("errors", $displayErrors); // Errors
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
     */
    final protected function onLoad(): void
    {
        // Validate IP Address
        if (!Validator::isValidIP($this->userClient->ipAddress)) {
            throw new AdminAPIException('INVALID_IP_ADDRESS');
        }

        // Public API callback
        $this->adminAPICallback();
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
