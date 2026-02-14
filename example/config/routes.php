<?php

declare(strict_types=1);

use Example\Controllers\HomeController;
use Example\Controllers\UserApiController;
use Melodic\Routing\Router;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\OAuthCallbackMiddleware;
use Melodic\Security\WebAuthenticationMiddleware;

return function (Router $router): void {
    // Public routes (no auth)
    $router->get('/', HomeController::class, 'index');
    $router->get('/about', HomeController::class, 'about');

    // Auth endpoints (login + callback) — handled by OAuthCallbackMiddleware
    $router->group('/auth', function (Router $router) {
        $router->get('/login', HomeController::class, 'index');
        $router->get('/callback', HomeController::class, 'index');
    }, middleware: [OAuthCallbackMiddleware::class]);

    // Protected web routes
    $router->group('/admin', function (Router $router) {
        $router->get('/dashboard', HomeController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);

    // API routes
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserApiController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
};
