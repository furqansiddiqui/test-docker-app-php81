<?php
declare(strict_types=1);

/** @noinspection PhpIncludeInspection */
require "../vendor/autoload.php";

try {
    $aK = \App\Services\Admin\AdminAPIService::Bootstrap();
    $router = $aK->http->router;

    $defaultRoute = $router->route('/*', 'App\Services\Admin\Controllers\*')
        ->fallbackController('App\Services\Admin\Controllers\Test');

    \Comely\Http\RESTful::Request($router, function (\Comely\Http\Router\AbstractController $page) use ($router) {
        $router->response->send($page);
    });
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
