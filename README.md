# Melodic PHP Framework

A modern PHP 8.2+ framework with CQRS data patterns, JWT authentication (local, OIDC, and OAuth2 providers), and a PSR-15-style middleware pipeline. Uses a layered architecture: **Controller → Service → Query/Command**.

Current version: **4.1.0** (`Melodic\Framework::VERSION`, Composer package `melodicdev/framework`).

- Building an application with Melodic? Read [MELODIC_PHP.md](MELODIC_PHP.md) — the usage reference for consumer projects. It ships inside the Composer package (`vendor/melodicdev/framework/MELODIC_PHP.md`), so it always matches the installed version and can be imported into your project's `CLAUDE.md`.
- What's new in 4.1 (backwards compatible with 4.0): see [CHANGELOG.md](CHANGELOG.md).
- Upgrading from 2.x or 3.x? See [MIGRATION-3.0.md](MIGRATION-3.0.md) and [MIGRATION-4.0.md](MIGRATION-4.0.md).
- Topic guides are indexed under [Documentation](#documentation).

## Requirements

- PHP 8.2+
- Composer
- PDO extension with a driver for your database (SQLite, MySQL, PostgreSQL, ...)
- `mbstring` is optional: without it, `#[MinLength]` / `#[MaxLength]` and console tables count bytes instead of characters

## Installation

```bash
composer require melodicdev/framework
```

## Quick Start

The package installs a `vendor/bin/melodic` CLI that scaffolds a new application:

```bash
vendor/bin/melodic make:project my-app    # creates ./my-app (MVC + API)
cd my-app
composer install
php -S localhost:8080 -t public
```

Then visit `http://localhost:8080/`. Add `--type=api` or `--type=mvc` for an API-only or MVC-only project, and run `vendor/bin/melodic make:entity Church` inside the project to generate a CQRS resource. See [Scaffolding](#scaffolding).

The generated `config/config.json` enables `app.debug` (error responses include stack traces); `config.pd.json` turns it off.

## Architecture

```
HTTP Request → Middleware Pipeline → Router → Controller → Service → Query/Command → Database
```

The framework enforces a clean separation of concerns across layers:

| Layer | Responsibility | Framework Namespace |
|---|---|---|
| Core | Application bootstrap, configuration | `Melodic\Core` |
| HTTP | Request/Response objects, middleware pipeline, HTTP exceptions | `Melodic\Http` |
| Routing | URL pattern matching, route groups, API resources, model binding | `Melodic\Routing` |
| Controller | Receives requests, returns responses, delegates to services | `Melodic\Controller` |
| Service | Business logic, orchestrates queries and commands | `Melodic\Service` |
| Data | Database access via DbContext, CQRS query/command objects, models | `Melodic\Data` |
| DI | Dependency injection container with auto-wiring | `Melodic\DI` |
| Security | JWT validation, OIDC/OAuth2/local login, CSRF, refresh tokens, authorization | `Melodic\Security` |
| Validation | Attribute-based DTO validation | `Melodic\Validation` |
| View | Template engine with layouts and sections | `Melodic\View` |
| Error | Exception → JSON/HTML response mapping | `Melodic\Error` |
| Event | Priority-based event dispatcher | `Melodic\Event` |
| Cache | PSR-16-style cache (file, array) | `Melodic\Cache` |
| Session | Session abstraction (native, array) | `Melodic\Session` |
| Log | File/null loggers with level filtering | `Melodic\Log` |
| Console | CLI runner and `make:*` scaffolding commands | `Melodic\Console` |

## Project Structure

```
melodic-php/
├── composer.json                            # melodicdev/framework — PSR-4: Melodic\ → src/
├── bin/
│   └── melodic                              # Scaffolding CLI (installed as vendor/bin/melodic)
├── src/
│   ├── Framework.php                        # Framework::VERSION — single source of truth for the version
│   ├── Utilities.php                        # Utilities::kill() debug helper (deprecated)
│   ├── Core/
│   │   ├── Application.php                  # App builder: config, providers, middleware, routes, run()
│   │   └── Configuration.php                # JSON config loader with dot-notation access and deep merge
│   ├── Http/
│   │   ├── HttpMethod.php                   # Enum: GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD
│   │   ├── Request.php                      # Wraps superglobals, immutable attributes
│   │   ├── Response.php                     # Status code, headers, cookies, body, send()
│   │   ├── JsonResponse.php                 # JSON-encoded response
│   │   ├── RedirectResponse.php             # Location redirect; local() rejects off-site targets
│   │   ├── Exception/
│   │   │   ├── HttpException.php            # Base HTTP exception with status code
│   │   │   ├── BadRequestException.php      # 400
│   │   │   ├── NotFoundException.php        # 404
│   │   │   └── MethodNotAllowedException.php # 405, carries the allowed methods
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php      # process(Request, RequestHandlerInterface): Response
│   │       ├── RequestHandlerInterface.php  # handle(Request): Response
│   │       ├── ResponseDecoratorInterface.php # decorate(Request, Response) — headers applied to error responses too
│   │       ├── Pipeline.php                 # Chains middleware to a final handler
│   │       ├── ErrorHandlerMiddleware.php   # Catches exceptions and delegates to ExceptionHandler
│   │       ├── CorsMiddleware.php           # Configurable CORS headers and preflight handling
│   │       ├── JsonBodyParserMiddleware.php # Parses application/json bodies
│   │       ├── RateLimitMiddleware.php      # Cache-backed fixed-window rate limiter
│   │       └── SecurityHeadersMiddleware.php # Baseline browser-hardening headers
│   ├── Routing/
│   │   ├── Route.php                        # Method + URI pattern + controller/action + middleware/attributes/name
│   │   ├── Router.php                       # Registration, groups, apiResource(), named routes + url()
│   │   ├── RoutingMiddleware.php            # Matches the route, binds action arguments, invokes the controller
│   │   └── FromQuery.php                    # #[FromQuery] — bind a Model parameter from the query string
│   ├── Controller/
│   │   ├── Controller.php                   # Abstract base: json(), created(), noContent(), getUserContext(), etc.
│   │   ├── ApiController.php                # Base for JSON API controllers
│   │   └── MvcController.php                # view(), redirect(), layout/ViewBag support
│   ├── DI/
│   │   ├── ContainerInterface.php           # get(), has(), bind(), singleton() — extends PSR-11
│   │   ├── Container.php                    # Auto-wiring, singletons, interface bindings, factories, instance()
│   │   ├── ContainerException.php           # Resolution failure
│   │   ├── NotFoundException.php            # Unknown id (extends ContainerException)
│   │   ├── CircularDependencyException.php  # Dependency cycle (extends ContainerException)
│   │   └── ServiceProvider.php              # Modular registration base class: register(), boot()
│   ├── Data/
│   │   ├── DbContextInterface.php           # query(), queryFirst(), command(), scalar(), transaction(), lastInsertId()
│   │   ├── DbContext.php                    # PDO wrapper with typed model hydration, cursor()
│   │   ├── QueryInterface.php               # CQRS query: getSql(), execute()
│   │   ├── CommandInterface.php             # CQRS command: getSql(), execute() returns int
│   │   ├── Model.php                        # Base DTO: fromArray(), toArray(), toPascalArray(), toUpdateArray()
│   │   ├── Guarded.php                      # #[Guarded] — property never binds from request input
│   │   ├── ListOf.php                       # #[ListOf(Foo::class)] — hydrate an array property as a list of models
│   │   ├── ModelBindingException.php        # Uncoercible input (becomes a 400)
│   │   ├── Paginator.php                    # LIMIT/OFFSET pagination → PaginatedResult
│   │   ├── PaginatedResult.php              # items, total, page, perPage, pageCount
│   │   └── Migration/
│   │       └── Migrator.php                 # Plain-SQL schema migrations
│   ├── Service/
│   │   └── Service.php                      # Base service holding the DbContext (and optional read-only context)
│   ├── Security/
│   │   ├── SecurityServiceProvider.php      # Registers the auth stack from the `auth` / `session` config
│   │   ├── AuthConfig.php                   # Typed view of the `auth` config section
│   │   ├── AuthProviderConfig.php           # One entry under auth.providers
│   │   ├── AuthProviderType.php             # Enum: oidc, oauth2, local
│   │   ├── LocalAuthConfig.php              # auth.local signing config (HMAC key ≥ 32 chars)
│   │   ├── LoginPageConfig.php              # auth.loginPage branding
│   │   ├── AuthProviderInterface.php        # handleLogin(), handleCallback()
│   │   ├── AuthProviderRegistry.php         # Providers by name/type
│   │   ├── OidcAuthProvider.php             # OIDC authorization-code flow (PKCE, nonce, id_token validation)
│   │   ├── OidcProvider.php                 # Discovery document + JWKS fetch/cache
│   │   ├── OAuth2AuthProvider.php           # Generic OAuth2 code flow (PKCE) → local JWT
│   │   ├── OAuthClient.php                  # State/PKCE helpers, TLS-verifying JSON HTTP client
│   │   ├── LocalAuthProvider.php            # Username/password login → local JWT
│   │   ├── LocalAuthenticatorInterface.php  # Your credential check for the local provider
│   │   ├── LocalJwtIssuer.php               # Issues the app's own JWT (internal)
│   │   ├── AuthResult.php                   # Token + claims returned by a provider callback
│   │   ├── ClaimMapper.php                  # Maps OAuth2 userinfo fields to standard claims
│   │   ├── JwtValidator.php                 # Validates local (HMAC) and OIDC (JWKS) tokens
│   │   ├── User.php                         # id, username, email, entitlements
│   │   ├── UserContextInterface.php         # isAuthenticated(), getUser(), hasEntitlement(), getClaim()
│   │   ├── UserContext.php                  # Built from JWT claims
│   │   ├── ApiAuthenticationMiddleware.php  # Bearer token → UserContext, 401 otherwise
│   │   ├── WebAuthenticationMiddleware.php  # Auth cookie → UserContext, redirect to login otherwise
│   │   ├── OptionalWebAuthMiddleware.php    # Auth cookie → UserContext or anonymous; never blocks
│   │   ├── AuthCallbackMiddleware.php       # Serves the login page, provider login/callback, POST logout
│   │   ├── AuthorizationMiddleware.php      # Authentication/entitlement checks (401/403)
│   │   ├── AuthLoginRendererInterface.php   # Login page renderer contract
│   │   ├── AuthLoginRenderer.php            # Built-in login page
│   │   ├── SessionManager.php               # Session used by the auth flow (a NativeSession)
│   │   ├── CsrfToken.php                    # Session-backed CSRF token
│   │   ├── CsrfMiddleware.php               # CSRF check for state-changing requests
│   │   ├── SafeRedirect.php                 # Deprecated — use RedirectResponse::isLocalPath()
│   │   ├── RefreshToken.php                 # Refresh token entity
│   │   ├── RefreshTokenConfig.php           # auth.refreshToken settings
│   │   ├── RefreshTokenService.php          # create(), validate(), rotate(), validateAndRotate()
│   │   ├── RefreshTokenMiddleware.php       # Validates the refresh cookie
│   │   ├── RefreshTokenCookieHelper.php     # attach()/clear() the refresh cookie
│   │   ├── RefreshTokenRepositoryInterface.php # Your persistence for refresh tokens
│   │   ├── RefreshTokenReuseException.php   # Reuse of a revoked/superseded token (a SecurityException)
│   │   └── SecurityException.php
│   ├── Validation/
│   │   ├── Validator.php                    # Validates objects and arrays against attribute rules
│   │   ├── ValidationResult.php             # isValid + errors keyed by field
│   │   ├── ValidationException.php          # Carries a ValidationResult (no built-in status mapping)
│   │   ├── RuleInterface.php                # Contract for rule attributes
│   │   └── Rules/                           # Required, Email, MinLength, MaxLength, Min, Max, Pattern, In,
│   │                                        # Date, Url, Uuid
│   ├── Error/
│   │   └── ExceptionHandler.php             # JSON/HTML error responses, debug mode, logging, custom mappers
│   ├── Event/
│   │   ├── EventDispatcherInterface.php     # listen() and dispatch()
│   │   ├── EventDispatcher.php              # Priority-based listener execution, removeListener()
│   │   ├── Event.php                        # Base event with stopPropagation()
│   │   └── EventServiceProvider.php
│   ├── Cache/
│   │   ├── CacheInterface.php               # PSR-16-style get/set/delete/has/clear
│   │   ├── FileCache.php                    # File-based with TTL expiration, atomic writes
│   │   ├── ArrayCache.php                   # In-memory for testing
│   │   ├── Psr16CacheAdapter.php            # Wraps any PSR-16 cache
│   │   └── CacheServiceProvider.php
│   ├── Session/
│   │   ├── SessionInterface.php             # start, get, set, has, remove, destroy, regenerate, isStarted
│   │   ├── NativeSession.php                # Wraps PHP session functions with hardened cookie defaults
│   │   ├── ArraySession.php                 # In-memory for testing
│   │   └── SessionServiceProvider.php
│   ├── Log/
│   │   ├── LoggerInterface.php              # Standard log level methods + log()
│   │   ├── LogLevel.php                     # Enum: emergency through debug
│   │   ├── FileLogger.php                   # One file per day, level filtering, message interpolation
│   │   ├── NullLogger.php                   # No-op for testing
│   │   ├── PsrLoggerAdapter.php             # Wraps any PSR-3 logger
│   │   └── LoggingServiceProvider.php
│   ├── Console/
│   │   ├── CommandInterface.php             # getName(), getDescription(), execute()
│   │   ├── Command.php                      # Base class with output helpers
│   │   ├── Console.php                      # Command runner with help output
│   │   ├── RouteListCommand.php             # route:list
│   │   ├── CacheClearCommand.php            # cache:clear
│   │   ├── ClaudeInstallCommand.php         # claude:install
│   │   ├── MigrateCommand.php               # migrate — plus MigrateRollbackCommand, MigrateStatusCommand
│   │   ├── MakeMigrationCommand.php         # make:migration
│   │   └── Make/
│   │       ├── MakeProjectCommand.php       # make:project
│   │       ├── MakeEntityCommand.php        # make:entity
│   │       ├── MakeConfigCommand.php        # make:config
│   │       └── Stub.php                     # Template rendering and name-casing helpers
│   └── View/
│       ├── ViewEngine.php                   # Renders .phtml templates with layouts/sections/partials/caching
│       └── ViewBag.php                      # Dynamic key-value store for view data
├── tests/                                   # PHPUnit suite, mirrors src/
├── docs/                                    # Topic guides (see Documentation)
└── .claude/                                 # Claude Code agent and skills installed by claude:install
```

## Application Bootstrap

Create an entry point (`public/index.php`) that configures and runs the app:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Controllers\UserApiController;
use Melodic\Core\Application;
use Melodic\Data\DbContext;
use Melodic\Data\DbContextInterface;
use Melodic\DI\Container;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Routing\Router;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\SecurityServiceProvider;

$app = new Application(dirname(__DIR__));
$app->loadEnvironmentConfig();   // config/config.json → config.{APP_ENV}.json → config.dev.json

// Register service providers and services
$app->register(new SecurityServiceProvider());

$app->services(function (Container $container) use ($app) {
    $container->singleton(DbContextInterface::class, fn() => new DbContext(
        $app->config('database.dsn'),
        $app->config('database.username'),
        $app->config('database.password'),
    ));
});

// Add global middleware (executed in order)
$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());

// Define routes; route middleware is resolved from the container by class name
$app->routes(function (Router $router) {
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserApiController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
});

$app->run();
```

### `Application` API

| Method | Description |
|---|---|
| `loadEnvironmentConfig(string $configDir = 'config')` | Load `config.json`, then the `APP_ENV` file, then `config.dev.json` (see [Configuration](#configuration)) |
| `loadConfig(string $path)` | Load and merge a single JSON config file (relative to the base path, or absolute); throws if the file is missing |
| `config(?string $key, mixed $default)` | Read a config value using dot-notation, e.g. `$app->config('database.dsn')`; with no key, returns the `Configuration` object |
| `getEnvironment()` | The current environment name (`app.environment`, default `dev`) |
| `addMiddleware(MiddlewareInterface $m)` | Add global middleware to the pipeline (order matters) |
| `services(callable $callback)` | Register services in the DI container; receives `Container` |
| `register(ServiceProvider $provider)` | Register a service provider (its `register()` runs immediately, `boot()` when the application boots) |
| `routes(callable $callback)` | Define routes; receives `Router` |
| `getContainer()` / `getRouter()` / `getConfiguration()` / `getBasePath()` | Access the underlying objects |
| `run(?Request $request = null)` | Boot, capture the request from the superglobals (unless one is passed), dispatch it, and send the response |
| `handle(Request $request): Response` | Run a request through the middleware pipeline and router and return the response without sending it — for tests and long-running runtimes |
| `boot()` | Boot the service providers once; called automatically by `handle()` and `run()` |

The `Application`, `Container`, `Router`, and `Configuration` instances are registered in the container, so any service can type-hint them. The application also registers a default `ViewEngine` (see [MVC Controllers](#mvc-controllers)).

`handle()` makes an application testable without a web server:

```php
use Melodic\Http\Request;

$response = $app->handle(new Request(
    server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/users/1'],
    headers: ['Authorization' => "Bearer {$token}"],
));

$response->getStatusCode();   // 200
$response->getBody();         // '{"id":1,...}'
```

## Configuration

Configuration is loaded from JSON files and supports dot-notation access:

```json
{
    "app": {
        "debug": false
    },
    "database": {
        "dsn": "mysql:host=localhost;dbname=myapp",
        "username": "root",
        "password": "secret"
    },
    "cors": {
        "allowedOrigins": ["https://app.example.com"],
        "allowedMethods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    },
    "auth": {
        "local": {
            "signingKey": "<at least 32 characters — e.g. openssl rand -base64 48>",
            "issuer": "my-app",
            "audience": "my-app"
        }
    }
}
```

```php
$app->config('database.dsn');             // "mysql:host=localhost;dbname=myapp"
$app->config('cors.maxAge', 86400);       // with default fallback
```

### Environment Files

`$app->loadEnvironmentConfig()` loads and merges up to three files from `config/`:

```
config.json  →  config.{APP_ENV}.json  →  config.dev.json
  (base)         (env overrides)          (local overrides, gitignored)
```

- The `APP_ENV` environment variable selects the environment file (e.g. `APP_ENV=qa` → `config.qa.json`). When `APP_ENV` is unset or `dev`, no environment file is loaded.
- `config.dev.json` is loaded last whenever it exists; keep it out of version control for local overrides and secrets.
- `app.environment` is set automatically; read it with `$app->getEnvironment()`.
- Later files override earlier ones. Objects merge recursively; **lists replace wholesale** — an environment file must list every element it needs (e.g. all `cors.allowedOrigins`).

String values may contain `${VAR}` or `${VAR:-default}` placeholders, which are replaced with environment variables when the file is loaded — so secrets can stay out of the JSON files:

```json
{ "database": { "password": "${DB_PASSWORD}", "host": "${DB_HOST:-localhost}" } }
```

The default applies when the variable is unset or empty; a placeholder whose variable is unset and has no default is left untouched. A value that is exactly one placeholder resolving to `true` or `false` (e.g. `"debug": "${APP_DEBUG}"`) becomes a real boolean.

`make:project` generates `config.json`, `config.qa.json`, and `config.pd.json`; `vendor/bin/melodic make:config staging` adds another environment file. To load arbitrary files instead, call `$app->loadConfig('config/config.json')` once per file.

### Keys Read by the Framework

| Key | Default | Used by |
|---|---|---|
| `app.debug` | `false` | `ExceptionHandler` — include exception details in error responses |
| `app.environment` | `dev` | Set by `loadEnvironmentConfig()` |
| `app.errorsAsExceptions` | `false` | Opt-in: PHP warnings and notices become `ErrorException`s (and so 500 responses and log entries) |
| `auth.*` | see [Security](#security) | `SecurityServiceProvider` |
| `views.path` | `views` | Default `ViewEngine` template directory (relative to the base path, or absolute) |
| `session.cookieSecure` / `cookieSameSite` / `cookiePath` / `cookieDomain` | `true` / `Lax` / `/` / `""` | `SecurityServiceProvider` (auth session) and `SessionServiceProvider` |
| `logging.path` / `logging.level` | `{basePath}/logs` / `debug` | `LoggingServiceProvider` |
| `logging.maxFiles` | `0` (keep all) | `LoggingServiceProvider` — number of daily log files to retain |
| `csrf.exceptPaths` | `["/api"]` | `CsrfMiddleware` — path prefixes exempt from the CSRF check |

`database.*` and `cors.*` are conventions read by your own bootstrap code, as in the example above.

See [docs/configuration.md](docs/configuration.md) for full details.

## Routing

### Basic Routes

Every route maps an HTTP method and path to a controller class and action method:

```php
$app->routes(function (Router $router) {
    $router->get('/users', UserController::class, 'index');
    $router->post('/users', UserController::class, 'store');
    $router->get('/users/{id}', UserController::class, 'show');
    $router->put('/users/{id}', UserController::class, 'update');
    $router->delete('/users/{id}', UserController::class, 'destroy');
    $router->patch('/users/{id}', UserController::class, 'patch');
});
```

### API Resource Routes

Register the six RESTful routes for a resource in one call:

```php
$router->apiResource('/users', UserController::class);
```

This registers:

| HTTP Method | URI | Controller Method |
|---|---|---|
| GET | `/users` | `index()` |
| GET | `/users/{id}` | `show($id)` |
| POST | `/users` | `store()` |
| PUT | `/users/{id}` | `update($id)` |
| PATCH | `/users/{id}` | `update($id)` |
| DELETE | `/users/{id}` | `destroy($id)` |

The PATCH route is a fallback: an explicit `patch()` route that matches the same request takes precedence, whether it is registered before or after `apiResource()`.

### Route Groups, Middleware, and Attributes

Group routes under a common prefix. Groups and individual routes accept `middleware` (class names or container ids, resolved from the container per request) and `attributes` (arbitrary metadata); nested groups accumulate both:

```php
$router->group('/api', function (Router $router) {
    $router->apiResource('/users', UserController::class);
    $router->get('/reports', ReportController::class, 'index', attributes: ['audit' => true]);
}, middleware: [ApiAuthenticationMiddleware::class]);
// Produces: /api/users, /api/users/{id}, /api/reports
```

The matched `Route` is available as `$request->getAttribute('route')` and its attributes as `$request->getAttribute('route.attributes')`.

Route attributes are merged over group attributes: maps merge key by key, and a list replaces the group's list as a whole (a route's `'entitlements' => ['editor']` replaces the group's `['admin', 'manager']`). An empty array never overrides a group value.

### Route Parameters

Parameters in `{braces}` match one path segment and are passed to the controller action by name. They are coerced to the action's declared type (`int`, `float`, `bool`, or a backed enum); input that cannot be coerced returns **400** with a field-keyed body instead of reaching the controller:

```php
$router->get('/users/{id}/posts/{postId}', UserPostController::class, 'show');

public function show(int $id, int $postId): JsonResponse
{
    // GET /users/abc/posts/1 → 400 {"id": ["Route parameter 'id' must be an integer."]}
}
```

Add a regex constraint with `{name:regex}`; a segment that does not satisfy it simply does not match the route (so the request falls through to other routes, or a 404):

```php
$router->get('/users/{id:\d+}', UserController::class, 'show');
$router->get('/users/{slug}', UserController::class, 'showBySlug');
```

Captured values are URL-decoded (`/files/a%20b.txt` → `"a b.txt"`); the decoded value must satisfy the parameter's constraint too, so an encoded slash (`%2F`) does not match a one-segment `{name}` (use `{path:.+}` to accept slashes). Constraints may use quantifier braces (`{year:\d{4}}`). Values are also set as request attributes (`$request->getAttribute('id')`). Literal segments match literally (`/v1.0/users` does not treat `.` as a wildcard), and a trailing slash is tolerated (`/users/` matches `/users`).

### Named Routes

Pass `name:` to any route method and build paths with `Router::url()`:

```php
$router->get('/users/{id}', UserController::class, 'show', name: 'users.show');

$router->url('users.show', ['id' => 5]);                    // "/users/5"
$router->url('users.show', ['id' => 5, 'tab' => 'posts']);  // "/users/5?tab=posts"
```

Parameters that are not in the pattern become a query string; a missing parameter or unknown name throws `InvalidArgumentException`. The `Router` is in the container, so controllers and services can type-hint it.

### Method Handling

- A path that exists under a different method returns **405** with an `Allow` header; an unknown path returns **404**.
- `HEAD` requests are served by the matching `GET` route with the body suppressed.
- An HTTP method the framework does not model (e.g. `PROPFIND`) returns **501**.

## Controllers

### API Controllers

Extend `ApiController` for JSON API endpoints. The current request is available as `$this->request`:

```php
use Melodic\Controller\ApiController;
use Melodic\Http\JsonResponse;
use Melodic\Http\Response;

class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService, // auto-injected by DI
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();
        return $this->json(array_map(fn(UserModel $u) => $u->toArray(), $users));
    }

    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getById($id);
        return $user ? $this->json($user->toArray()) : $this->notFound();
    }

    public function store(CreateUserRequest $input): JsonResponse
    {
        // $input is hydrated from the request body and validated before this runs
        $id = $this->userService->create($input->username, $input->email);
        $user = $this->userService->getById($id);
        return $this->created($user->toArray(), "/api/users/{$id}");
    }

    public function destroy(int $id): Response
    {
        $this->userService->delete($id);
        return $this->noContent();
    }
}
```

### Action Arguments

Each action parameter is resolved in this order:

1. **Route parameter** with the same name — coerced to the declared type (see [Route Parameters](#route-parameters)).
2. **`Model` subclass** — hydrated from the request body with `Model::fromArray()` (or from the query string when the parameter is marked `#[FromQuery]`) and validated. A binding or validation failure returns **400** with `{field: [messages]}` and the action is not called. See [Validation](#validation).
3. **Any other class** — resolved from the DI container, so services can be injected straight into an action.
4. **Scalar/untyped with no matching route parameter** — its default value, or `null` if nullable; otherwise a `RuntimeException`.

