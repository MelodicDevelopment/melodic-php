# Melodic PHP Framework

Modern PHP 8.2+ framework with CQRS, JWT authentication, and middleware pipeline. Uses a layered architecture (Controller → Service → Query/Command). This repository is the **framework itself** (`melodicdev/framework`); applications built with it live elsewhere.

## Where the documentation lives

| File | Audience | Shipped in the Composer dist? |
|---|---|---|
| [`MELODIC_PHP.md`](MELODIC_PHP.md) | **Consumer projects** — the authoritative "how to use Melodic" reference. Other projects import it from `vendor/melodicdev/framework/MELODIC_PHP.md` | Yes |
| [`README.md`](README.md) | Overview + feature tour | Yes |
| [`CHANGELOG.md`](CHANGELOG.md), `MIGRATION-3.0.md`, `MIGRATION-4.0.md` | Upgraders | Yes |
| `docs/*.md` | Topic guides (authentication, validation, configuration, console, …) | **No** (`export-ignore`) |
| `.claude/agents/`, `.claude/skills/` | Copied into consumer projects by `claude:install` | Yes |
| `CLAUDE.md` (this file), `PUBLISHING.md` | Framework maintainers | No |

**When you change public API or behaviour, update the docs in the same change:**
`MELODIC_PHP.md` (always — consumers and their AI assistants treat it as ground truth), the matching `docs/*.md`, `README.md`, `CHANGELOG.md`, and the `.claude/` agent/skills if their templates are affected. The source is the truth; never document something the code does not do.

## Architecture

```
HTTP Request → Middleware Pipeline → Router → Controller → Service → Query/Command → Database
```

### Namespace Mapping
- `Melodic\Core` — `Application` (bootstrap, `boot()`/`handle()`/`run()`), `Configuration` (JSON, dot-notation, `${ENV}` placeholders)
- `Melodic\Http` — `Request`, `Response`, `JsonResponse`, `RedirectResponse`, `HttpMethod`, `Exception\*`
- `Melodic\Http\Middleware` — `Pipeline`, `CorsMiddleware`, `JsonBodyParserMiddleware`, `SecurityHeadersMiddleware`, `RateLimitMiddleware`, `ResponseDecoratorInterface`
- `Melodic\Routing` — `Router` (groups, `apiResource()`, named routes, constraints), `Route`, `RoutingMiddleware` (action argument resolution + model binding), `FromQuery`
- `Melodic\Controller` — `Controller`, `ApiController`, `MvcController`
- `Melodic\DI` — `Container` (auto-wiring, PSR-11), `ServiceProvider`, container exceptions
- `Melodic\Data` — `DbContext` (PDO wrapper), CQRS interfaces, `Model`, `Guarded`, `ListOf`, `Paginator`, `Migration\Migrator`
- `Melodic\Security` — JWT validation, OAuth2/OIDC/local providers, refresh tokens, CSRF, auth/authorization middleware
- `Melodic\Service` — Base service class
- `Melodic\View` — Template engine with layouts, sections and partials (use `$this->e()` to escape output)
- `Melodic\Validation` — Attribute-based DTO validation (`#[Required]`, `#[Email]`, …); rules are nullable-by-default; `RuleInterface` for custom rules
- `Melodic\Cache` — PSR-16-style cache (`FileCache`, `ArrayCache`, `Psr16CacheAdapter`)
- `Melodic\Session` — Session abstraction (`NativeSession`, `ArraySession`)
- `Melodic\Log` — File/Null loggers with level filtering, `PsrLoggerAdapter`
- `Melodic\Event` — Priority-based event dispatcher
- `Melodic\Error` — `ExceptionHandler` (JSON/HTML, debug/prod, status mapping, custom mappers)
- `Melodic\Console` — CLI runner, `make:*` scaffolding, migration commands, `claude:install`

## Project Structure

