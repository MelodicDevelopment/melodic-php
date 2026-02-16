<?php

declare(strict_types=1);

use Example\Controllers\DocsController;
use Example\Controllers\HomeController;
use Example\Controllers\UserApiController;
use Melodic\Routing\Router;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\OptionalWebAuthMiddleware;
use Melodic\Security\WebAuthenticationMiddleware;

return function (Router $router): void {
    // Public routes (with optional auth — user context available if logged in)
    $router->group('', function (Router $router) {
        $router->get('/', HomeController::class, 'index');
        $router->get('/about', HomeController::class, 'about');
    }, middleware: [OptionalWebAuthMiddleware::class]);

    // Documentation routes
    $router->group('', function (Router $router) {
        $router->get('/docs', DocsController::class, 'index');
        $router->get('/docs/{page}', DocsController::class, 'show');
    }, middleware: [OptionalWebAuthMiddleware::class]);

    // Auth endpoints — handled by AuthCallbackMiddleware
    $router->group('/auth', function (Router $router) {
        $router->get('/login', HomeController::class, 'index');
        $router->get('/login/{provider}', HomeController::class, 'index');
        $router->get('/callback/{provider}', HomeController::class, 'index');
        $router->post('/callback/{provider}', HomeController::class, 'index');
        $router->get('/logout', HomeController::class, 'index');
    }, middleware: [AuthCallbackMiddleware::class]);

    // Protected web routes
    $router->group('/admin', function (Router $router) {
        $router->get('/dashboard', HomeController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);

    // API routes
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserApiController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
};
