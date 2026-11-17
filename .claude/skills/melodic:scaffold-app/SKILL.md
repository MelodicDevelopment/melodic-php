---
name: melodic:scaffold-app
description: Scaffold a new Melodic PHP application. Use when the user wants to create a new web app, API, or combined application.
disable-model-invocation: true
argument-hint: [app-directory]
---

# Scaffold a New Melodic PHP Application

You are scaffolding a new application using the Melodic PHP framework. The target directory is `$ARGUMENTS` (if no argument was provided, ask the user where to create the app).

Read `vendor/melodicdev/framework/MELODIC_PHP.md` (the authoritative framework usage reference; `MELODIC_PHP.md` at the root when working inside the framework repo) before generating code. If it disagrees with the framework's `src/`, the source wins.

## Step 1: Ask the User What They Want

Use AskUserQuestion to gather requirements before generating any files. Ask all questions in a single call:

1. **App type**: What kind of app?
    - **Web + API (Recommended)** — MVC views with layouts, plus JSON API endpoints
    - **API only** — JSON API with Bearer token auth, no views
    - **Web only** — MVC views with cookie-based auth, no API

2. **Authentication**: What auth providers?
    - **Google + GitHub (Recommended)** — Social login with Google (OIDC) and GitHub (OAuth2)
    - **Google only** — OIDC with Google
    - **Local only** — Username/password against app's own user store
    - **None** — No authentication

3. **Database**: Which database?
    - **SQLite (Recommended)** — File-based, no setup needed
    - **MySQL** — MySQL/MariaDB
    - **PostgreSQL** — PostgreSQL

4. **Domain entity**: What's the primary resource? (e.g. "Post", "Product", "Task") — this will be used to scaffold the first CRUD resource as a working example.

GitHub (OAuth2) login is a browser redirect flow that ends in a cookie. For **API only**, say so and steer the user to Google (clients send the Google ID token as Bearer) and/or Local (token endpoint below).

## Step 2: Create the Directory Structure

The layout is the one `vendor/bin/melodic make:project <name> [--type=full|api|mvc]` generates. `{Namespace}` is the PascalCased directory name (`my-app` → `MyApp`), not `App`. Write the files yourself from the templates below — the CLI skeleton has no database binding, no auth and no example resource.

### Base structure (always created):

```
{app-directory}/
├── composer.json              # PSR-4 {Namespace}\ → src/
├── .gitignore
├── config/
│   ├── config.json            # committed; no secrets
│   └── config.dev.json        # gitignored; local overrides + dev secrets
├── public/
│   ├── index.php              # Entry point: bootstrap, middleware, routes
│   └── .htaccess
├── bin/
│   └── console                # CLI: database migrations (chmod +x)
├── migrations/                # Plain-SQL migrations
├── src/
│   ├── Controllers/
│   ├── Services/
│   ├── DTO/                   # Models and request DTOs
│   ├── Data/{Entity}/         # Queries/ and Commands/
│   ├── Middleware/
│   └── Providers/
│       └── AppServiceProvider.php
├── storage/
│   ├── cache/.gitkeep
│   └── logs/.gitkeep
└── tests/.gitkeep
```

### If Web is included, also create:

```
├── views/
│   ├── layouts/main.phtml     # Base layout with nav and sections
│   └── home/index.phtml       # Home page
└── src/Controllers/
    ├── HomeController.php
    └── AppController.php      # only with auth: base class exposing the user to the layout
```

### If API is included, also create:

```
└── src/Controllers/{Entity}Controller.php   # CRUD API controller for the domain entity
```

### If Local auth, also create:

```
└── src/
    ├── Security/AppAuthenticator.php        # LocalAuthenticatorInterface implementation
    ├── Controllers/TokenController.php      # only with API: POST /api/auth/token
    └── DTO/TokenRequest.php                 # only with API
```

## Step 3: File Contents

Lines tagged `[web]`, `[api]`, `[auth]`, `[local]` are included only for that choice; drop the tags and the unused `use` statements.

### composer.json