```
melodic-php/
├── composer.json                # PSR-4: Melodic\ → src/ ; bin: bin/melodic
├── bin/melodic                  # Framework CLI (make:project, make:entity, make:config, claude:install)
├── src/
│   ├── Framework.php            # Framework::VERSION — single source of truth
│   ├── Core/                    # Application, Configuration
│   ├── Http/                    # Request, Response, JsonResponse, RedirectResponse, HttpMethod
│   │   ├── Exception/           # HttpException, NotFound, BadRequest, MethodNotAllowed
│   │   └── Middleware/          # Pipeline + built-in middleware
│   ├── Routing/                 # Router, Route, RoutingMiddleware, FromQuery
│   ├── Controller/              # Controller, ApiController, MvcController
│   ├── DI/                      # Container, ContainerInterface, ServiceProvider, exceptions
│   ├── Data/                    # DbContext(+Interface), Model, Guarded, Query/CommandInterface,
│   │   │                        # Paginator, PaginatedResult, ModelBindingException
│   │   └── Migration/           # Migrator (plain-SQL migrations)
│   ├── Security/                # SecurityServiceProvider, auth providers, middleware, CSRF, refresh tokens
│   ├── Service/                 # Service base class
│   ├── Validation/              # Validator, ValidationResult, RuleInterface, Rules/*
│   ├── View/                    # ViewEngine, ViewBag
│   ├── Cache/ Session/ Log/ Event/ Error/
│   └── Console/                 # Console, Command, route:list, cache:clear, migrate*, Make/*
├── tests/                       # PHPUnit 11 (mirrors src/), tests/livetest = manual Entra OIDC app
├── docs/                        # Topic guides (not shipped in the dist)
├── MELODIC_PHP.md               # Consumer usage reference (shipped)
├── .claude/
│   ├── agents/melodic-expert.md # Shipped to consumers via claude:install
│   ├── skills/melodic:*/        # scaffold-app, scaffold-resource, add-middleware (shipped)
│   └── commands/melodic:cp.md   # Commit and push shortcut (this repo only)
└── .github/workflows/tests.yml  # CI
```

## Key Patterns

### CQRS — Query/Command in Services (no mediator)

```php
// Query class — SQL in constructor, execute with DbContext
class GetUserByIdQuery implements QueryInterface {
    private readonly string $sql;

    public function __construct(private readonly int $id) {
        $this->sql = "SELECT * FROM users WHERE id = :id";
    }

    public function getSql(): string { return $this->sql; }

    public function execute(DbContextInterface $context): ?User {
        return $context->queryFirst(User::class, $this->sql, ['id' => $this->id]);
    }
}

// Service — instantiates queries/commands directly
class UserService extends Service {
    public function getById(int $id): ?User {
        return (new GetUserByIdQuery($id))->execute($this->context);
    }
}
```

### Application Bootstrap

```php
$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();  // config.json → config.{APP_ENV}.json → config.dev.json
$app->register(new SecurityServiceProvider());   // binds JwtValidator, auth middleware, CSRF, …
$app->register(new AppServiceProvider());
$app->addMiddleware(new CorsMiddleware($app->config('cors', [])));
$app->addMiddleware(new JsonBodyParserMiddleware());
$app->routes(function (Router $r) {
    $r->group('/api', function (Router $r) {
        $r->apiResource('/users', UserController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);   // route middleware = class names
});
$app->run();                    // or $app->handle($request) to get the Response (tests)
```

There is no `AuthenticationMiddleware` class: use `ApiAuthenticationMiddleware` (bearer token), `WebAuthenticationMiddleware` (cookie, redirects to login), `OptionalWebAuthMiddleware`, and `AuthCallbackMiddleware` (login/callback/logout endpoints), all resolved from the container.

### Environment Configuration

```
config.json  →  config.{APP_ENV}.json  →  config.dev.json
  (base)         (env overrides)          (dev overrides, gitignored)
```

- `APP_ENV` env var selects the environment file (e.g., `qa` → `config.qa.json`)
- When `APP_ENV` is unset or `dev`, no environment file is loaded (just base + dev)
- `config.dev.json` is always loaded last if it exists (always gitignored)
- `app.environment` is auto-set in config so code can check the current env via `$app->getEnvironment()`
- Maps deep-merge; **lists replace wholesale**; `${VAR}` / `${VAR:-default}` placeholders resolve from the environment