```php
use Melodic\Routing\FromQuery;

// GET /api/users?page=2&q=ali — UserFilter is a Model with public ?int $page, ?string $q
public function index(#[FromQuery] UserFilter $filter): JsonResponse { /* ... */ }
```

### Response Helpers

The base `Controller` class provides these helpers:

| Method | Status | Description |
|---|---|---|
| `json($data, $statusCode = 200)` | 200 | JSON response |
| `created($data, $location = null)` | 201 | Created with optional `Location` header |
| `noContent()` | 204 | Empty response |
| `badRequest($data = null)` | 400 | Defaults to `{"error": "Bad Request"}` |
| `unauthorized($data = null)` | 401 | Defaults to `{"error": "Unauthorized"}` |
| `forbidden($data = null)` | 403 | Defaults to `{"error": "Forbidden"}` |
| `notFound($data = null)` | 404 | Defaults to `{"error": "Not Found"}` |

### Requests and Responses

`Request` wraps the superglobals:

| Method | Returns |
|---|---|
| `method()` / `path()` / `uri()` / `queryString()` | `HttpMethod` enum / path / path plus query string / raw query string |
| `query($key, $default)` / `body($key, $default)` | A query or body value, or the whole array when called without a key. `body()` returns form fields, or the decoded JSON body when `JsonBodyParserMiddleware` is active |
| `header($name)` / `bearerToken()` / `cookie($name, $default)` | Case-insensitive header lookup / `Authorization: Bearer` token / cookie value |
| `rawBody()` / `file($key)` | Raw request body / an uploaded-file entry in `$_FILES` shape |
| `scheme()` / `isSecure()` / `host()` / `ip()` | `http` or `https` / HTTPS check / `Host` header (client-controlled) / `REMOTE_ADDR` (forwarded headers are not trusted) |
| `isJson()` / `wantsJson()` | `Content-Type` / `Accept` contains `application/json` |
| `server($key, $default)` | A `$_SERVER` value |
| `getAttribute($name, $default)` / `withAttribute($name, $value)` / `attributes()` | Request attributes set by middleware and the router; `withAttribute()` returns a new instance |