```json
{
    "name": "app/{app-name}",
    "description": "",
    "type": "project",
    "require": {
        "php": ">=8.2",
        "melodicdev/framework": "^4.1"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    },
    "autoload": {
        "psr-4": { "{Namespace}\\": "src/" }
    },
    "autoload-dev": {
        "psr-4": { "Tests\\": "tests/" }
    },
    "scripts": {
        "test": "phpunit"
    }
}
```

Add `"firebase/php-jwt": "^6.0 || ^7.0"` to `require` when you generate `TokenController`.

### .gitignore and public/.htaccess

```
/vendor/
/storage/cache/*
/storage/logs/*
/storage/*.sqlite
!storage/cache/.gitkeep
!storage/logs/.gitkeep
config/config.dev.json
.phpunit.cache
.env
```

```
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

### config/config.json

Valid JSON only — no comments. Secrets are `${ENV_VAR}` placeholders (resolved from the environment at load time) and are overridden for local development in `config.dev.json`.

```json
{
    "app": { "name": "{app-name}", "debug": false },
    "database": { "dsn": "sqlite:storage/database.sqlite" },
    "cors": {
        "allowedOrigins": ["*"],
        "allowedMethods": ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    },
    "auth": {
        "local": { "signingKey": "${AUTH_SIGNING_KEY}", "issuer": "{app-name}", "audience": "{app-name}", "tokenLifetime": 3600 },
        "providers": {
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "REPLACE_WITH_GOOGLE_CLIENT_ID",
                "clientSecret": "${GOOGLE_CLIENT_SECRET}",
                "redirectUri": "http://localhost:8080/auth/callback/google"
            },
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "REPLACE_WITH_GITHUB_CLIENT_ID",
                "clientSecret": "${GITHUB_CLIENT_SECRET}",
                "redirectUri": "http://localhost:8080/auth/callback/github",
                "scopes": "read:user user:email",
                "claimMap": { "sub": "id", "username": "login", "email": "email" }
            },
            "local": { "type": "local", "label": "Sign in" }
        }
    }
}
```

- `database`: MySQL `"dsn": "mysql:host=127.0.0.1;port=3306;dbname={app_name};charset=utf8mb4"`, PostgreSQL `"dsn": "pgsql:host=127.0.0.1;port=5432;dbname={app_name}"`, each with `"username"` and `"password": "${DB_PASSWORD}"`.
- `auth`: omit entirely for no auth; keep only the chosen providers. `auth.local` (the app's own JWT signing config) is required by `oauth2` and `local` providers and by `TokenController`; omit it for Google only. For API only + Local, omit `providers.local` (it is the browser login form) and keep `auth.local`.
- `cors`: tighten `allowedOrigins` before production.

### config/config.dev.json (gitignored)

```json
{
    "app": { "debug": true },
    "auth": {
        "cookieSecure": false,
        "local": { "signingKey": "<output of: php -r 'echo bin2hex(random_bytes(32));'>" },
        "providers": {
            "google": { "clientSecret": "REPLACE_WITH_GOOGLE_CLIENT_SECRET" },
            "github": { "clientSecret": "REPLACE_WITH_GITHUB_CLIENT_SECRET" }
        }
    },
    "session": { "cookieSecure": false }
}
```

Include only the keys that apply (plus `database.username`/`password` for MySQL/PostgreSQL). Generate a real signing key — a key shorter than 32 characters makes the app throw at startup. `cookieSecure: false` is needed because auth and session cookies are `Secure` by default and the dev server is plain HTTP; never set it in a committed config.

### src/Providers/AppServiceProvider.php

```php
<?php

declare(strict_types=1);

namespace {Namespace}\Providers;