See [docs/configuration.md](docs/configuration.md) for full details.

### DI Container

```php
// Interface binding
$container->bind(UserServiceInterface::class, UserService::class);

// Singleton with factory
$container->singleton(DbContextInterface::class, fn() => new DbContext($dsn, $user, $pass));

// Auto-wiring resolves constructor dependencies automatically
$controller = $container->get(UserController::class); // injects UserService, etc.
```

`has()` answers "resolvable" (true for any class); `isBound()` answers "explicitly registered".

### Routing

```php
$router->get('/users/{id:\d+}', UserController::class, 'show', name: 'users.show');
$router->apiResource('/users', UserController::class); // index, show, store, update (PUT+PATCH), destroy
$router->group('/api/admin', function ($r) {
    $r->apiResource('/users', UserAdminController::class);
}, middleware: [AuthorizationMiddleware::class], attributes: ['entitlements' => ['admin']]);
$router->url('users.show', ['id' => 5]);               // "/users/5"
```

### Automatic Model Binding

Controller action parameters typed as a `Model` subclass are automatically hydrated from the request body (or the query string with `#[FromQuery]`) and validated. If binding or validation fails, a 400 JSON response (`{field: [messages]}`) is returned before the controller is called. Route parameters bind by name and are coerced to the declared type; other class-typed parameters are injected from the container.

```php
// Model subclass with validation attributes
class CreateUserRequest extends Model {
    #[Required] #[MaxLength(50)]
    public string $username;
    #[Required] #[Email]
    public string $email;
}

// Controller — $request is hydrated and validated automatically
public function store(CreateUserRequest $request): JsonResponse { /* ... */ }
public function update(int $id, UpdateUserRequest $request): JsonResponse { /* ... */ }
```

See [docs/validation.md](docs/validation.md) for full details.

### JWT Authentication

```php
// ApiAuthenticationMiddleware reads the Bearer token, validates the JWT, sets UserContext
// In any controller (null when no auth middleware ran):
$userContext = $this->getUserContext();
if ($userContext?->hasEntitlement('admin')) { /* ... */ }
```

See [docs/authentication.md](docs/authentication.md) and [docs/refresh-tokens.md](docs/refresh-tokens.md).

### MVC Views

```php
// Controller
$this->setLayout('layouts/main');
return $this->view('home/index', ['message' => 'Hello']);

// Template (home/index.phtml) — output is NOT auto-escaped
<h1><?= $this->e($message) ?></h1>
<?= $this->partial('shared/nav') ?>
<?php $this->beginSection('scripts') ?>
<script src="/app.js"></script>
<?php $this->endSection() ?>

// Layout uses: <?= $this->renderBody() ?> and <?= $this->renderSection('scripts') ?>
```

`Application` binds a default `ViewEngine` at `{basePath}/views` (config `views.path`).

## Application Structure (for projects built with Melodic)

When generating code for applications that use this framework, follow this canonical layout (and `MELODIC_PHP.md`, which is the full reference). The default project type (`full`) includes both MVC views and API routing.

```
my-app/
├── composer.json               # PSR-4: {PascalCasedProjectName}\ → src/
├── config/
│   ├── config.json
│   ├── config.qa.json / config.pd.json
│   └── config.dev.json         # gitignored
├── public/
│   ├── index.php               # Entry point
│   └── .htaccess
├── bin/
│   └── console                 # CLI entry point (register route:list, cache:clear, migrate* here)
├── migrations/                 # Timestamped .sql files (make:migration)
├── src/
│   ├── Controllers/            # ApiController and MvcController subclasses
│   ├── Services/               # Service subclasses (business logic layer)
│   ├── DTO/                    # Models extending Melodic\Data\Model (flat)
│   ├── Data/
│   │   └── {Entity}/
│   │       ├── Queries/        # QueryInterface implementations
│   │       └── Commands/       # CommandInterface implementations
│   ├── Middleware/              # Custom MiddlewareInterface implementations
│   └── Providers/
│       └── AppServiceProvider.php
├── views/                      # MVC templates (full and mvc types)
│   ├── layouts/
│   └── {page}/
├── storage/
│   ├── cache/
│   └── logs/
└── tests/
```