`Response`, `JsonResponse`, and `RedirectResponse` are immutable — `withStatus()`, `withHeader()`, `withoutHeader()`, `withBody()`, and `withCookie()` return new instances. Read them back with `getStatusCode()`, `getHeaders()`, `getHeader($name)` (case-insensitive), and `getBody()`:

```php
use Melodic\Http\RedirectResponse;
use Melodic\Http\Response;

// Cookies default to Secure + HttpOnly + SameSite=Lax, path "/", session lifetime
$response = (new Response(body: 'ok'))->withCookie('theme', 'dark');

// Opt out explicitly for plain-HTTP local development
$response = $response->withCookie('theme', 'dark', ['secure' => false, 'expires' => time() + 86400]);

// Redirects: local() throws InvalidArgumentException unless the target is a same-origin path
return RedirectResponse::local($returnTo);           // "/dashboard" ok; "//evil.com" rejected
return new RedirectResponse('https://example.com');  // any URL — never pass user input here
```

### MVC Controllers

Extend `MvcController` for HTML views with layout support:

```php
use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class HomeController extends MvcController
{
    public function index(): Response
    {
        $this->viewBag->title = 'Home';
        $this->setLayout('layouts/main');
        return $this->view('home/index', ['message' => 'Hello World']);
    }
}
```

