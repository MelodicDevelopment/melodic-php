<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Example\Security\ExampleLoginRenderer;
use Example\Services\UserService;
use Example\Services\UserServiceInterface;
use Melodic\Core\Application;
use Melodic\Data\DbContext;
use Melodic\Data\DbContextInterface;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Security\AuthLoginRendererInterface;
use Melodic\Security\SecurityServiceProvider;
use Example\Middleware\RequestTimingMiddleware;
use Melodic\View\ViewEngine;

// Bootstrap the application
$app = new Application(dirname(__DIR__));
$app->loadConfig('config/config.json');

// Register the security service provider (wires OIDC, JWT, OAuth, middleware)
$app->register(new SecurityServiceProvider());

// Register services in the DI container
$app->services(function ($container) use ($app) {
    // Database — SQLite in-memory for demo
    $container->singleton(DbContextInterface::class, function () use ($app) {
        $pdo = new PDO(
            $app->config('database.dsn'),
            $app->config('database.username'),
            $app->config('database.password'),
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create demo table
        $pdo->exec('CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL,
            email TEXT NOT NULL,
            created_at TEXT NOT NULL
        )');

        // Seed demo data
        $pdo->exec("INSERT INTO users (username, email, created_at) VALUES
            ('alice', 'alice@example.com', '2025-01-01 00:00:00'),
            ('bob', 'bob@example.com', '2025-01-02 00:00:00')
        ");

        return new DbContext($pdo);
    });

    // View engine
    $container->singleton(ViewEngine::class, function () use ($app) {
        return new ViewEngine($app->getBasePath() . '/views');
    });

    // Services
    $container->bind(UserServiceInterface::class, UserService::class);

    // Custom login page renderer (overrides the framework default)
    $container->singleton(AuthLoginRendererInterface::class, ExampleLoginRenderer::class);
});

// Add global middleware (applied to all requests)
$app->addMiddleware(new RequestTimingMiddleware());
$corsConfig = $app->config('cors') ?? [];
$app->addMiddleware(new CorsMiddleware($corsConfig));
$app->addMiddleware(new JsonBodyParserMiddleware());

// Load routes from config file (route-level auth middleware is defined per route group)
$app->routes(require dirname(__DIR__) . '/config/routes.php');

// Run the application
$app->run();