Project types: `full` (default, MVC + API), `api` (no views), `mvc` (views only).

### Naming Conventions for Application Code

| Type | Location | Naming | Example |
|---|---|---|---|
| DTO / Model | `src/DTO/` | `{Entity}Model` | `ChurchModel` |
| Request DTO | `src/DTO/` | `Create{Entity}Request`, `Update{Entity}Request` | `CreateChurchRequest` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery`, `GetAll{Plural}Query` | `GetChurchByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create{Entity}Command`, `Update{Entity}Command`, `Delete{Entity}Command` | `CreateChurchCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ChurchService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ChurchController` |
| Provider | `src/Providers/` | `{Name}ServiceProvider` | `AppServiceProvider` |

### Scaffolding Commands

```bash
vendor/bin/melodic make:project my-app                 # Full project (MVC + API, default)
vendor/bin/melodic make:project my-api --type=api      # API-only project
vendor/bin/melodic make:project my-site --type=mvc     # MVC-only project
vendor/bin/melodic make:entity Church                  # Generate 8 CQRS files for an entity
vendor/bin/melodic make:config staging                 # Create config/config.staging.json
vendor/bin/melodic claude:install                      # Install Claude Code agents and skills
```

`make:entity` generates: DTO model, 2 queries (GetAll, GetById), 3 commands (Create, Update, Delete), service, and controller.
`make:config` creates an environment-specific config file. See [docs/configuration.md](docs/configuration.md).
`claude:install` copies Claude Code agents and skills into the project and adds a `MELODIC_PHP.md` reference to its CLAUDE.md. See [docs/claude-code.md](docs/claude-code.md).
App-level commands (`route:list`, `cache:clear`, `migrate`, `migrate:rollback`, `migrate:status`, `make:migration`) are registered by the application in its own `bin/console`. See [docs/console.md](docs/console.md).

## Conventions

- **Never add Co-Authored-By, Signed-off-by, or any AI/contributor attribution to commits**
- **PHP 8.2+**: enums, readonly properties, constructor promotion, match expressions
- **PascalCase** classes, **camelCase** methods/properties
- **Controller → Service → Query/Command** — no direct DB access in controllers
- **CQRS data access** — Query/Command objects executed via DbContext
- **No facades, no mediator** — direct, explicit instantiation
- **Secure by default** — auth/session cookies default to Secure+HttpOnly+SameSite; logout is a CSRF-protected POST; JWTs require `iss`+`exp`; OAuth2/OIDC use PKCE. See `MIGRATION-3.0.md` and `MIGRATION-4.0.md`.
- **Static analysis** — `composer analyse` (PHPStan level 6) is **clean (0 errors)**. Keep it that way: annotate iterable types (`array<K, V>`) on new code; CI should fail on any regression.
- **Docs move with code** — see "Where the documentation lives" above.

## Versioning & Publishing

The framework version is tracked in `src/Framework.php` (`Framework::VERSION`). This is the single source of truth — the `Console` class reads it automatically.

To publish a new release:
1. Bump `Framework::VERSION` in `src/Framework.php` (follow semver: patch for fixes, minor for features, major for breaking changes)
2. Update `CHANGELOG.md` and the version line at the top of `MELODIC_PHP.md`
3. Commit and push
4. Tag with the same version: `git tag v{VERSION} && git push origin v{VERSION}`

See `PUBLISHING.md` for full details.

## Getting Started

```bash
composer install
composer test        # PHPUnit
composer analyse     # PHPStan level 6
```

To try the framework end to end, scaffold a throwaway app outside this repo with `bin/melodic make:project` and point its `composer.json` at this checkout with a `path` repository.

## Dependencies

- `firebase/php-jwt` — JWT encoding/decoding
- `psr/container` — PSR-11 interfaces
- PHP 8.2+ with the PDO extension (`ext-mbstring` suggested)