`MvcController` adds `view($template, $data)`, `setLayout($layout)`, the `$this->viewBag` store, and `redirect($url, $statusCode = 302)`.

It takes a `ViewEngine` in its constructor. `Application` registers a default one that reads templates from `{basePath}/views` (and uses the bound `CacheInterface`, if any), so no setup is needed. To change the directory, set the `views.path` config key or bind your own:

```php
use Melodic\View\ViewEngine;

$container->singleton(ViewEngine::class, fn() => new ViewEngine('/srv/app/templates'));
```

A subclass that declares its own constructor must accept the `ViewEngine` and pass it to `parent::__construct()`.

### Accessing the User Context

Every controller (API or MVC) can read the authenticated user set by the authentication middleware. `getUserContext()` returns `null` when no authentication middleware ran for the request:

```php
$userContext = $this->getUserContext();

if ($userContext?->isAuthenticated()) {
    $username = $userContext->getUsername();

    if ($userContext->hasEntitlement('admin')) {
        // admin-only logic
    }
}
```

## Dependency Injection

The DI container supports auto-wiring, singletons, interface bindings, and factory closures.

### Registering Services

```php
$app->services(function (Container $container) use ($app) {
    // Singleton with a factory: same instance every time
    $container->singleton(DbContextInterface::class, fn() => new DbContext($app->config('database.dsn')));

    // Transient: a new instance on each get()
    $container->bind(ReportBuilderInterface::class, PdfReportBuilder::class);

    // Singleton interface → implementation. The concrete class is aliased too, so a
    // direct UserService type-hint receives the same shared instance.
    $container->singleton(UserServiceInterface::class, UserService::class);

    // Factories receive the container
    $container->singleton(CacheInterface::class, fn(Container $c) => new FileCache(
        $c->get(Application::class)->getBasePath() . '/storage/cache',
    ));

    // Register an existing instance
    $container->instance(Clock::class, new Clock());
});
```

- Re-registering with `bind()` or `singleton()` discards any cached instance.
- `has($id)` answers "can the container attempt to resolve this?" — it is `true` for explicit bindings *and* for any existing class. Use `isBound($id)` to ask whether an id was explicitly registered.
- The container implements PSR-11 (`Psr\Container\ContainerInterface`), so it can be handed to libraries that accept one.

### Auto-Wiring

The container resolves constructor dependencies via Reflection. Class-typed parameters are resolved recursively; a parameter that cannot be resolved falls back to its default value if it has one:

```php
class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService, // resolved automatically
    ) {}
}

class UserService extends Service
{
    // No constructor needed: the inherited Service constructor receives the
    // DbContextInterface binding.
}
```

Resolution failures throw `Melodic\DI\ContainerException` (non-instantiable class, scalar parameter with no default), its subclass `NotFoundException` (an id that is neither bound nor an existing class), or `CircularDependencyException` for a dependency cycle. All extend `RuntimeException` and implement the PSR-11 exception interfaces. Exceptions thrown by your own factories and constructors propagate unchanged.

### Service Providers

For modular registration, extend `ServiceProvider`:

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(UserServiceInterface::class, UserService::class);
    }

    public function boot(Container $container): void
    {
        // Runs once when the application boots, after every provider has registered
    }
}

$app->register(new AppServiceProvider());
```

Built-in providers: `SecurityServiceProvider`, `LoggingServiceProvider`, `EventServiceProvider`, `SessionServiceProvider`, and `CacheServiceProvider($cacheDir)`.

## CQRS Data Pattern

The framework uses a **Command/Query Responsibility Segregation** pattern. Services instantiate query and command objects directly (no mediator).

### Queries

Queries read data and return typed results:

```php
use Melodic\Data\QueryInterface;
use Melodic\Data\DbContextInterface;

class GetUserByIdQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "SELECT id, username, email FROM users WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): ?UserModel
    {
        return $context->queryFirst(UserModel::class, $this->sql, ['id' => $this->id]);
    }
}
```

### Commands

Commands write data and return the number of affected rows:

```php
use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class CreateUserCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly string $username,
        private readonly string $email,
    ) {
        $this->sql = "INSERT INTO users (username, email) VALUES (:username, :email)";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, [
            'username' => $this->username,
            'email' => $this->email,
        ]);
    }
}
```

### Services

Services orchestrate queries and commands. The base constructor is `Service(DbContextInterface $context, ?DbContextInterface $readOnlyContext = null)`; subclasses use `$this->context` / `getContext()` for writes and `getReadOnlyContext()` for reads (it falls back to the primary context when no read-only context was given):

```php
use Melodic\Service\Service;

class UserService extends Service
{
    public function getAll(): array
    {
        return (new GetAllUsersQuery())->execute($this->getReadOnlyContext());
    }

    public function getById(int $id): ?UserModel
    {
        return (new GetUserByIdQuery($id))->execute($this->getReadOnlyContext());
    }

    public function create(string $username, string $email): int
    {
        (new CreateUserCommand($username, $email))->execute($this->context);
        return $this->context->lastInsertId();
    }
}
```

When a service is auto-wired, both constructor arguments receive the `DbContextInterface` binding. To use a read replica, register the service with a factory that passes two contexts.

### DbContext

`DbContext` wraps PDO with typed query methods and automatic model hydration. Construct it from a DSN (`new DbContext($dsn, $username, $password, $options)`) or an existing `PDO` instance; either way it sets `ERRMODE_EXCEPTION` and `FETCH_ASSOC`.

| Method | Description |
|---|---|
| `query(string $class, string $sql, array $params = []): array` | Execute SELECT, return an array of hydrated objects |
| `queryFirst(string $class, string $sql, array $params = []): ?object` | Execute SELECT, return the first row or `null` |
| `command(string $sql, array $params = []): int` | Execute INSERT/UPDATE/DELETE, return the affected row count |
| `scalar(string $sql, array $params = []): mixed` | Return the first column of the first row; `false` when there are no rows |
| `transaction(callable $callback): mixed` | Run the callback (it receives the context) in a transaction; commits on return, rolls back and rethrows on exception. Nested calls use savepoints |
| `lastInsertId(): int` | Return the last auto-increment ID |

Hydration matches column names to property names exactly and casts values to the declared property type (`int`, `float`, `bool`, `string`, backed enums, `DateTime`/`DateTimeImmutable`); columns without a matching property are ignored. Pass `\stdClass::class` to get plain objects.

Bound parameters are normalized before execution: booleans become ints, backed enums their backing value, and date/time objects a `Y-m-d H:i:s` string.

The concrete `DbContext` class also has `cursor(string $class, string $sql, array $params = []): Generator`, which streams hydrated rows one at a time for exports and large batch jobs. It is not part of `DbContextInterface`, so type-hint `DbContext` where you need it.

### Pagination

`Paginator::paginate()` runs a `COUNT(*)` over a plain SELECT plus the SELECT itself with `LIMIT`/`OFFSET`, and returns a `PaginatedResult`. The SQL must not already contain `LIMIT`/`OFFSET` and should have an `ORDER BY`. Its column names must be unique, because the count wraps it as a subquery (MySQL rejects `SELECT a.*, b.*` there when both tables share a column name) — alias the columns of a join:

```php
use Melodic\Data\PaginatedResult;
use Melodic\Data\Paginator;

public function execute(DbContextInterface $context): PaginatedResult
{
    return Paginator::paginate($context, UserModel::class, $this->sql, [], page: $this->page, perPage: 25);
}
```

`PaginatedResult` exposes `items`, `total`, `page`, `perPage`, `pageCount()`, and `hasMore()`, and serializes to `{items, total, page, perPage, pageCount}`. `perPage` is capped by the `maxPerPage` argument (default 100).

### Migrations

`Melodic\Data\Migration\Migrator` applies plain `.sql` files from a directory in file-name order and records them in a `melodic_migrations` table. An optional `-- @down` line separates the forward SQL from the SQL that undoes it:

```sql
CREATE TABLE users (id INTEGER PRIMARY KEY, email VARCHAR(255) NOT NULL);

