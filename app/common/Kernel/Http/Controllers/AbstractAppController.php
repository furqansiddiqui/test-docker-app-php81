<?php
declare(strict_types=1);

namespace App\Common\Kernel\Http\Controllers;

use Comely\Http\Router;
use Comely\Http\Router\AbstractController;
use Comely\Http\Router\Request;

/**
 * Class AbstractAppController
 * @package App\Common\Kernel\Http\Controllers
 */
abstract class AbstractAppController extends AbstractController
{
    /** @var RemoteClient */
    protected readonly RemoteClient $userClient;

    /**
     * @param Router $router
     * @param Request $request
     * @param AbstractController|null $prev
     * @param string|null $entryPoint
     * @param RemoteClient|null $remoteClient
     * @throws \Comely\Http\Exception\ControllerException
     */
    public function __construct(
        Router              $router,
        Request             $request,
        ?AbstractController $prev = null,
        ?string             $entryPoint = null,
        ?RemoteClient       $remoteClient = null
    )
    {
        if (!$remoteClient) {
            $remoteClient = new RemoteClient($request);
        }

        $this->userClient = $remoteClient;

        parent::__construct($router, $request, $prev, $entryPoint);
    }

    /**
     * @param \Exception $e
     * @return array
     */
    protected function getExceptionTrace(\Exception $e): array
    {
        return array_map(function (array $trace) {
            unset($trace["args"]);
            return $trace;
        }, $e->getTrace());
    }
}