use Melodic\Core\Application;
use Melodic\Core\Configuration;
use Melodic\Data\DbContext;
use Melodic\Data\DbContextInterface;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Log\FileLogger;
use Melodic\Log\LoggerInterface;
use Melodic\Security\LocalAuthenticatorInterface;                 // [local]
use {Namespace}\Security\AppAuthenticator;                        // [local]

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $basePath = $container->get(Application::class)->getBasePath();

        $container->singleton(DbContextInterface::class, function (Container $c) use ($basePath) {
            $config = $c->get(Configuration::class);
            $dsn = (string) $config->get('database.dsn');

            // A relative SQLite path would resolve against the web server's working directory (public/).
            if (str_starts_with($dsn, 'sqlite:') && !str_starts_with($dsn, 'sqlite:/') && !str_starts_with($dsn, 'sqlite::')) {
                $dsn = 'sqlite:' . $basePath . '/' . substr($dsn, 7);
            }

            return new DbContext($dsn, $config->get('database.username'), $config->get('database.password'));
        });

        // Without a logger binding the framework discards exception logs.
        $container->singleton(LoggerInterface::class, fn() => new FileLogger($basePath . '/storage/logs'));

        $container->bind(LocalAuthenticatorInterface::class, AppAuthenticator::class);   // [local]
    }
}
```

Controllers, services and the `ViewEngine` (`{basePath}/views`) need no binding — they are auto-wired or bound by the framework.

### public/index.php

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Cache\CacheInterface;                                  // [local]
use Melodic\Cache\CacheServiceProvider;                            // [local]
use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Http\Middleware\RateLimitMiddleware;                   // [local]
use Melodic\Http\Middleware\SecurityHeadersMiddleware;             // [web]
use Melodic\Routing\Router;
use Melodic\Security\ApiAuthenticationMiddleware;                  // [api][auth]
use Melodic\Security\AuthCallbackMiddleware;                       // [web][auth]
use Melodic\Security\CsrfMiddleware;                               // [web][auth]
use Melodic\Security\OptionalWebAuthMiddleware;                    // [web][auth]
use Melodic\Security\SecurityServiceProvider;                      // [auth]
use Melodic\Security\WebAuthenticationMiddleware;                  // [web][auth]
use {Namespace}\Controllers\HomeController;                        // [web]
use {Namespace}\Controllers\{Entity}Controller;                    // [api]
use {Namespace}\Controllers\TokenController;                       // [api][local]
use {Namespace}\Providers\AppServiceProvider;

$app = new Application(dirname(__DIR__));
$app->loadEnvironmentConfig();   // config.json → config.{APP_ENV}.json → config.dev.json

$app->register(new AppServiceProvider());
$app->register(new CacheServiceProvider($app->getBasePath() . '/storage/cache'));   // [local]
$app->register(new SecurityServiceProvider());                                      // [auth]

$container = $app->getContainer();

// Global middleware: instances, run in this order for every request.
$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new SecurityHeadersMiddleware());                               // [web]
$app->addMiddleware(new JsonBodyParserMiddleware());
$app->addMiddleware(new RateLimitMiddleware(                                        // [local] throttle password guessing
    $container->get(CacheInterface::class), 10, 60,
    paths: ['/auth/callback', '/api/auth/token'], methods: ['POST'],
));
$app->addMiddleware($container->get(AuthCallbackMiddleware::class));                // [web][auth] serves /auth/login, /auth/callback/*, POST /auth/logout
$app->addMiddleware($container->get(CsrfMiddleware::class));                        // [web][auth] POST forms must print $csrfField; /api is exempt

// Route middleware: class-name strings, resolved from the container.
$app->routes(function (Router $router) {
    // [web] public page; with auth, OptionalWebAuthMiddleware exposes the user without forcing a login
    $router->get('/', HomeController::class, 'index', middleware: [OptionalWebAuthMiddleware::class]);

    // [web][auth] pages that require a login go in a group like this
    // $router->group('', function (Router $router) { ... }, middleware: [WebAuthenticationMiddleware::class]);

    $router->post('/api/auth/token', TokenController::class, 'create');             // [api][local]

    // [api] drop the middleware argument when there is no auth
    $router->group('/api', function (Router $router) {
        $router->apiResource('/{path}', {Entity}Controller::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
});

$app->run();
```

Without auth, `/` takes no middleware. `ApiAuthenticationMiddleware` expects `Authorization: Bearer <jwt>`: the Google ID token, the token from `POST /api/auth/token`, or — handy for testing — the value of the `melodic_auth` cookie after a browser login. If the app's own pages should call the API with the login cookie instead, use `middleware: [OptionalWebAuthMiddleware::class, AuthorizationMiddleware::class]` on that group and set `"csrf": { "exceptPaths": [] }` so those calls must send the `X-CSRF-Token` header.