-- @down
DROP TABLE users;
```

```php
use Melodic\Data\Migration\Migrator;

$migrator = new Migrator($dbContext, __DIR__ . '/../migrations');
$migrator->migrate();       // apply pending migrations; returns their names
$migrator->rollback(1);     // undo the most recent migration(s)
$migrator->status();        // [['name' => ..., 'applied' => bool, 'appliedAt' => ?string], ...]
```

Statements are split on a `;` at the end of a line. Most databases auto-commit DDL, so a migration that fails part-way is not rolled back — keep each one small. See [Console](#console) for the `migrate` commands.

### Models

Extend `Model` for DTOs with array conversion and request binding:

```php
use Melodic\Data\Guarded;
use Melodic\Data\Model;

class UserModel extends Model
{
    public int $id;
    public string $username;
    public string $email;

    #[Guarded]
    public bool $isAdmin = false;   // never set from request input
}

// Hydrated automatically by DbContext
$user = $context->queryFirst(UserModel::class, "SELECT id, username, email FROM users WHERE id = :id", ['id' => 1]);
$user->toArray(); // ['id' => 1, 'username' => 'alice', 'email' => 'alice@example.com', 'isAdmin' => false]
```

| Method | Description |
|---|---|
| `Model::fromArray(array $data): static` | Hydrate from input. A key matches a property by exact name, then `ucfirst`, then upper-cased (`userName` → `$UserName`, `ein` → `$EIN`); unknown keys are ignored. Scalars are coerced to the declared type (`"5"` → `5`); uncoercible values throw `ModelBindingException`. Nullable properties missing from the input are set to `null` |
| `toArray()` | Initialized public properties with camelCase keys (`$UserName` → `userName`, `$EIN` → `ein`) |
| `toPascalArray()` | Initialized public properties keyed by property name, with booleans as ints, backed enums as their value, and dates as `Y-m-d H:i:s` — for INSERT parameters |
| `toUpdateArray()` | Only the properties that were present in the `fromArray()` input (including explicit nulls) — for partial UPDATEs |
| `wasProvided(string $property)` | Whether a property came from the `fromArray()` input |

Models implement `JsonSerializable` (`toArray()` with date/time values rendered as ISO-8601 strings), so they can be passed straight to `$this->json()`.

`#[Guarded]` (`Melodic\Data\Guarded`) is a mass-assignment defense: a guarded property is skipped by `fromArray()` and therefore never appears in `toUpdateArray()`. It can still be set in code or hydrated from the database.

Properties typed as another `Model` hydrate from nested objects, and an `array` property marked `#[ListOf(OrderLine::class)]` (`Melodic\Data\ListOf`) hydrates each element into that model; binding and validation errors for them use dotted paths such as `address.city` or `lines.0.sku`. Backed-enum and `DateTime`/`DateTimeImmutable` properties are coerced from scalars, and `array` properties reject non-array input.

```php
use Melodic\Data\ListOf;

class OrderRequest extends Model
{
    public AddressModel $address;        // nested object

    #[ListOf(OrderLine::class)]
    public array $lines = [];            // list of OrderLine models
}
```

## Middleware

Global middleware added with `$app->addMiddleware()` runs for every request, in the order added. Route and group middleware (see [Routing](#route-groups-middleware-and-attributes)) runs after the global middleware, only for matching routes. An error-handling middleware is always installed first, so it wraps everything else.

### Built-in Middleware

**CorsMiddleware** — Adds CORS headers and answers preflight `OPTIONS` requests with a 204:

```php
$app->addMiddleware(new CorsMiddleware([
    'allowedOrigins' => ['https://example.com', 'https://*.example.com'],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowedHeaders' => ['Content-Type', 'Authorization'],
    'maxAge' => 3600,
    'allowCredentials' => true,
]));
```

Defaults: `allowedOrigins` `['*']`, `allowedMethods` `GET, POST, PUT, DELETE, PATCH, OPTIONS`, `allowedHeaders` `Content-Type, Authorization`, `maxAge` `86400`, `allowCredentials` `false`. A `*` in an origin matches one or more subdomain levels. `Allow-Methods`, `Allow-Headers`, and `Max-Age` are sent only on preflight responses; requests from origins that are not allowed proceed without CORS headers.

**JsonBodyParserMiddleware** — Parses `application/json` request bodies so they are available via `$request->body()`. Malformed JSON returns a 400:

```php
$app->addMiddleware(new JsonBodyParserMiddleware());
```

**SecurityHeadersMiddleware** (`Melodic\Http\Middleware`) — Adds `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, and `Referrer-Policy: strict-origin-when-cross-origin` to every response, plus HSTS (HTTPS requests only), CSP, and Permissions-Policy when configured. Headers the application already set are not overwritten; set a key to `null` to omit that header:

```php
$app->addMiddleware(new SecurityHeadersMiddleware([
    'hsts' => 'max-age=31536000; includeSubDomains',
    'contentSecurityPolicy' => "default-src 'self'",
    'frameOptions' => 'DENY',
]));
```

**RateLimitMiddleware** (`Melodic\Http\Middleware`) — Fixed-window rate limiter backed by a `CacheInterface`. Over the limit it answers **429** with `Retry-After`; otherwise it adds `X-RateLimit-*` headers to the response. Clients are keyed by `Request::ip()` unless you pass a `keyResolver` (do so behind a reverse proxy). Counting is best-effort — suitable for abuse throttling, not exact quotas. Each instance keeps its own counter (the key covers its paths, methods, limit and window):

```php
// 10 attempts per minute per client on the login callback
$app->addMiddleware(new RateLimitMiddleware($cache, maxAttempts: 10, windowSeconds: 60, paths: ['/auth/callback']));
```

**Authentication, authorization, and CSRF middleware** live in `Melodic\Security` and are registered by `SecurityServiceProvider` — see [Security](#security).

### Custom Middleware

Implement `MiddlewareInterface`:

```php
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class TimingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $start = microtime(true);
        $response = $handler->handle($request);
        $elapsed = microtime(true) - $start;

        return $response->withHeader('X-Response-Time', round($elapsed * 1000) . 'ms');
    }
}
```

Each middleware can:
- Modify the request before passing it down
- Short-circuit the pipeline by returning a response directly
- Modify the response on the way back up

An exception (including the router's 404/405 and the CSRF 403) unwinds past the middleware stack to the error handler, so a middleware's "way back up" code does not run for error responses. A global middleware that only adds headers can also implement `ResponseDecoratorInterface`; its `decorate()` is then applied to error responses as well. `CorsMiddleware` and `SecurityHeadersMiddleware` do this, so error responses carry CORS and security headers (rate-limit headers are not added to them):

```php
use Melodic\Http\Middleware\ResponseDecoratorInterface;

class RequestIdMiddleware implements MiddlewareInterface, ResponseDecoratorInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        return $this->decorate($request, $handler->handle($request));
    }

    public function decorate(Request $request, Response $response): Response
    {
        return $response->withHeader('X-Request-Id', bin2hex(random_bytes(8)));
    }
}
```

Only global middleware (added with `$app->addMiddleware()`) is consulted for error responses, not route middleware.

## Security

Authentication is configuration-driven. `SecurityServiceProvider` reads the `auth` config section and registers the validator, providers, and middleware as singletons; resolve them from the container rather than constructing them yourself.

```php
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\OptionalWebAuthMiddleware;
use Melodic\Security\SecurityServiceProvider;
use Melodic\Security\WebAuthenticationMiddleware;

$app->register(new SecurityServiceProvider());

// Serves the login page, provider login/callback endpoints, and POST /auth/logout
$app->addMiddleware($app->getContainer()->get(AuthCallbackMiddleware::class));

