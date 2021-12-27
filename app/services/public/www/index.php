<?php
declare(strict_types=1);

/** @noinspection PhpIncludeInspection */
require "../vendor/autoload.php";

try {
    $aK = \App\Services\Public\PublicAPIService::Bootstrap();
    $router = $aK->http->router;

    $defaultRoute = $router->route('/*', 'App\Services\Public\Controllers\*')
        ->fallbackController('App\Services\Public\Controllers\Hello');

    \Comely\Http\RESTful::Request($router, function (\Comely\Http\Router\AbstractController $page) use ($router) {
        $router->response->send($page);
    });
} catch (Exception $e) {
    /** @noinspection PhpUnhandledExceptionInspection */
    throw $e;
}
