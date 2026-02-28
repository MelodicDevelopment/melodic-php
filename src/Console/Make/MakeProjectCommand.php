<?php

declare(strict_types=1);

namespace Melodic\Console\Make;

use Melodic\Console\Command;

class MakeProjectCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:project', 'Create a new Melodic project');
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->error('Usage: make:project <name> [--type=full|api|mvc]');
            return 1;
        }

        $type = $this->parseType($args);
        $projectDir = getcwd() . '/' . $name;

        if (is_dir($projectDir)) {
            $this->error("Directory '{$name}' already exists.");
            return 1;
        }

        $namespace = Stub::pascalCase($name);
        $hasMvc = in_array($type, ['full', 'mvc'], true);

        $this->writeln("Creating {$type} project '{$name}'...");

        $this->createDirectories($projectDir, $hasMvc);
        $this->createComposerJson($projectDir, $name, $namespace);
        $this->createConfig($projectDir);
        $this->createGitignore($projectDir);
        $this->createHtaccess($projectDir);
        $this->createIndexPhp($projectDir, $namespace, $type);
        $this->createConsole($projectDir, $namespace);
        $this->createServiceProvider($projectDir, $namespace);

        if ($hasMvc) {
            $this->createMvcFiles($projectDir, $namespace);
        }

        $this->writeln('');
        $this->writeln("Project '{$name}' created successfully!");
        $this->writeln('');
        $this->writeln('Next steps:');
        $this->writeln("  cd {$name}");
        $this->writeln('  composer install');

        if ($hasMvc) {
            $this->writeln('  php -S localhost:8080 -t public');
        }

        return 0;
    }

    private function parseType(array $args): string
    {
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--type=')) {
                $type = substr($arg, 7);
                if (in_array($type, ['full', 'api', 'mvc'], true)) {
                    return $type;
                }
            }
        }

        return 'full';
    }

    private function createDirectories(string $dir, bool $hasMvc): void
    {
        $dirs = [
            'config',
            'public',
            'bin',
            'src/Controllers',
            'src/Services',
            'src/DTO',
            'src/Data',
            'src/Middleware',
            'src/Providers',
            'storage/cache',
            'storage/logs',
            'tests',
        ];

        if ($hasMvc) {
            $dirs[] = 'views/layouts';
            $dirs[] = 'views/home';
        }

        foreach ($dirs as $subdir) {
            $path = $dir . '/' . $subdir;
            mkdir($path, 0755, true);

            // Add .gitkeep to empty directories that won't get files
            if (in_array($subdir, ['src/Controllers', 'src/Services', 'src/DTO', 'src/Data', 'src/Middleware', 'storage/cache', 'storage/logs', 'tests'], true)) {
                file_put_contents($path . '/.gitkeep', '');
            }
        }
    }

    private function createComposerJson(string $dir, string $name, string $namespace): void
    {
        $content = Stub::render(self::COMPOSER_STUB, [
            'name' => $name,
            'namespace' => str_replace('\\', '\\\\', $namespace . '\\'),
        ]);

        file_put_contents($dir . '/composer.json', $content);
    }

    private function createConfig(string $dir): void
    {
        file_put_contents($dir . '/config/config.json', self::CONFIG_STUB);
        file_put_contents($dir . '/config/config.qa.json', self::CONFIG_QA_STUB);
        file_put_contents($dir . '/config/config.pd.json', self::CONFIG_PD_STUB);
    }

    private function createGitignore(string $dir): void
    {
        file_put_contents($dir . '/.gitignore', self::GITIGNORE_STUB);
    }

    private function createHtaccess(string $dir): void
    {
        file_put_contents($dir . '/public/.htaccess', self::HTACCESS_STUB);
    }

    private function createIndexPhp(string $dir, string $namespace, string $type): void
    {
        $stub = match ($type) {
            'api' => self::INDEX_API_STUB,
            'mvc' => self::INDEX_MVC_STUB,
            default => self::INDEX_FULL_STUB,
        };
        $content = Stub::render($stub, ['namespace' => $namespace]);
        file_put_contents($dir . '/public/index.php', $content);
    }

    private function createConsole(string $dir, string $namespace): void
    {
        $content = Stub::render(self::CONSOLE_STUB, ['namespace' => $namespace]);
        $path = $dir . '/bin/console';
        file_put_contents($path, $content);
        chmod($path, 0755);
    }

    private function createServiceProvider(string $dir, string $namespace): void
    {
        $content = Stub::render(self::SERVICE_PROVIDER_STUB, ['namespace' => $namespace]);
        file_put_contents($dir . '/src/Providers/AppServiceProvider.php', $content);
    }

    private function createMvcFiles(string $dir, string $namespace): void
    {
        $content = Stub::render(self::HOME_CONTROLLER_STUB, ['namespace' => $namespace]);
        file_put_contents($dir . '/src/Controllers/HomeController.php', $content);

        // Remove .gitkeep from Controllers since it now has a file
        $gitkeep = $dir . '/src/Controllers/.gitkeep';
        if (file_exists($gitkeep)) {
            unlink($gitkeep);
        }

        file_put_contents($dir . '/views/layouts/main.phtml', self::LAYOUT_STUB);
        file_put_contents($dir . '/views/home/index.phtml', self::HOME_VIEW_STUB);
    }

    private const COMPOSER_STUB = <<<'JSON'
{
    "name": "app/{name}",
    "description": "",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "melodicdev/framework": "^1.0"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": {
            "{namespace}": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "test": "phpunit"
    }
}
JSON;

    private const CONFIG_STUB = <<<'JSON'
{
    "app": {
        "debug": true
    },
    "database": {
        "dsn": "sqlite:storage/database.sqlite"
    },
    "jwt": {
        "secret": "change-me",
        "algorithm": "HS256"
    },
    "cors": {
        "allowedOrigins": ["*"],
        "allowedMethods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    }
}
JSON;

    private const CONFIG_QA_STUB = <<<'JSON'
{
    "app": {
        "debug": true
    },
    "database": {
        "dsn": ""
    },
    "jwt": {
        "secret": "",
        "algorithm": "HS256"
    }
}
JSON;

    private const CONFIG_PD_STUB = <<<'JSON'
{
    "app": {
        "debug": false
    },
    "database": {
        "dsn": ""
    },
    "jwt": {
        "secret": "",
        "algorithm": "HS256"
    }
}
JSON;

    private const GITIGNORE_STUB = <<<'TXT'
/vendor/
/storage/cache/*
/storage/logs/*
!storage/cache/.gitkeep
!storage/logs/.gitkeep
config/config.dev.json
.phpunit.cache
.env
TXT;

    private const HTACCESS_STUB = <<<'TXT'
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
TXT;

    private const INDEX_API_STUB = <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use {namespace}\Providers\AppServiceProvider;

$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();

$app->register(new AppServiceProvider());

$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());

$app->routes(function ($router) {
    // $router->apiResource('/api/users', UserController::class);
});

$app->run();
PHP;

    private const INDEX_MVC_STUB = <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use {namespace}\Controllers\HomeController;
use {namespace}\Providers\AppServiceProvider;

$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();

$app->register(new AppServiceProvider());

$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());

$app->routes(function ($router) {
    $router->get('/', HomeController::class, 'index');
});

$app->run();
PHP;

    private const INDEX_FULL_STUB = <<<'PHP'
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use {namespace}\Controllers\HomeController;
use {namespace}\Providers\AppServiceProvider;

$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();

$app->register(new AppServiceProvider());

$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());

$app->routes(function ($router) {
    // MVC routes
    $router->get('/', HomeController::class, 'index');

    // API routes
    // $router->group('/api', function ($router) {
    //     $router->apiResource('/users', UserController::class);
    // });
});

$app->run();
PHP;

    private const CONSOLE_STUB = <<<'PHP'
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Console\Console;

$console = new Console();
$console->setName('{namespace}');

exit($console->run($argv));
PHP;

    private const SERVICE_PROVIDER_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Providers;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Register application services
        // $container->singleton(DbContextInterface::class, fn() => new DbContext($pdo));
    }

    public function boot(Container $container): void
    {
        // Post-registration logic
    }
}
PHP;

    private const HOME_CONTROLLER_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class HomeController extends MvcController
{
    public function index(): Response
    {
        $this->viewBag->title = 'Home';
        $this->setLayout('layouts/main');

        return $this->view('home/index', [
            'heading' => 'Welcome',
        ]);
    }
}
PHP;

    private const LAYOUT_STUB = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($viewBag->title ?? 'App') ?></title>
    <?= $this->renderSection('head') ?>
</head>
<body>
    <main><?= $this->renderBody() ?></main>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
HTML;

    private const HOME_VIEW_STUB = <<<'HTML'
<h1><?= htmlspecialchars($heading) ?></h1>
<p>Your Melodic application is running.</p>
HTML;
}