$app->routes(function (Router $router) {
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserApiController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);         // Bearer token required

    $router->group('/account', function (Router $router) {
        $router->get('', AccountController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);         // auth cookie required

    $router->get('/', HomeController::class, 'index', middleware: [OptionalWebAuthMiddleware::class]);
});
```

| Middleware | Behaviour |
|---|---|
| `ApiAuthenticationMiddleware(AuthConfig, JwtValidator, ?LoggerInterface = null)` | Validates the `Authorization: Bearer` token and attaches a `UserContext`; otherwise returns a generic 401 JSON response (the real reason is logged when a `LoggerInterface` is bound). Pass-through with an anonymous context when `auth.api.enabled` is `false` |
| `WebAuthenticationMiddleware(AuthConfig, JwtValidator, SessionManager)` | Validates the auth cookie and attaches a `UserContext`; otherwise remembers the requested URL (local paths only) and redirects to the login page. Pass-through when `auth.web.enabled` is `false` |
| `OptionalWebAuthMiddleware(AuthConfig, JwtValidator)` | Attaches the cookie's `UserContext` or an anonymous one; never blocks. Not registered by the provider — the container auto-wires it |
| `AuthCallbackMiddleware` | Handles `GET {loginPath}`, `GET {loginPath}/{provider}`, `GET\|POST {callbackPath}/{provider}`, and `POST /auth/logout`; passes every other request through |
| `AuthorizationMiddleware(array $requiredEntitlements = [], bool $requireAuthentication = true)` | 401 for anonymous requests, 403 when the user holds none of the required entitlements (constructor list plus the route's `entitlements` attribute). Entitlements are always enforced, even with `requireAuthentication: false` |
| `CsrfMiddleware` | Rejects state-changing requests without the session's CSRF token — see [CSRF Protection](#csrf-protection) |
| `RefreshTokenMiddleware` | Validates the refresh-token cookie — see [docs/refresh-tokens.md](docs/refresh-tokens.md) |

### Configuration

```json
{
    "auth": {
        "local": {
            "signingKey": "<at least 32 characters>",
            "issuer": "my-app",
            "audience": "my-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "entra": {
                "type": "oidc",
                "label": "Sign in with Microsoft",
                "discoveryUrl": "https://login.microsoftonline.com/<tenant>/v2.0/.well-known/openid-configuration",
                "clientId": "<client id>",
                "clientSecret": "<client secret>",
                "redirectUri": "https://app.example.com/auth/callback/entra"
            }
        }
    }
}
```

| Key | Default | Purpose |
|---|---|---|
| `auth.api.enabled` / `auth.web.enabled` | `true` | Turn API / web authentication off (anonymous pass-through) |
| `auth.loginPath` / `auth.callbackPath` / `auth.postLoginRedirect` | `/auth/login` / `/auth/callback` / `/` | Login flow endpoints |
| `auth.cookieName` / `auth.cookieLifetime` | `melodic_auth` / `3600` | Auth cookie holding the JWT |
| `auth.cookieSecure` / `cookieSameSite` / `cookiePath` / `cookieDomain` | `true` / `Lax` / `/` / `""` | Auth cookie attributes (always `HttpOnly`) |
| `auth.local.signingKey` / `issuer` / `audience` / `tokenLifetime` / `algorithm` | — / `melodic-app` / `melodic-app` / `3600` / `HS256` | Signing config for tokens the app issues itself. HMAC keys shorter than 32 characters are rejected. Required by `local` and `oauth2` providers |
| `auth.providers.{name}.type` | `oidc` | `oidc`, `oauth2`, or `local` |
| `auth.providers.{name}.*` | | `label`, `clientId`, `clientSecret`, `redirectUri`, `scopes`, `audience`; `discoveryUrl` and `signingAlg` (default `RS256`) for OIDC; `authorizeUrl`, `tokenUrl`, `userInfoUrl` for OAuth2; `claimMap` to map provider claims onto `sub` / `username` / `email` / `entitlements` (e.g. `{"entitlements": "roles"}`) |
| `auth.oidcCacheDir` | system temp dir | Discovery/JWKS cache location |
| `auth.loginPage.*` | | Branding for the built-in login page (`title`, colors, `logoUrl`, `customCss`, ...) |
| `auth.refreshToken.*` | | `tokenLifetime` (`604800`), `cookieName` (`melodic_refresh`), `cookiePath` (`/auth/refresh`), `cookieSecure`, `cookieSameSite`, `cookieDomain` |

Provider behaviour:

- **`oidc`** — authorization-code flow with PKCE and a nonce; the callback requires an `id_token`, which is validated against the provider's JWKS, issuer, and audience (falling back to `clientId`).
- **`oauth2`** — authorization-code flow with PKCE for providers without OIDC (e.g. GitHub); the userinfo response is mapped to claims and the app issues its own JWT signed with `auth.local`.
- **`local`** — username/password form. Bind your own `LocalAuthenticatorInterface` in the container; it returns the user's claims (`sub`, `username`, `email`, optionally `entitlements`) or throws `SecurityException`.

Auth and session cookies default to `Secure`, so over plain HTTP (typical local development) login appears not to work. Set `auth.cookieSecure` and `session.cookieSecure` to `false` in `config/config.dev.json` only.

Logout is a CSRF-protected `POST /auth/logout`: a non-POST request returns 405 and a missing or invalid `csrf_token` field returns 403. On success it destroys the session, clears the auth and refresh-token cookies, and redirects to `/`. With `CsrfMiddleware` active (see [CSRF Protection](#csrf-protection)) views already have `$csrfToken`; otherwise obtain it with `$container->get(CsrfToken::class)->getToken()` and pass it to your view:

```php
<form method="post" action="/auth/logout">
    <input type="hidden" name="csrf_token" value="<?= $this->e($csrfToken) ?>">
    <button type="submit">Log out</button>
</form>
```

See [docs/authentication.md](docs/authentication.md) and the provider setup guides under [Documentation](#documentation).

### JWT Validation

`JwtValidator(AuthProviderRegistry $registry, ?LocalAuthConfig $localAuthConfig = null)` validates tokens using [firebase/php-jwt](https://github.com/firebase/php-jwt). It takes no secret or algorithm of its own — those come from the `auth` config:

- A token whose `iss` equals `auth.local.issuer` is verified with the local signing key and must carry `exp` and a matching `aud`.
- Any other token is tried against each configured `oidc` provider (signature via JWKS, `exp` present, `iss` equal to the discovery issuer, `aud` equal to the configured audience or client id).

```php
use Firebase\JWT\JWT;
use Melodic\Security\JwtValidator;

/** @var JwtValidator $validator */
$validator = $app->getContainer()->get(JwtValidator::class);

// Validate a token (throws SecurityException on failure)
$claims = $validator->validate($token);

// Issue a local token (useful for tests) — sign it with the auth.local settings
$token = JWT::encode([
    'iss' => 'my-app',
    'aud' => 'my-app',
    'exp' => time() + 3600,
    'sub' => '1',
    'username' => 'alice',
    'email' => 'alice@example.com',
    'entitlements' => ['admin', 'editor'],
], $app->config('auth.local.signingKey'), 'HS256');
```

### User Context

When an authentication middleware has run, controllers can access the user:

```php
$userContext = $this->getUserContext();

$userContext->isAuthenticated();                    // bool
$userContext->getUser();                            // User object or null
$userContext->getUsername();                        // string or null
$userContext->hasEntitlement('admin');              // bool
$userContext->hasAnyEntitlement('admin', 'editor'); // bool
$userContext->getClaim('tenant_id');                // any raw claim, with optional default
$userContext->getClaims();                          // all claims
```

The `UserContext` is built from JWT claims with this mapping:
- `sub` → user ID (string)
- `username`, else `preferred_username`, else `name` → username
- `email` → email
- `entitlements` → array of entitlement strings
- `provider` → the auth provider's name, via `UserContext::getProvider()` (on the concrete class, not the interface)

### Authorization

`AuthorizationMiddleware` checks the `UserContext` set by an authentication middleware that ran before it:

```php
use Melodic\Security\AuthorizationMiddleware;

// Globally: require an authenticated user
$app->addMiddleware(new AuthorizationMiddleware());

// Globally: require at least one of these entitlements
$app->addMiddleware(new AuthorizationMiddleware(requiredEntitlements: ['admin', 'editor']));
```

Route middleware is resolved from the container by class name and so cannot take constructor arguments. Declare per-route requirements with the `entitlements` route attribute instead (the user needs at least one of them; entries are strings or backed enums, and any other type throws rather than being ignored):

```php
$router->get('/api/admin/stats', StatsController::class, 'index',
    middleware: [ApiAuthenticationMiddleware::class, AuthorizationMiddleware::class],
    attributes: ['entitlements' => ['admin']]);
```

### CSRF Protection

`CsrfMiddleware` protects cookie-authenticated requests. Every state-changing request (anything but `GET`, `HEAD`, `OPTIONS`) must carry the session's CSRF token as a `csrf_token` form/JSON field or an `X-CSRF-Token` header, or it is rejected with **403**. Paths under `csrf.exceptPaths` (default `/api`) and requests carrying an `Authorization: Bearer` header are exempt. The token is per-session, so multiple tabs and forms work.

```php
use Melodic\Security\CsrfMiddleware;

$app->addMiddleware($app->getContainer()->get(CsrfMiddleware::class));
```

With it active, `MvcController::view()` passes `$csrfToken` and a ready-made `$csrfField` hidden input to every template (and `$this->csrfToken()` returns the token in the controller):

```php
<form method="post" action="/profile">
    <?= $csrfField ?>
    <button type="submit">Save</button>
</form>
```

## Views

### Template Rendering

Views are `.phtml` files rendered by `ViewEngine`. Data is extracted as local variables. Output is **not** auto-escaped — wrap anything user-supplied in `$this->e()`:

```php
// Controller
$this->setLayout('layouts/main');
return $this->view('home/index', ['message' => 'Hello World', 'items' => $items]);
```

```php
<!-- views/home/index.phtml -->
<h1><?= $this->e($message) ?></h1>
<ul>
    <?php foreach ($items as $item): ?>
        <li><?= $this->e($item) ?></li>
    <?php endforeach ?>
</ul>
```

### Layouts

Layouts wrap view content. Use `renderBody()` to inject the view content:

```php
<!-- views/layouts/main.phtml -->
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->e($viewBag->title ?? 'App') ?></title>
    <?= $this->renderSection('head') ?>
</head>
<body>
    <nav><!-- navigation --></nav>
    <main><?= $this->renderBody() ?></main>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

### Sections

Views can define named sections that are injected into specific places in the layout:

```php
<!-- views/home/index.phtml -->
<h1>Page Content</h1>

<?php $this->beginSection('head') ?>
<meta name="description" content="My page description">
<?php $this->endSection() ?>

<?php $this->beginSection('scripts') ?>
<script src="/app.js"></script>
<?php $this->endSection() ?>
```

### ViewBag

`ViewBag` is a dynamic key-value store for passing data between controllers and layouts. It is available as `$viewBag` in both the view and the layout; unset keys read as `null`:

```php
// Controller
$this->viewBag->title = 'My Page';
$this->viewBag->breadcrumbs = ['Home', 'Users'];
```