### bin/console

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Console\Console;
use Melodic\Console\MakeMigrationCommand;
use Melodic\Console\MigrateCommand;
use Melodic\Console\MigrateRollbackCommand;
use Melodic\Console\MigrateStatusCommand;
use Melodic\Core\Application;
use Melodic\Data\DbContextInterface;
use Melodic\Data\Migration\Migrator;
use {Namespace}\Providers\AppServiceProvider;

$app = new Application(dirname(__DIR__));
$app->loadEnvironmentConfig();
$app->register(new AppServiceProvider());

$migrations = dirname(__DIR__) . '/migrations';
$migrator = new Migrator($app->getContainer()->get(DbContextInterface::class), $migrations);

$console = new Console();
$console->setName('{Namespace}');
$console->register(new MigrateCommand($migrator));
$console->register(new MigrateRollbackCommand($migrator));
$console->register(new MigrateStatusCommand($migrator));
$console->register(new MakeMigrationCommand($migrations));

exit($console->run($argv));
```

### Domain entity files

For the user's chosen entity, create the full CQRS stack exactly as specified in Steps 2–5 of `.claude/skills/melodic:scaffold-resource/SKILL.md` (read that file; the framework package ships the same file under `vendor/melodicdev/framework/.claude/skills/`): `src/DTO/{Entity}Model.php` + `Create{Entity}Request.php` + `Update{Entity}Request.php`, `src/Data/{Entity}/Queries/` (`GetAll{Plural}Query`, `Get{Entity}ByIdQuery`), `src/Data/{Entity}/Commands/` (`Create…`, `Update…`, `Delete…`), `src/Services/{Entity}Service.php`, `src/Controllers/{Entity}Controller.php` (if API), and the migration `migrations/{YYYYMMDDHHMMSS}_create_{table}.sql`. Give the entity typical fields (id, name/title, createdAt) and name the table columns exactly like the model properties. For Web only, create the MVC controller and views described there instead of the API controller and register its page routes (that skill's Step 4) in place of the `/api` group.

### HomeController, layout and home view (if web)

Without auth, `HomeController` extends `Melodic\Controller\MvcController` directly and calls `$this->setLayout('layouts/main')` in the action. With auth, add this base class so every page's layout can show the login state, and extend it:

```php
// src/Controllers/AppController.php
abstract class AppController extends MvcController
{
    protected ?string $layout = 'layouts/main';

    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): Response
    {
        // Read by layouts/main.phtml to switch between "Log in" and the logout form.
        $this->viewBag->userContext = $this->getUserContext();

        return parent::view($template, $data);
    }
}

// src/Controllers/HomeController.php
class HomeController extends AppController
{
    public function index(): Response
    {
        $this->viewBag->title = 'Home';

        return $this->view('home/index', ['heading' => 'Welcome']);
    }
}
```

A controller that adds a constructor must accept `Melodic\View\ViewEngine $viewEngine` and call `parent::__construct($viewEngine)`.

`views/layouts/main.phtml` — templates are **not auto-escaped**; wrap every dynamic value in `$this->e()`. Logout must be a POST carrying the CSRF token (`$csrfField` is provided by `CsrfMiddleware`); a GET to `/auth/logout` returns 405.

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($viewBag->title ?? '{app-name}') ?></title>
    <?= $this->renderSection('head') ?>
</head>
<body>
    <nav>
        <a href="/">Home</a>
        <?php if ($viewBag->userContext?->isAuthenticated()): ?>
            <form method="POST" action="/auth/logout">
                <?= $csrfField ?? '' ?>
                <button type="submit">Log out (<?= $this->e($viewBag->userContext->getUsername()) ?>)</button>
            </form>
        <?php else: ?>
            <a href="/auth/login">Log in</a>
        <?php endif; ?>
    </nav>
    <main><?= $this->renderBody() ?></main>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

Omit the login/logout block without auth. Add a small inline `<style>` (system font stack, light gray background, white content card, nav bar). `views/home/index.phtml`:

```php
<h1><?= $this->e($heading) ?></h1>
<p>Your Melodic application is running.</p>
```

### AppAuthenticator (if local auth) — `src/Security/AppAuthenticator.php`

```php
class AppAuthenticator implements LocalAuthenticatorInterface
{
    // TODO: Replace with a database lookup (Service → Query). Demo user: admin@example.com / password
    private const DEMO_EMAIL = 'admin@example.com';
    private const DEMO_HASH = '<output of: php -r "echo password_hash(\"password\", PASSWORD_DEFAULT);">';

