<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Log\LoggingServiceProvider;
use MelodicWeb\Middleware\RequestTimingMiddleware;
use Melodic\View\ViewEngine;

// Bootstrap the application
$app = new Application(dirname(__DIR__));
$app->loadConfig('config/config.json');

// Register service providers
$app->register(new LoggingServiceProvider());

// Register services in the DI container
$app->services(function ($container) use ($app) {
    // View engine
    $container->singleton(ViewEngine::class, function () use ($app) {
        return new ViewEngine($app->getBasePath() . '/views');
    });
});

// Add global middleware
$app->addMiddleware(new RequestTimingMiddleware());

// Load routes
$app->routes(require dirname(__DIR__) . '/config/routes.php');

// Run the application
$app->run();