```php
<!-- Layout -->
<title><?= $this->e($viewBag->title) ?></title>
```

### Partials

Render another template inline (without a layout) with `partial()`:

```php
<?= $this->partial('shared/nav', ['items' => $items]) ?>
```

## Validation

Attribute-based validation using PHP attributes on DTO properties:

```php
use Melodic\Data\Model;
use Melodic\Validation\Rules\{Required, Email, MinLength, MaxLength, In};

class CreateUserRequest extends Model
{
    #[Required]
    #[MinLength(3)]
    #[MaxLength(50)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;

    #[In(['admin', 'editor', 'viewer'])]
    public ?string $role;      // optional: validated only when present
}
```

Rules are **nullable-by-default**: every rule except `#[Required]` passes when the value is `null` or absent. `#[Required]` rejects `null`, blank strings, and empty arrays.

### Automatic Model Binding

A controller action parameter typed as a `Model` subclass is hydrated from the request body and validated before the action runs:

```php
public function store(CreateUserRequest $input): JsonResponse { /* ... */ }
public function update(int $id, UpdateUserRequest $input): JsonResponse { /* ... */ }
```

A failure returns **HTTP 400** with errors keyed by field, and the action is not called:

```json
{
    "username": ["Must be at least 3 characters"],
    "email": ["Must be a valid email address"]
}
```

Input that cannot be coerced to the property's type (e.g. an array for a `string`, a non-numeric string for an `int`, an unknown backed-enum value) is reported the same way: `{"username": ["Field 'username' must be a string."]}`.

### Manual Validation

Validate objects or raw arrays yourself with `Validator`:

```php
use Melodic\Validation\Validator;

$validator = new Validator();

// Validate an object
$result = $validator->validate($dto);

// Validate raw input against a DTO class (keys must equal the property names)
$result = $validator->validateArray($request->body(), CreateUserRequest::class);

if (!$result->isValid) {
    return $this->badRequest($result->errors);   // ['username' => ['Must be at least 3 characters'], ...]
}
```

Available rules: `#[Required]`, `#[Email]`, `#[MinLength(n)]`, `#[MaxLength(n)]`, `#[Min(n)]`, `#[Max(n)]`, `#[Pattern(regex)]`, `#[In([...])]` (strict comparison), `#[Url]` (absolute http/https), `#[Uuid]`, and `#[Date(format)]` (default `Y-m-d`). All accept a custom `message`.

For a custom rule, write an attribute class that implements `Melodic\Validation\RuleInterface` (`validate(mixed $value): bool`) and exposes a public `$message`.

Nested models — and lists of models declared with `#[ListOf]` — are validated recursively; their errors use dotted keys such as `address.city` or `lines.0.quantity`.

See [docs/validation.md](docs/validation.md) for full details.

## Error Handling

The `ExceptionHandler` turns uncaught exceptions into responses. It returns JSON when the request's `Accept` or `Content-Type` header contains `application/json` or the path is `/api` or starts with `/api/`; otherwise it returns an HTML error page:

```php
// Throw typed exceptions from controllers or middleware
throw new NotFoundException('User not found');           // 404
throw new BadRequestException('Missing required field'); // 400
throw new HttpException(409, 'Resource conflict');       // any status code
```

```json
{ "error": "User not found" }
```

Exception-to-status-code mapping: `HttpException` uses its status code, `SecurityException` maps to 401, `JsonException`, `ValidationException`, and `ModelBindingException` to 400, everything else to 500. A `ValidationException` thrown by your code produces the same field-keyed body as automatic validation (`{"email": ["Already taken"]}`). With `app.debug` enabled, responses include the exception class, file, line, and stack trace. Otherwise 5xx responses return a generic message and `SecurityException` messages are replaced with `Authentication failed.`. Exceptions are logged through the bound `LoggerInterface`, if any — server errors with the stack trace, client errors (4xx) as a single line.

Register a mapper to give an exception class (and its subclasses) its own response, typically from a provider's `boot()`. Mappers run before the built-in mapping, and the log entry uses the status the mapper returns — for example, to answer validation failures with 422 instead of 400:

```php
use Melodic\Error\ExceptionHandler;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Validation\ValidationException;

public function boot(Container $container): void
{
    $container->get(ExceptionHandler::class)->registerMapper(
        ValidationException::class,
        fn(ValidationException $e, Request $request) => new JsonResponse($e->result->errors, 422),
    );
}
```

Error responses also receive the headers of global middleware that implements `ResponseDecoratorInterface` (CORS, security headers) — see [Custom Middleware](#custom-middleware).

See [docs/error-handling.md](docs/error-handling.md) for full details.

## Events

Priority-based event dispatcher for decoupling components. Listeners with a higher priority run first:

```php
use Melodic\Event\Event;
use Melodic\Event\EventDispatcher;

class UserRegistered extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {}
}

$dispatcher = new EventDispatcher();

$dispatcher->listen(UserRegistered::class, function (UserRegistered $event) {
    sendWelcomeEmail($event->email);
}, priority: 10);

$dispatcher->dispatch(new UserRegistered(userId: 42, email: 'alice@example.com'));
```

Any object can be dispatched; events extending the `Event` base class support `stopPropagation()`. `EventServiceProvider` registers a shared `EventDispatcherInterface`. `EventDispatcher::removeListener()` deregisters a listener.

See [docs/events.md](docs/events.md) for full details.

## Cache

PSR-16-style caching with `FileCache` and `ArrayCache` drivers:

```php
use Melodic\Cache\FileCache;

$cache = new FileCache('/path/to/cache');
$cache->set('user:42', $userData, ttl: 3600);
$cache->get('user:42');
$cache->get('missing', 'fallback');
```

Register a `FileCache` as `CacheInterface` with `$app->register(new CacheServiceProvider($cacheDir))`. `FileCache` keeps its directory at `0700` and its entry files at `0600`. A `ViewEngine` constructed with a cache (`new ViewEngine($viewsPath, $cache)`) supports cached rendering via `renderCached()`. To use Redis, Memcached, or any other PSR-16 cache, wrap it: `$container->singleton(CacheInterface::class, fn() => new Psr16CacheAdapter($psr16Cache))` (requires `psr/simple-cache`).

See [docs/cache.md](docs/cache.md) for full details.

## Session

Session abstraction with `NativeSession` (wraps PHP sessions) and `ArraySession` (for testing):

```php
$session->set('user_id', 42);
$session->get('user_id');       // 42
$session->regenerate();         // new session ID
$session->destroy();            // clear everything and expire the session cookie
```

`SessionServiceProvider` registers a `NativeSession` as `SessionInterface`, configured from the `session.*` config keys. The session cookie is `Secure` + `HttpOnly` + `SameSite=Lax` by default (set `session.cookieSecure` to `false` for plain-HTTP development), and sessions run in strict mode with cookie-only ids. The login flow uses `Melodic\Security\SessionManager` (a `NativeSession` configured from the same keys) and regenerates the session ID after a successful login; when no `SessionInterface` is bound, `SecurityServiceProvider` binds it to that `SessionManager`.

See [docs/session.md](docs/session.md) for full details.

## Logging

File logger with level filtering and message interpolation. It writes one file per day, `melodic-YYYY-MM-DD.log`:

```php
use Melodic\Log\FileLogger;
use Melodic\Log\LogLevel;

$logger = new FileLogger('/path/to/logs', LogLevel::INFO);   // minimum level; default DEBUG
$logger->info('User {username} logged in', ['username' => 'alice']);
$logger->error('Failed', ['exception' => $e]); // includes stack trace
$logger->debug('Not written: below the minimum level');
```

`LoggingServiceProvider` registers a `FileLogger` as `LoggerInterface` using `logging.path` (default `{basePath}/logs`; a relative path resolves against the working directory, so prefer an absolute one) and `logging.level` (default `debug`). Set `logging.maxFiles` (the third `FileLogger` argument) to keep only the newest N daily files. `NullLogger` is available for testing, and `PsrLoggerAdapter` wraps any PSR-3 logger (e.g. Monolog) as a `LoggerInterface` (requires `psr/log`).

See [docs/logging.md](docs/logging.md) for full details.

## Console

`Console` is a small command runner. The framework's own `vendor/bin/melodic` registers only the scaffolding commands (`make:project`, `make:entity`, `make:config`, `claude:install`). The route, cache, and migration commands need your application's router, cache, and database, so register them in your app's `bin/console` (the scaffolded one starts with no commands):

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Cache\CacheInterface;
use Melodic\Console\CacheClearCommand;
use Melodic\Console\Console;
use Melodic\Console\MakeMigrationCommand;
use Melodic\Console\MigrateCommand;
use Melodic\Console\MigrateRollbackCommand;
use Melodic\Console\MigrateStatusCommand;
use Melodic\Console\RouteListCommand;
use Melodic\Data\DbContextInterface;
use Melodic\Data\Migration\Migrator;

$app = require __DIR__ . '/../bootstrap.php';   // your file: build and return the Application without calling run()
$container = $app->getContainer();
$migrations = __DIR__ . '/../migrations';

$console = new Console();
$console->register(new RouteListCommand($app->getRouter()));
$console->register(new CacheClearCommand($container->get(CacheInterface::class)));

$migrator = new Migrator($container->get(DbContextInterface::class), $migrations);
$console->register(new MigrateCommand($migrator));
$console->register(new MigrateRollbackCommand($migrator));
$console->register(new MigrateStatusCommand($migrator));
$console->register(new MakeMigrationCommand($migrations));

exit($console->run($argv));
```

```bash
php bin/console                              # list available commands
php bin/console route:list                   # list all registered routes
php bin/console cache:clear                  # clear the application cache
php bin/console make:migration create_users  # create migrations/{timestamp}_create_users.sql
php bin/console migrate                      # apply pending migrations
php bin/console migrate:status               # show applied and pending migrations
php bin/console migrate:rollback --steps=2   # undo the last N migrations (default 1)
```

Extend `Command` to write custom commands — call `parent::__construct($name, $description)`, implement `execute(array $args): int`, and use the `writeln()`, `write()`, `error()`, and `table()` output helpers.

See [docs/console.md](docs/console.md) for full details.

## Conventions

- **PHP 8.2+** features throughout: enums, readonly properties, constructor promotion, match expressions, named arguments
- **PascalCase** for classes, **camelCase** for methods and properties
- **Controller → Service → Query/Command** — controllers never access the database directly
- **CQRS data access** — Query/Command objects executed via DbContext
- **No facades, no mediator** — dependencies are explicit and directly instantiated
- **Immutable request/response** — `withAttribute()`, `withHeader()`, etc. return new instances
- **Secure by default** — auth, session, and response cookies default to `Secure` + `HttpOnly` + `SameSite=Lax`; logout is a CSRF-protected POST; JWTs require `iss` and `exp`; OAuth2/OIDC use PKCE

## Architecture Decisions

### Custom Request/Response vs PSR-7

Melodic uses lightweight, purpose-built `Request` and `Response` classes rather than implementing PSR-7 (`psr/http-message`). Both classes follow an immutable builder pattern (`withAttribute()`, `withHeader()`, `withStatus()`, etc.) and are designed specifically for the framework's middleware pipeline.

PSR-7 was not adopted because its full interface surface — `StreamInterface`, `UriInterface`, `ServerRequestInterface` with 20+ methods, and mandatory stream-wrapping of all message bodies — adds significant complexity for minimal benefit in a framework that controls the entire HTTP stack. Melodic's Request and Response expose only what the middleware pipeline, router, and controllers actually need, resulting in simpler, faster objects that are easier to understand and debug.

If you need to integrate third-party PSR-7 middleware, you can write thin adapter classes that map between `Melodic\Http\Request`/`Response` and the PSR-7 interfaces.

## Recommended Application Structure

When building an application with Melodic, use this canonical directory layout. The default project type (`full`) includes both MVC views and API routing — most real-world apps need both.

```
my-app/
├── composer.json               # PSR-4: App\ → src/
├── config/
│   ├── config.json             # Base configuration
│   ├── config.qa.json          # Environment overrides (APP_ENV=qa)
│   ├── config.pd.json          # Environment overrides (APP_ENV=pd)
│   └── config.dev.json         # Local overrides, gitignored (create it yourself)
├── public/
│   ├── index.php               # Entry point
│   └── .htaccess
├── bin/
│   └── console                 # CLI entry point
├── src/
│   ├── Controllers/            # ApiController and MvcController subclasses
│   ├── Services/               # Service subclasses (business logic)
│   ├── DTO/                    # Models extending Melodic\Data\Model
│   ├── Data/
│   │   └── {Entity}/
│   │       ├── Queries/        # QueryInterface implementations
│   │       └── Commands/       # CommandInterface implementations
│   ├── Middleware/             # Custom middleware
│   └── Providers/
│       └── AppServiceProvider.php
├── views/
│   ├── layouts/
│   │   └── main.phtml          # Base HTML layout
│   └── home/
│       └── index.phtml         # Home page template
├── migrations/                 # SQL migrations (created by make:migration)
├── storage/
│   ├── cache/
│   └── logs/
└── tests/
```

API and MVC controllers coexist in the same `src/Controllers/` directory. Use route groups to organize them:

```php
$app->routes(function (Router $router) {
    // MVC routes
    $router->get('/', HomeController::class, 'index');

    // API routes
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserController::class);
    });
});
```

### Project Types

| Type | Flag | Description |
|---|---|---|
| Full (default) | `--type=full` or omit | MVC views + API routing — recommended for most apps |
| API only | `--type=api` | No views directory, API-only `index.php` |
| MVC only | `--type=mvc` | Views + HomeController, MVC-only `index.php` |

### Naming Conventions

| Type | Location | Naming | Example |
|---|---|---|---|
| DTO / Model | `src/DTO/` | `{Entity}Model` | `ChurchModel` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery`, `GetAll{Plural}Query` | `GetChurchByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create{Entity}Command`, `Update{Entity}Command`, `Delete{Entity}Command` | `CreateChurchCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ChurchService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ChurchController` |
| Provider | `src/Providers/` | `{Name}ServiceProvider` | `AppServiceProvider` |