    public function authenticate(string $username, string $password): array
    {
        if ($username !== self::DEMO_EMAIL || !password_verify($password, self::DEMO_HASH)) {
            throw new SecurityException('Invalid username or password.');
        }

        return ['sub' => '1', 'username' => 'admin', 'email' => self::DEMO_EMAIL, 'entitlements' => ['admin']];
    }
}
```

`use Melodic\Security\LocalAuthenticatorInterface;` and `Melodic\Security\SecurityException;`. Keep the hash in **single quotes** (it contains `$`). The returned claims must include `sub`, `username` and `email`.

### TokenController (if local auth + API) — `POST /api/auth/token`

`src/DTO/TokenRequest.php` extends `Melodic\Data\Model` with `#[Required] public string $username;` and `#[Required] public string $password;`.

```php
class TokenController extends ApiController
{
    public function __construct(
        private readonly LocalAuthenticatorInterface $authenticator,
        private readonly AuthConfig $authConfig,
    ) {}

    public function create(TokenRequest $request): JsonResponse
    {
        $local = $this->authConfig->getLocalAuth() ?? throw new \RuntimeException('auth.local is not configured.');

        // Throws SecurityException on bad credentials → 401 from the error handler.
        $claims = $this->authenticator->authenticate($request->username, $request->password);
        $now = time();

        $token = JWT::encode([
            'iss' => $local->issuer,
            'aud' => $local->audience,
            'iat' => $now,
            'exp' => $now + $local->tokenLifetime,
            'sub' => (string) ($claims['sub'] ?? ''),
            'username' => $claims['username'] ?? '',
            'email' => $claims['email'] ?? '',
            'entitlements' => $claims['entitlements'] ?? [],
            'provider' => 'local',
        ], $local->signingKey, $local->algorithm);

        return $this->json(['token' => $token, 'expiresIn' => $local->tokenLifetime]);
    }
}
```

`use Firebase\JWT\JWT;`, `Melodic\Controller\ApiController;`, `Melodic\Http\JsonResponse;`, `Melodic\Security\AuthConfig;`, `Melodic\Security\LocalAuthenticatorInterface;`.

## Step 4: Summary

After creating all files, run `php -l` on every PHP file, then print a summary:

- List all files created
- Install and migrate: `cd {app-directory} && composer install && php bin/console migrate` (SQLite creates `storage/database.sqlite`; for MySQL/PostgreSQL create the database and set credentials in `config/config.dev.json` first)
- Start the dev server: `php -S localhost:8080 -t public`
- If auth is configured with external providers, remind the user to replace the placeholder client IDs/secrets (`config/config.json` / `config/config.dev.json`) and register the redirect URIs `http://localhost:8080/auth/callback/{provider}` with each provider; the `auth` config keys are documented in section 11 of `vendor/melodicdev/framework/MELODIC_PHP.md`
- For production: provide `AUTH_SIGNING_KEY` and the other `${...}` secrets through the environment (or a `config/config.{APP_ENV}.json` created with `vendor/bin/melodic make:config <env>`), keep `app.debug` false, serve over HTTPS
- Show the URLs they can visit to test (`/`, `/auth/login`, `/api/{path}`) and how to obtain a Bearer token for the API

## Important

- Follow all conventions from CLAUDE.md (PHP 8.2+, PascalCase classes, camelCase methods, CQRS layering)
- Use `declare(strict_types=1)` in every PHP file, with the full `namespace` and `use` statements (the shorter templates above omit them)
- Use constructor promotion and readonly properties
- The generated app should work out of the box with `php -S localhost:8080 -t public` (assuming dependencies are installed, migrations are run, and placeholder credentials are updated for auth)
- Never invent framework classes: there is no `AuthenticationMiddleware`, no `config.local.json` and no `jwt` config block — check `vendor/melodicdev/framework/src/` when unsure
- Do NOT create the app inside the melodic-php framework directory — it's a separate project that depends on the framework
- If the target directory already exists and has files, warn the user before overwriting