### Scaffolding

The `melodic` CLI generates projects, entity CQRS scaffolding, and config files:

```bash
# Create a new project (full — MVC + API, the default)
vendor/bin/melodic make:project my-app

# API-only project
vendor/bin/melodic make:project my-api --type=api

# MVC-only project
vendor/bin/melodic make:project my-site --type=mvc

# Inside a project: generate entity files (DTO, queries, commands, service, controller)
cd my-app
vendor/bin/melodic make:entity Church

# Create config/config.staging.json
vendor/bin/melodic make:config staging

# Install the Melodic Claude Code agent and skills (--force overwrites them) and a CLAUDE.md that imports MELODIC_PHP.md
vendor/bin/melodic claude:install
```

`make:project` creates the layout above (without `config.dev.json` and `migrations/`), a `composer.json` requiring `melodicdev/framework` at the generating framework's minor version (`^4.1`), a `.gitignore`, and — for `full` and `mvc` — a `HomeController`, layout, and home view.

`make:entity` generates 8 files per entity following the CQRS pattern:
- `src/DTO/{Entity}Model.php`
- `src/Data/{Entity}/Queries/GetAll{Plural}Query.php`
- `src/Data/{Entity}/Queries/Get{Entity}ByIdQuery.php`
- `src/Data/{Entity}/Commands/Create{Entity}Command.php`
- `src/Data/{Entity}/Commands/Update{Entity}Command.php`
- `src/Data/{Entity}/Commands/Delete{Entity}Command.php`
- `src/Services/{Entity}Service.php`
- `src/Controllers/{Entity}Controller.php`

The generated create and update commands contain clearly marked `TODO` SQL placeholders — fill in the columns before using them. Existing files are never overwritten — the command skips any file that already exists. Project, entity, and environment names must be simple identifiers (letters, digits, dashes, underscores); a mixed-case entity name such as `BlogPost` is kept as-is.

## Documentation

| Document | Contents |
|---|---|
| [MELODIC_PHP.md](MELODIC_PHP.md) | Usage reference for projects built with Melodic — rules, bootstrap, routing, models, data access, auth, testing, a worked example, common mistakes, and an API index. Ships in the package at `vendor/melodicdev/framework/MELODIC_PHP.md` |
| [CHANGELOG.md](CHANGELOG.md) | Release notes |
| [MIGRATION-3.0.md](MIGRATION-3.0.md) / [MIGRATION-4.0.md](MIGRATION-4.0.md) | Upgrade guides for the 3.0 and 4.0 breaking changes (4.1 is backwards compatible with 4.0) |
| [docs/configuration.md](docs/configuration.md) | Environment config files and merge rules |
| [docs/validation.md](docs/validation.md) | Validation rules and model binding |
| [docs/error-handling.md](docs/error-handling.md) | Exception handling and HTTP exceptions |
| [docs/authentication.md](docs/authentication.md) | Authentication overview |
| [docs/refresh-tokens.md](docs/refresh-tokens.md) | Refresh-token rotation |
| Provider setup | [Local](docs/setup-local.md) · [Microsoft Entra](docs/setup-entra.md) · [Google](docs/setup-google.md) · [GitHub](docs/setup-github.md) · [Facebook](docs/setup-facebook.md) · [Okta](docs/setup-okta.md) |
| [docs/events.md](docs/events.md) | Event dispatcher |
| [docs/cache.md](docs/cache.md) | Cache drivers |
| [docs/session.md](docs/session.md) | Session abstraction |
| [docs/logging.md](docs/logging.md) | Logging |
| [docs/console.md](docs/console.md) | Console commands |
| [docs/claude-code.md](docs/claude-code.md) | Claude Code agent and skills |
| [docs/roadmap.md](docs/roadmap.md) | What has shipped and what is still open, as of 4.1.0 |
| [docs/framework-review.md](docs/framework-review.md) | Status review as of 4.1.0: strengths, security posture, and known limitations |
| [PUBLISHING.md](PUBLISHING.md) | Releasing a new version to Packagist |

The `docs/` directory is excluded from Composer dist installs (see `.gitattributes`); read it in the repository.

## Development

To work on the framework itself:

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan level 6 — keep it at 0 errors
```

## Dependencies

| Package | Purpose |
|---|---|
| `firebase/php-jwt` (`^6.0 \|\| ^7.0`) | JWT encoding and decoding |
| `psr/container` (`^1.1 \|\| ^2.0`) | PSR-11 container interfaces |
| `psr/log`, `psr/simple-cache` (suggested) | Needed only for `PsrLoggerAdapter` / `Psr16CacheAdapter` |
| `ext-mbstring` (suggested) | Character-accurate length checks in `#[MinLength]` / `#[MaxLength]` |
| `phpunit/phpunit`, `phpstan/phpstan` (dev) | Tests and static analysis |
