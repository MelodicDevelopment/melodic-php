# Using the Melodic PHP Framework

> **Framework version:** 4.1.0 · **PHP:** 8.2+ · **Package:** `melodicdev/framework` · **License:** MIT

This is the reference for **projects that are built with Melodic**. It ships
inside the Composer package, so it always matches the installed version:

```
vendor/melodicdev/framework/MELODIC_PHP.md
```

It tells you — or the AI assistant working in your repository — how an
application is meant to be structured, which framework APIs to reach for, and
which mistakes to avoid. Every signature and behaviour described here was
checked against the 4.1.0 source; where this file and an older tutorial or
blog post disagree, this file is right.

**Point your project at it.** In your project's `CLAUDE.md` (or equivalent
assistant instructions file):

```markdown
## Melodic framework reference

Before writing or changing code that uses the Melodic framework, read:

@vendor/melodicdev/framework/MELODIC_PHP.md
```

`vendor/bin/melodic claude:install` writes that section for you (and appends it
to an existing `CLAUDE.md` that does not mention this file yet), alongside the
Melodic agent and skills.

---

## Contents

1. [The rules](#1-the-rules)
2. [Install and project layout](#2-install-and-project-layout)
3. [Bootstrap](#3-bootstrap)
4. [Configuration](#4-configuration)
5. [Dependency injection](#5-dependency-injection)
6. [Routing](#6-routing)
7. [Controllers](#7-controllers)
8. [Models, binding and validation](#8-models-binding-and-validation)
9. [Data access: queries, commands, services](#9-data-access-queries-commands-services)
10. [Views (MVC)](#10-views-mvc)
11. [Authentication and authorization](#11-authentication-and-authorization)
12. [Middleware](#12-middleware)
13. [Errors, logging, cache, sessions, events](#13-errors-logging-cache-sessions-events)
14. [Console and migrations](#14-console-and-migrations)
15. [Testing](#15-testing)
16. [Worked example: a complete resource](#16-worked-example-a-complete-resource)
17. [Common mistakes](#17-common-mistakes)
18. [Quick API index](#18-quick-api-index)

---

## 1. The rules

Melodic is small and explicit on purpose. An application that follows these
rules stays readable; one that fights them gets nothing back for the effort.

**Architecture**

1. **One direction of flow:** `Controller → Service → Query/Command → DbContext`.
   Controllers never touch `DbContext` or write SQL. Services never build
   HTTP responses.
2. **CQRS without a mediator.** Each SQL statement lives in its own
   `QueryInterface` or `CommandInterface` class. A service runs it directly:
   `(new GetUserByIdQuery($id))->execute($this->context)`. No bus, no
   repositories-of-everything, no ORM, no facades, no static service locator.
3. **Constructor injection only.** Declare dependencies as typed constructor
   parameters; the container auto-wires them. Never call the container from
   inside a controller or service.
4. **Wire the application in one place.** Bindings go in a `ServiceProvider`;
   middleware and routes go in the bootstrap file.

**Input and output**

5. **Input arrives as a typed `Model`.** Type-hint a `Model` subclass on a
   controller action and the framework hydrates, coerces and validates it
   before your code runs. Do not hand-parse `$request->body()` and do not
   validate by hand.
6. **One request DTO per endpoint.** `CreateXRequest` and `UpdateXRequest` are
   separate from the entity model `XModel`. Never bind request input straight
   into an entity model (mass assignment); if you must reuse one, mark
   server-owned properties `#[Guarded]`.
7. **Only `#[Required]` makes a field mandatory.** Every other rule passes on
   `null`.
8. **Return a `Response` from every action** — use the controller helpers
   (`json()`, `created()`, `noContent()`, `notFound()`, `view()`,
   `redirect()`).
9. **JSON APIs live under `/api`.** Error rendering, CSRF exemption and rate
   limit responses all key off that prefix.

**Data**

10. **Always bind parameters.** SQL is a constant string with `:named`
    placeholders; values go in the params array. Never interpolate input.
11. **Model property names equal column names, exactly** (case-sensitive).
12. **Multi-statement writes run inside `$context->transaction()`**, in the
    service.

**Security**

13. **Escape every value you print in a template:** `<?= $this->e($value) ?>`.
    Templates are not auto-escaped.
14. **Keep the secure defaults.** Cookies are `Secure` + `HttpOnly` +
    `SameSite=Lax`; turn `Secure` off only in `config.dev.json` for plain-HTTP
    local work.
15. **Secrets come from the environment** — `"${DB_PASSWORD}"` placeholders in
    config, never literal values in committed JSON.
16. **Redirect to user-influenced targets only with `RedirectResponse::local()`.**
17. **State-changing MVC forms carry the CSRF token** (`<?= $csrfField ?>`)
    and the app runs `CsrfMiddleware`.

**Code style**

18. `declare(strict_types=1);` in every file; PHP 8.2+ features (readonly,
    constructor promotion, enums, `match`); PascalCase classes, camelCase
    methods.
19. Annotate iterable types (`array<string, mixed>`, `ProductModel[]`) so
    PHPStan stays clean.

---

## 2. Install and project layout

```bash
composer require melodicdev/framework

# or scaffold a new project (from a directory that has the framework installed)
vendor/bin/melodic make:project my-app                # MVC + API (default)
vendor/bin/melodic make:project my-api --type=api     # API only
vendor/bin/melodic make:project my-site --type=mvc    # MVC only

cd my-app && composer install
php -S localhost:8080 -t public
```

The canonical layout (what `make:project` generates, plus the directories you
add as the app grows):

```
my-app/
├── composer.json                # PSR-4: MyApp\ → src/
├── config/
│   ├── config.json              # base config (committed)
│   ├── config.qa.json           # per-environment overrides (committed)
│   ├── config.pd.json
│   └── config.dev.json          # local overrides — gitignored
├── public/
│   ├── index.php                # entry point
│   └── .htaccess                # rewrite everything to index.php
├── bin/console                  # your CLI (migrations, route:list, ...)
├── migrations/                  # *.sql, timestamp-prefixed
├── src/
│   ├── Controllers/             # ApiController / MvcController subclasses
│   ├── Services/                # Service subclasses — business logic
│   ├── DTO/                     # Model subclasses (entities + request DTOs)
│   ├── Data/{Entity}/Queries/   # QueryInterface implementations
│   ├── Data/{Entity}/Commands/  # CommandInterface implementations
│   ├── Middleware/              # MiddlewareInterface implementations
│   └── Providers/AppServiceProvider.php
├── views/                       # .phtml templates (full / mvc)
│   ├── layouts/main.phtml
│   └── home/index.phtml
├── storage/{cache,logs}/
└── tests/
```

The root namespace is the PascalCased project name (`my-app` → `MyApp`), not
necessarily `App`. Read `composer.json` before generating a class.

### Naming

| Kind | Location | Name | Example |
|---|---|---|---|
| Entity model | `src/DTO/` | `{Entity}Model` | `ProductModel` |
| Request DTO | `src/DTO/` | `Create{Entity}Request`, `Update{Entity}Request` | `CreateProductRequest` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery`, `GetAll{Plural}Query` | `GetProductByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create/Update/Delete{Entity}Command` | `CreateProductCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ProductService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ProductController` |
| Middleware | `src/Middleware/` | `{Purpose}Middleware` | `TenantMiddleware` |
| Provider | `src/Providers/` | `{Name}ServiceProvider` | `AppServiceProvider` |

`vendor/bin/melodic make:entity Product` generates the model, two queries,
three commands, the service and the controller in those locations (existing
files are skipped; no route is registered). `BlogPost`, `blog-post` and
`blog_post` all produce `BlogPost`. The
generated `Create`/`Update` commands contain `TODO` SQL and the controller
actions take no request DTO — treat the output as a skeleton and finish it the
way [section 16](#16-worked-example-a-complete-resource) shows.

---

## 3. Bootstrap

`public/index.php` builds the application and runs it. The order is always:
config → providers → middleware → routes → run.

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Cache\CacheServiceProvider;
use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Http\Middleware\SecurityHeadersMiddleware;
use Melodic\Log\LoggingServiceProvider;
use Melodic\Routing\Router;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\SecurityServiceProvider;
use MyApp\Controllers\HomeController;
use MyApp\Controllers\ProductController;
use MyApp\Providers\AppServiceProvider;

$app = new Application(dirname(__DIR__));
$app->loadEnvironmentConfig();                      // config/config.json → env → dev

// Providers: register() runs now, boot() runs when the app starts.
$app->register(new LoggingServiceProvider());
$app->register(new CacheServiceProvider($app->getBasePath() . '/storage/cache'));
$app->register(new SecurityServiceProvider());      // only if the app authenticates
$app->register(new AppServiceProvider());

// Global middleware — runs for every request, in this order.
$app->addMiddleware(new SecurityHeadersMiddleware());
$app->addMiddleware(new CorsMiddleware($app->config('cors', [])));
$app->addMiddleware(new JsonBodyParserMiddleware());

$app->routes(function (Router $router): void {
    $router->get('/', HomeController::class, 'index', name: 'home');

    $router->group('/api', function (Router $router): void {
        $router->apiResource('/products', ProductController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
});

$app->run();
```

What `run()` does: boots providers, captures the request, sends it through
an error-handling layer (always outermost, added for you) → your global
middleware → the router → route middleware → the controller action, then sends
the response. `HEAD` is answered by `GET` routes with the body suppressed.

`Application` API you will use:

| Method | Purpose |
|---|---|
| `loadEnvironmentConfig(string $configDir = 'config')` | Load layered config (preferred) |
| `loadConfig(string $path)` | Load/merge one JSON file |
| `config(?string $key = null, mixed $default = null)` | Read a value (dot notation); no key → the `Configuration` |
| `getEnvironment(): string` | `APP_ENV`, default `dev` |
| `getBasePath(): string` | Project root |
| `register(ServiceProvider $p)` | Register a provider |
| `services(callable $fn)` | Ad-hoc bindings: `fn(Container $c)` |
| `addMiddleware(MiddlewareInterface $m)` | Add global middleware |
| `routes(callable $fn)` | Define routes: `fn(Router $r)` |
| `getContainer()`, `getRouter()`, `getConfiguration()` | Access the pieces |
| `boot(): self` | Boot providers once (automatic) |
| `handle(Request $r): Response` | Run a request without sending output — tests, workers |
| `run(?Request $r = null): void` | Handle and send |

**Make the app testable:** build the `Application` in `bootstrap/app.php` and
`return $app;`. Then `public/index.php` is `(require __DIR__ . '/../bootstrap/app.php')->run();`
and tests call `->handle($request)` on the same object
([section 15](#15-testing)).

---

## 4. Configuration

JSON files, deep-merged in this order by `loadEnvironmentConfig()`:

```
config/config.json  →  config/config.{APP_ENV}.json  →  config/config.dev.json
     (base)                 (environment)                 (local, gitignored)
```

- `APP_ENV` (environment variable) picks the middle file. Unset or `dev` skips
  it. A missing environment or dev file is fine; a missing `config.json` throws.
- `app.environment` is set for you; read it with `$app->getEnvironment()`.
- **Maps merge, lists replace.** An override of `cors.allowedOrigins` replaces
  the whole list — repeat every entry the environment needs.
- Read values with dot notation: `$app->config('database.dsn')`,
  `$config->get('app.debug', false)`. A JSON key that itself contains a dot is
  not addressable — nest instead.
- **Environment placeholders** in any string value: `"${DB_PASSWORD}"` or
  `"${DB_HOST:-localhost}"`. An unset variable without a default leaves the
  text untouched; a default applies when the variable is unset **or empty**.
  The result is a string, with one exception: a value that is exactly one
  placeholder resolving to `true` / `false` becomes a real boolean
  (`"debug": "${APP_DEBUG:-false}"` works). Numbers stay strings — cast them
  where you read them. Names starting with `HTTP_` never resolve (under a web
  server those are request headers), so do not name a variable that way.
- `config.dev.json` is loaded in **every** environment when the file exists.
  Keep it gitignored and out of deployment artifacts.
- `vendor/bin/melodic make:config staging` creates `config/config.staging.json`.

Keys the framework itself reads:

| Key | Default | Used by |
|---|---|---|
| `app.debug` | `false` | Error pages/JSON include exception detail when true. **Never true in production.** |
| `app.errorsAsExceptions` | `false` | Turn PHP warnings/notices into exceptions (500 + log) |
| `views.path` | `views` | Default `ViewEngine` (relative to the project root, or absolute) |
| `logging.path` | `{basePath}/logs` | `LoggingServiceProvider` (give an absolute path) |
| `logging.level` | `debug` | Minimum level written |
| `logging.maxFiles` | `0` | Keep only the newest N daily log files (0 = all) |
| `session.cookieSecure` / `cookieSameSite` / `cookiePath` / `cookieDomain` | `true` / `Lax` / `/` / `""` | Session cookie |
| `csrf.exceptPaths` | `["/api"]` | `CsrfMiddleware` from `SecurityServiceProvider` |
| `auth.*` | see [section 11](#11-authentication-and-authorization) | `SecurityServiceProvider` |

Everything else (`database.*`, `cors.*`, your own keys) is application
convention: you read it and pass it to the class that needs it.

A reasonable base file:

```json
{
    "app": { "debug": false },
    "database": {
        "dsn": "${DB_DSN}",
        "username": "${DB_USER:-}",
        "password": "${DB_PASSWORD:-}"
    },
    "cors": {
        "allowedOrigins": ["https://app.example.com"],
        "allowedMethods": ["GET", "POST", "PUT", "PATCH", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "allowCredentials": false,
        "maxAge": 3600
    },
    "logging": { "level": "info", "maxFiles": 30 }
}
```

and a local `config/config.dev.json`:

```json
{
    "app": { "debug": true },
    "auth": { "cookieSecure": false, "refreshToken": { "cookieSecure": false } },
    "session": { "cookieSecure": false }
}
```

---

## 5. Dependency injection

`Melodic\DI\Container` auto-wires any class whose constructor parameters are
class-typed (or have defaults). You only register what auto-wiring cannot
guess: interfaces, classes that need scalars, and things that must be shared.

```php
namespace MyApp\Providers;

use Melodic\Core\Configuration;
use Melodic\Data\DbContext;
use Melodic\Data\DbContextInterface;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Error\ExceptionHandler;
use Melodic\Http\JsonResponse;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // One shared database connection, built lazily from config.
        $container->singleton(DbContextInterface::class, function (Container $c): DbContextInterface {
            $config = $c->get(Configuration::class);

            return new DbContext(
                (string) $config->get('database.dsn'),
                $config->get('database.username'),
                $config->get('database.password'),
            );
        });

        // Interface → implementation (new instance per resolution).
        $container->bind(MailerInterface::class, SmtpMailer::class);
    }

    public function boot(Container $container): void
    {
        // Runs once at startup, after every provider has registered.
        $container->get(ExceptionHandler::class)->registerMapper(
            InsufficientStockException::class,
            fn(\Throwable $e) => new JsonResponse(['error' => $e->getMessage()], 409),
        );
    }
}
```

| Method | Meaning |
|---|---|
| `bind($abstract, $concrete)` | New instance each `get()`. `$concrete` is a class name or `fn(Container $c)` |
| `singleton($abstract, $concrete)` | One shared instance. `singleton(Interface::class, Impl::class)` also makes a direct `Impl` type-hint share it |
| `instance($abstract, $object)` | Register an existing object |
| `get($id)` | Resolve (binding, else auto-wire) |
| `isBound($id)` | Explicitly registered? |
| `has($id)` | Resolvable — true for **any existing class**, so use `isBound()` to test for a registration |

Already registered by `Application`: `Configuration`, `Container`, `Router`,
`Application`, a default `ViewEngine`, and (at boot) `ExceptionHandler`.

Facts worth knowing:

- Controllers, services and route middleware are resolved from the container
  **per request** and are not shared unless you register them as singletons.
  Extending `Melodic\Service\Service` needs no registration: its
  `DbContextInterface` parameter is auto-wired from your binding.
- Resolution failures throw `ContainerException` (`NotFoundException` for an
  unknown id, `CircularDependencyException` for a cycle). Exceptions thrown by
  your own factories and constructors propagate unchanged.
- The container is PSR-11 compatible (`Melodic\DI\ContainerInterface` extends
  `Psr\Container\ContainerInterface`).
- A scalar constructor parameter without a default cannot be auto-wired —
  register a factory.

---

## 6. Routing

Routes map a method and path to a controller class and an action method name.

```php
$app->routes(function (Router $router): void {
    $router->get('/', HomeController::class, 'index', name: 'home');
    $router->get('/products/{id:\d+}', CatalogController::class, 'show', name: 'catalog.show');

    $router->group('/api', function (Router $router): void {
        $router->apiResource('/products', ProductController::class);

        $router->post('/products/{id:\d+}/archive', ProductController::class, 'archive',
            middleware: [AuthorizationMiddleware::class],
            attributes: ['entitlements' => ['catalog.admin']],
        );
    }, middleware: [ApiAuthenticationMiddleware::class]);
});
```

**Verbs:** `get`, `post`, `put`, `patch`, `delete` — all with the signature
`(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null)`.

**`apiResource($path, $controller, $middleware = [], $attributes = [])`**
registers six routes:

| Method | Path | Action |
|---|---|---|
| GET | `{path}` | `index` |
| GET | `{path}/{id}` | `show` |
| POST | `{path}` | `store` |
| PUT | `{path}/{id}` | `update` |
| PATCH | `{path}/{id}` | `update` |
| DELETE | `{path}/{id}` | `destroy` |

The PATCH route is a fallback: your own `patch()` route that matches the same
request takes precedence, whether it is registered before or after
`apiResource()`.

**`group($prefix, $callback, $middleware = [], $attributes = [])`** prefixes
paths and prepends middleware; groups nest. Group attributes merge into route
attributes and the route wins: maps merge key by key, while a list replaces
the group's list as a whole (a route's `'entitlements' => ['editor']` replaces
the group's `['admin', 'manager']`). An empty array or `null` never overrides
an array, so `'entitlements' => []` cannot switch a group's requirement off.

**Parameters**

- `{name}` matches one path segment. `{name:regex}` constrains it
  (`{id:\d+}`, `{slug:[a-z0-9-]+}`, `{year:\d{4}}`). A
  value that fails the constraint does not match the route at all (404),
  whereas an unconstrained `{id}` bound to `int $id` answers 400.
- Captured values are URL-decoded and bound to the action parameter **with the
  same name**, coerced to its declared type (`int`, `float`, `bool`, `string`,
  backed enum). An uncoercible value is a 400, not a 500. The decoded value
  must satisfy the constraint as well, so an encoded slash (`%2F`) never
  reaches a one-segment `{name}` — the route does not match. Use an explicit
  `{path:.+}` to accept slashes, and still validate anything used as a file
  path.
- A trailing slash matches the same route (`/products/` ≡ `/products`).
- Literal segments match literally (`/v1.0/` — the dot is not a wildcard).
- Routes are tried in registration order; register specific paths
  (`/products/featured`) before parameterised ones (`/products/{id}`) or
  constrain the parameter.

**Middleware on routes** is a list of **class names**, resolved from the
container, run after global middleware (group first, then route). Because it
is built by the container it cannot take per-route constructor arguments —
pass per-route data through `attributes` and read
`$request->getAttribute('route.attributes')` in the middleware.

**Named routes:** `$router->url('catalog.show', ['id' => 5])` → `/products/5`;
extra parameters become a query string. Inject `Router` where you need it.

**Unmatched requests:** unknown path → 404; known path, wrong method → 405
with an `Allow` header; HTTP method the framework does not model → 501.

`route:list` (see [section 14](#14-console-and-migrations)) prints the table.

---

## 7. Controllers

Extend `ApiController` for JSON endpoints, `MvcController` for HTML. Inject
services through the constructor. Each action returns a `Response`.

```php
namespace MyApp\Controllers;

use Melodic\Controller\ApiController;
use Melodic\Http\JsonResponse;
use Melodic\Http\Response;
use Melodic\Routing\FromQuery;
use MyApp\DTO\CreateProductRequest;
use MyApp\DTO\PageRequest;
use MyApp\DTO\UpdateProductRequest;
use MyApp\Services\ProductService;

class ProductController extends ApiController
{
    public function __construct(
        private readonly ProductService $products,
    ) {}

    public function index(#[FromQuery] PageRequest $page): JsonResponse
    {
        return $this->json($this->products->getPage($page->Page, $page->PerPage));
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->products->getById($id);

        return $product !== null ? $this->json($product) : $this->notFound();
    }

    public function store(CreateProductRequest $request): JsonResponse
    {
        $product = $this->products->create($request);

        return $this->created($product, "/api/products/{$product->Id}");
    }

    public function update(int $id, UpdateProductRequest $request): JsonResponse
    {
        $product = $this->products->update($id, $request);

        return $product !== null ? $this->json($product) : $this->notFound();
    }

    public function destroy(int $id): Response
    {
        return $this->products->delete($id) ? $this->noContent() : $this->notFound();
    }
}
```

### How action arguments are resolved

For each parameter of the action, in this order:

1. **Name matches a route parameter** → that value, coerced to the declared
   type (400 on bad input).
2. **Scalar/untyped with no matching route parameter** → its default value,
   or `null` if nullable; otherwise the request fails with a 500 — every
   scalar parameter must be a route parameter or have a default.
3. **`Model` subclass** → hydrated from the request body (or from the query
   string when the parameter has `#[FromQuery]`), then validated. Failure
   returns 400 before the action runs.
4. **Any other class** → resolved from the container (per-action service
   injection).

### Helpers

On every controller (`Melodic\Controller\Controller`):

| Helper | Result |
|---|---|
| `json(mixed $data, int $status = 200)` | JSON response. Models, `PaginatedResult` and arrays of them serialise themselves |
| `created(mixed $data, ?string $location = null)` | 201 (+ `Location`) |
| `noContent()` | 204 |
| `badRequest($data = null)`, `unauthorized()`, `forbidden()`, `notFound()` | 400 / 401 / 403 / 404 with `{"error": "..."}` unless you pass data |
| `getUserContext(): ?UserContextInterface` | The authenticated user; **`null` when no auth middleware ran** |
| `$this->request` | The current `Request` |

On `MvcController` additionally: `view(string $template, array $data = [])`,
`setLayout(string $layout)`, `viewBag()` / `$this->viewBag`,
`redirect(string $url, int $status = 302)`, `csrfToken(): ?string`.

### Request

`Request` is immutable; `withAttribute()` returns a new instance.

| Method | Returns |
|---|---|
| `method(): HttpMethod`, `path(): string`, `uri(): string`, `queryString(): string` | Request line |
| `query(?string $key = null, $default = null)` | Query parameter(s) |
| `body(?string $key = null, $default = null)` | Form field(s), or the decoded JSON body when `JsonBodyParserMiddleware` ran |
| `rawBody(): string` | Unparsed body |
| `header(string $name): ?string` (case-insensitive), `bearerToken(): ?string` | Headers |
| `cookie(string $name, $default = null)`, `file(?string $key = null)` | Cookies; `$_FILES`-shaped upload entries |
| `getAttribute(string $name, $default = null)`, `attributes(): array` | Values set by middleware/routing |
| `ip(): ?string` | `REMOTE_ADDR` only — forwarded headers are deliberately not trusted |
| `host(): string`, `scheme(): string`, `isSecure(): bool` | Client-supplied; do not build security-sensitive URLs from `host()` |
| `isJson(): bool`, `wantsJson(): bool` | `Content-Type` / `Accept` is JSON |
| `server(?string $key = null, $default = null)` | `$_SERVER` |

Attributes set by the framework: each route parameter by name, `route` (the
`Route`), `route.attributes`, `parsedBody`, `userContext`, `csrfToken`,
`refreshToken`, `refreshTokenUserId`.

### Response

`new Response(int $statusCode = 200, string $body = '', array $headers = [])`,
`new JsonResponse(mixed $data, int $statusCode = 200, array $headers = [])`,
`new RedirectResponse(string $url, int $statusCode = 302)`,
`RedirectResponse::local(string $path, int $statusCode = 302)` (throws on
anything but a same-origin path — use it for any target that came from outside
your code).

Responses are immutable-style: `withStatus()`, `withHeader()`,
`withoutHeader()`, `withBody()`, `withCookie()` return a modified copy —
**use the return value**. `withCookie($name, $value, $options)` defaults to
`secure: true, httponly: true, samesite: 'Lax', path: '/'`.

---

## 8. Models, binding and validation

`Melodic\Data\Model` is the base for every DTO: entity rows, request bodies,
query-string filters. Properties are public and typed.

```php
namespace MyApp\DTO;

use Melodic\Data\Model;
use Melodic\Validation\Rules\{Max, MaxLength, Min, Required};

class CreateProductRequest extends Model
{
    #[Required]
    #[MaxLength(100)]
    public string $Name;

    #[Required]
    #[Min(0)]
    public float $Price;

    public bool $IsActive = true;          // optional, defaults when omitted
}

class UpdateProductRequest extends Model   // partial update: everything nullable
{
    #[MaxLength(100)]
    public ?string $Name = null;

    #[Min(0)]
    public ?float $Price = null;

    public ?bool $IsActive = null;
}

class PageRequest extends Model            // bound with #[FromQuery]
{
    #[Min(1)]
    public int $Page = 1;

    #[Min(1)]
    #[Max(100)]
    public int $PerPage = 25;
}
```

### Property naming and JSON keys

- **Input:** an incoming key is matched to a property as-is, then with its
  first letter upper-cased, then fully upper-cased. So `name`, `Name` → `$Name`;
  `ein` → `$EIN`. Unknown keys are ignored.
- **Output:** `toArray()` / `json_encode($model)` lower-case the first letter
  (`$UserName` → `userName`); an all-caps name is fully lower-cased (`$EIN` →
  `ein`).
- **Database:** a column hydrates the property with the **identical** name.

Pick one convention and hold it. The framework's house style is PascalCase
columns and properties (`Id`, `CreatedAt`) with camelCase JSON, which the rules
above produce with no mapping code. If your schema is `snake_case`, name the
properties `snake_case` too (`$created_at`) and the JSON keys will be
`created_at`.

### Binding

When an action parameter is a `Model`, the framework calls
`Model::fromArray()` on the body (or query string):

- Scalars are coerced to the declared type: `"5"` → `int`, `"true"`/`1`/`"on"`
  → `bool`, numbers → `string`. Backed enums are built from their backing
  value; `DateTime`/`DateTimeImmutable` from a date string; a nested `Model`
  property from a nested object.
- A value that cannot be coerced (`"abc"` for an `int`, a string for an
  `array`, `null` for a non-nullable property, an unknown enum value) returns
  **400** with `{"<field>": ["Field '<field>' must be an integer."]}` (dotted
  path for nested models).
- A property missing from the input keeps its declared default; a nullable
  property without a default becomes `null`; a non-nullable property without a
  default stays uninitialised — which is why mandatory fields carry
  `#[Required]` (the validator reports it) and optional ones are nullable or
  defaulted.
- A plain `array` property receives the decoded array as-is. To get a list of
  typed, validated models, declare the element type:

  ```php
  #[Required]
  #[ListOf(OrderLineRequest::class)]      // Melodic\Data\ListOf
  public array $Lines = [];
  ```

- `#[Guarded]` (`Melodic\Data\Guarded`) properties are never set from input.

Useful `Model` methods:

| Method | Returns |
|---|---|
| `Model::fromArray(array $data): static` | Hydrate + coerce (throws `ModelBindingException`) |
| `toArray()` | Initialised properties, JSON-style keys, raw values |
| `jsonSerialize()` | `toArray()` with `DateTime` as ISO-8601 — what `json()` emits |
| `toPascalArray()` | Initialised properties keyed by **property name**, values made bindable (bool → int, enum → value, `DateTime` → `Y-m-d H:i:s`) — INSERT parameters |
| `toUpdateArray()` | Only the properties **present in the input** (including explicit `null`), same conversion — PATCH/partial UPDATE parameters |
| `wasProvided(string $property): bool` | Was this property in the input? |

### Validation

Runs automatically after binding. Failure returns **400** with every message,
keyed by **property name**:

```json
{ "Name": ["This field is required"], "Price": ["Must be at least 0"] }
```

Nested models and `#[ListOf]` lists are validated too, with dotted keys
(`Address.City`, `Lines.0.Quantity`).

Rules (`Melodic\Validation\Rules\*`), each with an optional custom `message`:

| Attribute | Passes when |
|---|---|
| `#[Required]` | not `null`, not blank string, not `[]` |
| `#[Email]` | valid email |
| `#[Url]` | absolute `http`/`https` URL |
| `#[Uuid]` | canonical UUID |
| `#[Date(format: 'Y-m-d')]` | string in exactly that format (use on `string` properties) |
| `#[MinLength(n)]`, `#[MaxLength(n)]` | string length (multibyte-aware) |
| `#[Min(n)]`, `#[Max(n)]` | numeric bounds |
| `#[Pattern('/regex/')]` | regex matches |
| `#[In([...])]` | **strictly** equal to one of the values — list values of the property's type |

All except `#[Required]` pass on `null`. Custom rule:

```php
use Attribute;
use Melodic\Validation\RuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Sku implements RuleInterface
{
    public function __construct(public readonly string $message = 'Must be a valid SKU') {}

    public function validate(mixed $value): bool
    {
        return $value === null || (is_string($value) && preg_match('/^[A-Z]{3}-\d{4}$/', $value) === 1);
    }
}
```

Rules that need the database or other fields (uniqueness, "end after start")
belong in the service. Throw a `ValidationException` to answer 400 with the
same field-keyed body as automatic validation:

```php
use Melodic\Validation\{ValidationException, ValidationResult};

throw new ValidationException(ValidationResult::failure(['Email' => ['Already registered']]));
```

or throw `BadRequestException` / a domain exception mapped in a provider's
`boot()` for errors that are not about one field.

To validate outside a controller, inject `Melodic\Validation\Validator` and
call `validate(object $dto): ValidationResult` (`->isValid`, `->errors`).

---

## 9. Data access: queries, commands, services

### DbContext

`DbContextInterface` is the only door to the database. Bind one `DbContext`
as a singleton ([section 5](#5-dependency-injection)).

| Method | Use |
|---|---|
| `query(string $class, string $sql, array $params = []): array` | Rows hydrated into `$class` |
| `queryFirst(string $class, string $sql, array $params = []): ?object` | First row or `null` |
| `scalar(string $sql, array $params = []): mixed` | First column of first row (`false` when no rows) |
| `command(string $sql, array $params = []): int` | INSERT/UPDATE/DELETE → affected rows |
| `lastInsertId(): int` | After an INSERT |
| `transaction(callable $fn): mixed` | `fn(DbContextInterface $db)`; commits on return, rolls back on any exception; nested calls use savepoints |
| `DbContext::cursor($class, $sql, $params): \Generator` | Stream rows (concrete class only — not on the interface) |

- Hydration sets each column on the same-named property (private and inherited
  properties included) **without calling the constructor**, casting to the
  declared type: `int`, `float`, `bool` (driver forms like `"f"`, `"0"`
  handled), `string`, backed enums, `DateTime`/`DateTimeImmutable`. Columns
  without a matching property are ignored. Use `\stdClass::class` for ad-hoc
  rows.
- Parameters are normalised before binding: `bool` → `0/1`, backed enum → its
  value, `DateTimeInterface` → `Y-m-d H:i:s`.
- PDO runs in exception mode. Every named placeholder needs a value and every
  value needs a placeholder.

### Query and command classes

The SQL is assembled once in the constructor; `execute()` runs it.

```php
namespace MyApp\Data\Product\Queries;

use Melodic\Data\DbContextInterface;
use Melodic\Data\QueryInterface;
use MyApp\DTO\ProductModel;

class GetProductByIdQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct(private readonly int $id)
    {
        $this->sql = 'SELECT Id, Name, Price, IsActive, CreatedAt FROM products WHERE Id = :id';
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): ?ProductModel
    {
        return $context->queryFirst(ProductModel::class, $this->sql, ['id' => $this->id]);
    }
}
```

```php
namespace MyApp\Data\Product\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;
use MyApp\DTO\CreateProductRequest;

class CreateProductCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(private readonly CreateProductRequest $request)
    {
        $this->sql = 'INSERT INTO products (Name, Price, IsActive, CreatedAt)
                      VALUES (:Name, :Price, :IsActive, :CreatedAt)';
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, [
            'Name' => $this->request->Name,
            'Price' => $this->request->Price,
            'IsActive' => $this->request->IsActive,
            'CreatedAt' => new \DateTimeImmutable(),
        ]);
    }
}
```

`QueryInterface::execute()` returns `mixed` — narrow it in your class
(`?ProductModel`, `array`, `PaginatedResult`). `CommandInterface::execute()`
returns the affected-row count.

**Partial updates** build the `SET` list from `toUpdateArray()`. Its keys are
real property names of the DTO class (never raw input keys), so they are safe
to use as column names:

```php
class UpdateProductCommand implements CommandInterface
{
    private readonly string $sql;

    /** @param array<string, mixed> $changes from UpdateProductRequest::toUpdateArray() */
    public function __construct(
        private readonly int $id,
        private readonly array $changes,
    ) {
        $assignments = array_map(fn(string $column) => "{$column} = :{$column}", array_keys($this->changes));
        $this->sql = 'UPDATE products SET ' . implode(', ', $assignments) . ' WHERE Id = :__id';
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, $this->changes + ['__id' => $this->id]);
    }
}
```

**Pagination** — `Melodic\Data\Paginator` inside a query. The SQL must have an
`ORDER BY` and no `LIMIT`/`OFFSET`. Its column names must be unique (the count
wraps it as a subquery, and MySQL rejects duplicate names there) — alias the
columns of a join instead of selecting `a.*, b.*`:

```php
public function execute(DbContextInterface $context): PaginatedResult
{
    return Paginator::paginate($context, ProductModel::class, $this->sql, [], $this->page, $this->perPage);
}
```

`PaginatedResult` exposes `items`, `total`, `page`, `perPage`, `pageCount()`,
`hasMore()` and serialises to `{items, total, page, perPage, pageCount}`.
`$perPage` is clamped to `$maxPerPage` (default 100).

### Services

Business logic and transaction boundaries. Extend `Melodic\Service\Service`:
it receives the `DbContextInterface` (`$this->context`) and an optional
read-only context (`getReadOnlyContext()` falls back to the main one).

```php
namespace MyApp\Services;

use Melodic\Data\DbContextInterface;
use Melodic\Data\PaginatedResult;
use Melodic\Service\Service;

class ProductService extends Service
{
    /** @return PaginatedResult<ProductModel> */
    public function getPage(int $page, int $perPage): PaginatedResult
    {
        return (new GetProductsPageQuery($page, $perPage))->execute($this->getReadOnlyContext());
    }

    public function getById(int $id): ?ProductModel
    {
        return (new GetProductByIdQuery($id))->execute($this->getReadOnlyContext());
    }

    public function create(CreateProductRequest $request): ProductModel
    {
        return $this->context->transaction(function (DbContextInterface $db) use ($request): ProductModel {
            (new CreateProductCommand($request))->execute($db);

            return (new GetProductByIdQuery($db->lastInsertId()))->execute($db);
        });
    }

    public function update(int $id, UpdateProductRequest $request): ?ProductModel
    {
        $changes = $request->toUpdateArray();

        if ($changes !== []) {
            (new UpdateProductCommand($id, $changes))->execute($this->context);
        }

        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        return (new DeleteProductCommand($id))->execute($this->context) > 0;
    }
}
```

A service that needs more collaborators declares its own constructor and calls
`parent::__construct($context)`. A service with no database work does not need
to extend `Service` at all. Auto-wiring passes the same `DbContextInterface`
binding for both contexts; to send reads to a replica, register the service
with a factory that supplies a second `DbContext`.

---

## 10. Views (MVC)

Templates are plain `.phtml` files under `views/`. `Application` binds a
`ViewEngine` for that directory (override with `views.path`), so
`MvcController` subclasses work with no extra registration.

```php
namespace MyApp\Controllers;

use Melodic\Controller\MvcController;
use Melodic\Http\Response;

class CatalogController extends MvcController
{
    public function __construct(
        \Melodic\View\ViewEngine $viewEngine,
        private readonly ProductService $products,
    ) {
        parent::__construct($viewEngine);
    }

    public function show(int $id): Response
    {
        $product = $this->products->getById($id);

        if ($product === null) {
            throw new \Melodic\Http\Exception\NotFoundException('Product not found');
        }

        $this->viewBag->title = $product->Name;
        $this->setLayout('layouts/main');

        return $this->view('catalog/show', ['product' => $product]);
    }
}
```

`views/layouts/main.phtml`:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $this->e($viewBag->title ?? 'My App') ?></title>
    <?= $this->renderSection('head') ?>
</head>
<body>
    <?= $this->partial('shared/nav') ?>
    <main><?= $this->renderBody() ?></main>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

`views/catalog/show.phtml`:

```php
<h1><?= $this->e($product->Name) ?></h1>
<p><?= $this->e(number_format($product->Price, 2)) ?></p>

<form method="post" action="/cart">
    <?= $csrfField ?? '' ?>
    <input type="hidden" name="productId" value="<?= $this->e((string) $product->Id) ?>">
    <button type="submit">Add to cart</button>
</form>

<?php $this->beginSection('scripts') ?>
<script src="/js/product.js"></script>
<?php $this->endSection() ?>
```

Inside a template, `$this` is the `ViewEngine`:

| Call | Purpose |
|---|---|
| `$this->e(?string $value)` | HTML-escape. **Required for every dynamic value** — output is not auto-escaped. Cast non-strings |
| `$this->renderBody()` | In a layout: the rendered view |
| `$this->beginSection('name')` … `$this->endSection()` | In a view: capture a block |
| `$this->renderSection('name')` | In a layout: output a block (empty string if undefined) |
| `$this->partial('path', $data = [])` | Render another template inline |

- Template names are paths relative to the views directory, without `.phtml`.
- Variables: every key of the `$data` array, plus `$viewBag` (the controller's
  `ViewBag`), plus `$csrfToken` / `$csrfField` when `CsrfMiddleware` is
  active. `$csrfField` is ready-made HTML — print it unescaped.
- `ViewEngine::renderCached($template, $data, $layout, $ttl)` caches output
  when a `CacheInterface` is bound.
- Throw `NotFoundException` (or any `HttpException`) from an MVC action to get
  the framework's HTML error page.

---

## 11. Authentication and authorization

Register `Melodic\Security\SecurityServiceProvider`. It reads the `auth`
config section and binds the validator, providers and middleware as
singletons. Tokens are JWTs: bearer tokens for APIs, an HttpOnly cookie for
browser sessions.

### Config

```json
{
    "auth": {
        "loginPath": "/auth/login",
        "callbackPath": "/auth/callback",
        "postLoginRedirect": "/",
        "cookieName": "melodic_auth",
        "cookieLifetime": 3600,
        "local": {
            "signingKey": "${AUTH_SIGNING_KEY}",
            "issuer": "my-app",
            "audience": "my-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "entra": {
                "type": "oidc",
                "label": "Sign in with Microsoft",
                "discoveryUrl": "https://login.microsoftonline.com/<tenant>/v2.0/.well-known/openid-configuration",
                "clientId": "${ENTRA_CLIENT_ID}",
                "clientSecret": "${ENTRA_CLIENT_SECRET}",
                "redirectUri": "https://app.example.com/auth/callback/entra",
                "claimMap": { "entitlements": "roles", "username": "preferred_username" }
            },
            "password": { "type": "local", "label": "Sign in" }
        }
    }
}
```

- Provider `type` is `oidc` (discovery + JWKS, PKCE, nonce; the id_token is
  the session token), `oauth2` (`authorizeUrl` / `tokenUrl` / `userInfoUrl`,
  PKCE; a local JWT is issued) or `local` (username/password form; a local JWT
  is issued).
- `oauth2` and `local` providers **require `auth.local`**, and an `HS*`
  signing key must be at least 32 characters (`openssl rand -base64 48`).
- A `local` provider needs your authenticator bound **before** the app
  resolves any auth middleware:

  ```php
  $container->bind(LocalAuthenticatorInterface::class, DatabaseAuthenticator::class);
  ```

  `authenticate(string $username, string $password): array` returns claims
  (`sub`, `username`, `email`, optional `entitlements`) or throws
  `SecurityException`.
- Other keys: `auth.api.enabled` / `auth.web.enabled` (default `true`; `false`
  makes the matching middleware pass everyone as anonymous),
  `auth.cookieSecure` / `cookieSameSite` / `cookiePath` / `cookieDomain`,
  `auth.oidcCacheDir`, `auth.providers.<name>.audience` / `scopes` /
  `signingAlg`, `auth.loginPage.*` (title, colours, logo), and
  `auth.refreshToken.*`.

### Middleware

| Class | Behaviour |
|---|---|
| `ApiAuthenticationMiddleware` | Requires `Authorization: Bearer <jwt>`. Missing/invalid → 401 JSON. Sets `userContext` |
| `WebAuthenticationMiddleware` | Requires a valid auth cookie; otherwise remembers the URL and redirects to the login page. Sets `userContext` |
| `OptionalWebAuthMiddleware` | Sets `userContext` from the cookie if valid, anonymous otherwise; never blocks |
| `AuthCallbackMiddleware` | Serves `GET {loginPath}`, `GET {loginPath}/{provider}`, `GET|POST {callbackPath}/{provider}` and `POST /auth/logout` |
| `AuthorizationMiddleware` | Requires an authenticated user (401) holding **any** of the route's `entitlements` (403). Entries are strings or backed enums; any other type throws |
| `CsrfMiddleware` | CSRF check for non-API state-changing requests |
| `RefreshTokenMiddleware` | Validates the refresh-token cookie on your refresh endpoint |

Apply authentication to route groups, not globally — a global
`ApiAuthenticationMiddleware` would reject your public pages, and a global
`WebAuthenticationMiddleware` would redirect the login page to itself:

```php
$c = $app->getContainer();

$app->addMiddleware(new JsonBodyParserMiddleware());
$app->addMiddleware($c->get(CsrfMiddleware::class));          // MVC apps
$app->addMiddleware($c->get(AuthCallbackMiddleware::class));  // browser login/logout

$app->routes(function (Router $router): void {
    $router->get('/', HomeController::class, 'index',
        middleware: [OptionalWebAuthMiddleware::class]);

    $router->group('/account', function (Router $router): void {
        $router->get('', AccountController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);

    $router->group('/api', function (Router $router): void {
        $router->apiResource('/products', ProductController::class);

        $router->group('/admin', function (Router $router): void {
            $router->apiResource('/users', UserAdminController::class);
        }, middleware: [AuthorizationMiddleware::class], attributes: ['entitlements' => ['admin']]);
    }, middleware: [ApiAuthenticationMiddleware::class]);
});
```

### In a controller

```php
$user = $this->getUserContext();          // null if no auth middleware ran

if ($user === null || !$user->isAuthenticated()) {
    return $this->unauthorized();
}

$user->getUser()->id;                     // also ->username, ->email, ->entitlements
$user->hasEntitlement('catalog.admin');
$user->hasAnyEntitlement('admin', 'editor');
$user->getClaim('tenant_id');
```

Prefer `AuthorizationMiddleware` + route `entitlements` for coarse rules and a
check in the service for row-level rules (ownership).

### Browser session facts

- **Logout is `POST /auth/logout` with a `csrf_token` field** (405 on other
  methods, 403 on a bad token). It clears the auth cookie, destroys the
  session, clears the refresh cookie and redirects to `/`.

  ```php
  <form method="post" action="/auth/logout">
      <?= $csrfField ?? '' ?>
      <button type="submit">Sign out</button>
  </form>
  ```

- After login the user returns to the page they asked for (local paths only),
  else `postLoginRedirect`.
- Cookies are `Secure` by default. Over plain HTTP the browser drops them and
  login appears to do nothing — set `auth.cookieSecure` and
  `session.cookieSecure` to `false` in `config.dev.json` only.
- Authentication failures show a generic `Authentication failed.` unless
  `app.debug` is on; the reason is logged.

### CSRF

`CsrfMiddleware` rejects any non-GET/HEAD/OPTIONS request that lacks the
session token (`csrf_token` field or `X-CSRF-Token` header) with 403. Paths
under `csrf.exceptPaths` (default `/api`) and requests with a bearer token are
exempt. The token is per-session, so several tabs and forms work.

### Refresh tokens

Opt-in rotation with reuse detection: implement and bind
`RefreshTokenRepositoryInterface`, issue with
`RefreshTokenService::create($userId)` +
`RefreshTokenCookieHelper::attach($response, $token, $config)`, and on your
refresh route (with `RefreshTokenMiddleware`) call
`validateAndRotate($rawToken)`. Config lives under `auth.refreshToken`
(`cookieName` default `melodic_refresh`, `cookiePath` default `/auth/refresh`,
`tokenLifetime` default 7 days). Full guide:
[docs/refresh-tokens.md](https://github.com/MelodicDevelopment/melodic-php/blob/main/docs/refresh-tokens.md).

Provider setup walkthroughs (Entra, Google, Okta, GitHub, Facebook, local):
[docs/authentication.md](https://github.com/MelodicDevelopment/melodic-php/blob/main/docs/authentication.md).

---

## 12. Middleware

```php
namespace MyApp\Middleware;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class RequestIdMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $id = $request->header('X-Request-Id') ?? bin2hex(random_bytes(8));

        $response = $handler->handle($request->withAttribute('requestId', $id));   // downstream

        return $response->withHeader('X-Request-Id', $id);                         // on the way out
    }
}
```

- Work before `$handler->handle()` sees the request; work after sees the
  response; returning without calling it short-circuits.
- `Request` and `Response` are immutable — pass on / return the new instances.
- **Global** (`$app->addMiddleware(new X(...))`): an instance you construct,
  runs for every request including 404s. **Route/group**
  (`middleware: [X::class]`): a class name the container builds, runs only
  for matched routes, after the global stack.
- Throw an `HttpException` to fail a request; the outer error handler renders
  it.
- When a request fails with an exception, code after `$handler->handle()` never
  runs. If a **global** middleware only adds response headers and they must be
  on error responses too, also implement
  `Melodic\Http\Middleware\ResponseDecoratorInterface` —
  `decorate(Request $request, Response $response): Response` — and call it from
  `process()`:

  ```php
  public function process(Request $request, RequestHandlerInterface $handler): Response
  {
      return $this->decorate($request, $handler->handle($request));
  }
  ```

Built in:

| Class | Constructor | Notes |
|---|---|---|
| `Http\Middleware\JsonBodyParserMiddleware` | — | Decodes `application/json` bodies into `$request->body()`; malformed JSON → 400. **Required for JSON APIs** |
| `Http\Middleware\CorsMiddleware` | `array $config` | Keys `allowedOrigins` (`*`, exact, or `https://*.example.com`), `allowedMethods`, `allowedHeaders`, `maxAge`, `allowCredentials`. Answers `OPTIONS` preflights itself |
| `Http\Middleware\SecurityHeadersMiddleware` | `array $config = []` | `nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy` by default; opt-in `hsts`, `contentSecurityPolicy`, `permissionsPolicy`. Never overwrites a header you set |
| `Http\Middleware\RateLimitMiddleware` | `CacheInterface $cache, int $maxAttempts = 60, int $windowSeconds = 60, array $paths = [], array $methods = [], ?callable $keyResolver = null` | Fixed window per client IP; 429 with `Retry-After`. Behind a proxy pass a `$keyResolver` |
| `Security\CsrfMiddleware` and the auth middleware | via the container | [Section 11](#11-authentication-and-authorization) |

Recommended global order: `SecurityHeadersMiddleware`, `CorsMiddleware`,
`RateLimitMiddleware`, `JsonBodyParserMiddleware`, `CsrfMiddleware`,
`AuthCallbackMiddleware`. Throttle login like this:

```php
$app->addMiddleware(new RateLimitMiddleware(
    $c->get(CacheInterface::class), 10, 60, paths: ['/auth/callback'], methods: ['POST'],
));
```

Each limiter counts on its own: two instances that differ in paths, methods,
limit or window never share a counter, even on the same cache.

---

## 13. Errors, logging, cache, sessions, events

### Errors

Throw; do not build error responses by hand in services.

| Throw | Status |
|---|---|
| `Melodic\Http\Exception\NotFoundException($msg)` | 404 |
| `Melodic\Http\Exception\BadRequestException($msg)` | 400 |
| `Melodic\Validation\ValidationException($result)` | 400 with `{field: [messages]}` |
| `new HttpException($status, $msg)`, `HttpException::forbidden($msg)` | any |
| `Melodic\Security\SecurityException` | 401 (message masked outside debug) |
| anything else | 500 (message masked outside debug) |

The `ExceptionHandler` answers JSON (`{"error": "..."}`) when the request's
`Accept` or `Content-Type` is `application/json` or the path is `/api` /
`/api/...`; otherwise an HTML error page. With `app.debug` on, the exception
class, file, line and trace are included. Server errors are logged with a
stack trace; client errors as one line.

Map your own exception types once, in a provider's `boot()`:

```php
$container->get(ExceptionHandler::class)->registerMapper(
    DomainRuleViolation::class,
    fn(\Throwable $e, Request $request) => new JsonResponse(['error' => $e->getMessage()], 422),
);
```

Mappers run first and the first registered one whose class matches (subclasses
included) wins, so map narrow domain types — a mapper for `\RuntimeException`
would also swallow every `HttpException`. The log entry uses the status of the
response the mapper returns.

An exception unwinds past your middleware, so a middleware's "after" code does
not run for error responses. Header-only middleware opt back in by also
implementing `ResponseDecoratorInterface` ([section 12](#12-middleware));
`CorsMiddleware` and `SecurityHeadersMiddleware` do, so error responses carry
CORS and security headers.

### Logging

`$app->register(new LoggingServiceProvider())`, then inject
`Melodic\Log\LoggerInterface`. Daily files `melodic-YYYY-MM-DD.log`.

```php
$this->logger->info('Order {id} shipped to {country}', ['id' => $order->Id, 'country' => $country]);
$this->logger->error('Payment failed', ['exception' => $e]);   // 'exception' adds the trace
```

Levels: `emergency`, `alert`, `critical`, `error`, `warning`, `notice`,
`info`, `debug`. Never log secrets, tokens or full request bodies. To use
Monolog or another PSR-3 logger:
`$container->singleton(LoggerInterface::class, fn() => new PsrLoggerAdapter($monolog));`.
Without a logger bound, the framework logs nothing.

### Cache

`$app->register(new CacheServiceProvider($dir))`, then inject
`Melodic\Cache\CacheInterface`: `get($key, $default = null)`,
`set($key, $value, ?int $ttl = null)`, `has()`, `delete()`, `clear()`. TTL is
seconds; `null` never expires. `FileCache` is the default, `ArrayCache` is for
tests, `Psr16CacheAdapter` wraps Redis/Memcached/etc. Cache in the service
layer, and invalidate in the same service that writes.

### Sessions

`$app->register(new SessionServiceProvider())`, then inject
`Melodic\Session\SessionInterface`: `get`, `set`, `has`, `remove`,
`regenerate()`, `destroy()`. Starts lazily on first use. Call `regenerate()`
after any privilege change. Never use `$_SESSION` directly. `ArraySession` is
the in-memory test double.

### Events

`$app->register(new EventServiceProvider())`, then inject
`Melodic\Event\EventDispatcherInterface`.

```php
final class ProductCreated extends \Melodic\Event\Event
{
    public function __construct(public readonly ProductModel $product) {}
}

// In a provider's boot():
$container->get(EventDispatcherInterface::class)->listen(
    ProductCreated::class,
    fn(ProductCreated $event) => $container->get(SearchIndexer::class)->index($event->product),
    priority: 10,                       // higher runs first
);

// In the service, after the write succeeds:
$this->events->dispatch(new ProductCreated($product));
```

Listeners are matched on the event's **exact class** (no parent or interface
matching) and run synchronously. Use events for side effects, not for core
control flow.

---

## 14. Console and migrations

`vendor/bin/melodic` (framework CLI): `make:project`, `make:entity`,
`make:config`, `claude:install`.

`bin/console` (your application's CLI) starts empty — register what you need:

```php
#!/usr/bin/env php
<?php
declare(strict_types=1);

use Melodic\Cache\CacheInterface;
use Melodic\Console\{CacheClearCommand, Console, MakeMigrationCommand, MigrateCommand};
use Melodic\Console\{MigrateRollbackCommand, MigrateStatusCommand, RouteListCommand};
use Melodic\Data\DbContextInterface;
use Melodic\Data\Migration\Migrator;

$app = require __DIR__ . '/../bootstrap/app.php';      // returns the Application
$c = $app->getContainer();

$migrations = $app->getBasePath() . '/migrations';
$migrator = new Migrator($c->get(DbContextInterface::class), $migrations);

$console = new Console();
$console->setName('MyApp');
$console->register(new RouteListCommand($app->getRouter()));           // route:list
$console->register(new CacheClearCommand($c->get(CacheInterface::class)));  // cache:clear
$console->register(new MigrateCommand($migrator));                     // migrate
$console->register(new MigrateRollbackCommand($migrator));             // migrate:rollback [--steps=N]
$console->register(new MigrateStatusCommand($migrator));               // migrate:status
$console->register(new MakeMigrationCommand($migrations));             // make:migration <name>

exit($console->run($argv));
```

**Migrations** are plain SQL files applied in file-name order and recorded in
`melodic_migrations`:

```sql
-- migrations/20261116093000_create_products.sql
CREATE TABLE products (
    Id INTEGER PRIMARY KEY AUTOINCREMENT,
    Name VARCHAR(100) NOT NULL,
    Price DECIMAL(10,2) NOT NULL,
    IsActive INTEGER NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL
);

-- @down
DROP TABLE products;
```

Statements are split on a `;` at the end of a line. Most databases auto-commit
DDL, so keep each migration small. A migration without a `-- @down` section,
or with an empty one, cannot be rolled back (`migrate:rollback` fails rather
than forget the migration). Never edit an applied migration — add a new one.

**Your own command:**

```php
use Melodic\Console\Command;

class PruneSessionsCommand extends Command
{
    public function __construct(private readonly SessionService $sessions)
    {
        parent::__construct('sessions:prune', 'Delete expired sessions');
    }

    public function execute(array $args): int
    {
        $this->writeln("Pruned {$this->sessions->prune()} session(s).");

        return 0;                       // non-zero = failure; $this->error() writes to STDERR
    }
}
```

---

## 15. Testing

`Application::handle()` runs a request through the full stack — middleware,
routing, binding, validation, error handling — and returns the `Response`
without sending anything. Build requests explicitly (a `null` constructor
argument falls back to the PHP superglobal):

```php
use Melodic\Http\Request;
use PHPUnit\Framework\TestCase;

final class ProductApiTest extends TestCase
{
    private \Melodic\Core\Application $app;

    protected function setUp(): void
    {
        $this->app = require __DIR__ . '/../bootstrap/app.php';
        // Swap infrastructure for test doubles:
        $this->app->getContainer()->singleton(
            \Melodic\Data\DbContextInterface::class,
            fn() => new \Melodic\Data\DbContext('sqlite::memory:'),
        );
    }

    private function request(string $method, string $uri, array $body = [], array $headers = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],                                  // parse the query string yourself if the test needs it
            body: $body,
            headers: $headers + ['Accept' => 'application/json'],
            cookies: [],
            files: [],
        );
    }

    public function testRejectsInvalidProduct(): void
    {
        $response = $this->app->handle($this->request('POST', '/api/products', ['Price' => -1]));

        self::assertSame(400, $response->getStatusCode());
        self::assertArrayHasKey('Name', json_decode($response->getBody(), true));
    }
}
```

- `Request` takes `query` separately from `REQUEST_URI` — pass
  `query: ['page' => '2']` for query-string tests.
- Unit-test services with a `DbContext('sqlite::memory:')` and your
  migrations, or a stub `DbContextInterface`. Query/command classes are
  trivially testable the same way.
- Test doubles shipped with the framework: `ArrayCache`, `ArraySession`,
  `NullLogger`.
- Authenticated requests: bind a stub middleware, or set
  `auth.api.enabled: false` in the test config to get an anonymous
  `userContext`, or mint a token with your `auth.local` key.

---

## 16. Worked example: a complete resource

Adding "products" to an app, in the order you should write it.

1. **Migration** — `php bin/console make:migration create_products`, write the
   SQL ([section 14](#14-console-and-migrations)), `php bin/console migrate`.
2. **Entity model** — `src/DTO/ProductModel.php`, properties named like the
   columns:

   ```php
   class ProductModel extends Model
   {
       public int $Id;
       public string $Name;
       public float $Price;
       public bool $IsActive;
       public \DateTimeImmutable $CreatedAt;
   }
   ```

3. **Request DTOs** — `CreateProductRequest`, `UpdateProductRequest`,
   `PageRequest` ([section 8](#8-models-binding-and-validation)).
4. **Queries and commands** — one class per statement under
   `src/Data/Product/` ([section 9](#9-data-access-queries-commands-services)):
   `GetProductByIdQuery`, `GetProductsPageQuery`, `CreateProductCommand`,
   `UpdateProductCommand`, `DeleteProductCommand`.
5. **Service** — `ProductService extends Service`; transactions live here.
6. **Controller** — `ProductController extends ApiController`
   ([section 7](#7-controllers)).
7. **Route** — `$router->apiResource('/products', ProductController::class);`
   inside the `/api` group.
8. **Test** — at least one happy path and one validation failure through
   `$app->handle()`.

Resulting behaviour, with no further code:

| Request | Response |
|---|---|
| `GET /api/products?page=2&perPage=10` | 200 `{"items":[…],"total":57,"page":2,"perPage":10,"pageCount":6}` |
| `GET /api/products/5` | 200 `{"id":5,"name":"…","price":9.5,"isActive":true,"createdAt":"2026-11-16T09:30:00+00:00"}` or 404 |
| `GET /api/products/abc` | 400 `{"id":["Route parameter 'id' must be an integer."]}` |
| `POST /api/products` `{"name":"Widget","price":"9.50"}` | 201 + `Location: /api/products/6` |
| `POST /api/products` `{"price":-1}` | 400 `{"Name":["This field is required"],"Price":["Must be at least 0"]}` |
| `PATCH /api/products/5` `{"price":12}` | 200 — only `Price` is updated |
| `DELETE /api/products/5` | 204, or 404 if it did not exist |
| `PUT /api/products` | 405 with `Allow: GET, POST, HEAD` |
| no/invalid bearer token (group uses `ApiAuthenticationMiddleware`) | 401 `{"error":"Authentication required."}` |

---

## 17. Common mistakes

| Mistake | What happens | Do this |
|---|---|---|
| `new AuthenticationMiddleware(...)` or `new JwtValidator($secret)` | Class/signature does not exist | Register `SecurityServiceProvider`; use `ApiAuthenticationMiddleware::class` / `WebAuthenticationMiddleware::class` from the container |
| Auth middleware added globally | Public pages 401 / login page redirect loop | Put it on route groups |
| `config.local.json` | Never loaded | `config/config.dev.json` |
| Expecting an environment list override to merge | Entries vanish | Lists replace — restate the full list |
| No `JsonBodyParserMiddleware` | `$request->body()` and model binding see nothing for JSON requests | Add it globally |
| Reading validation errors by JSON key (`name`) | Keys are property names (`Name`) | Read by property name |
| `#[MaxLength(50)]` to make a field mandatory | `null` passes | Add `#[Required]` |
| Non-nullable request property without `#[Required]` or a default | "must not be accessed before initialization" error when omitted | Make it nullable, give it a default, or mark it `#[Required]` |
| Action parameter name ≠ route parameter name (`{id}` vs `$productId`) | 500 "Cannot resolve argument" | Use the same name |
| `show(string $id)` then `(int) $id` | Garbage ids reach the service | `show(int $id)` — coerced, 400 on bad input |
| `$response->withHeader(...)` without using the result | Header silently missing | `$response = $response->withHeader(...)` |
| `<?= $product->Name ?>` in a template | XSS | `<?= $this->e($product->Name) ?>` |
| Logout as a link (`GET /auth/logout`) | 405 | POST form with `$csrfField` |
| Login "does nothing" on `http://localhost` | Secure cookies dropped | `cookieSecure: false` for `auth` and `session` in `config.dev.json` |
| POST form returns 403 after adding `CsrfMiddleware` | Missing token | `<?= $csrfField ?>` in the form, or `X-CSRF-Token` header |
| `AuthorizationMiddleware` with constructor entitlements on a route | Route middleware is container-built — the arguments never arrive | `attributes: ['entitlements' => [...]]` on the route/group |
| `$container->has(Foo::class)` to detect a binding | True for any class | `isBound()` |
| Model property `$createdAt` for column `CreatedAt` | Never hydrated (exact match) | Name the property like the column |
| Extra or missing keys in the params array | PDO "invalid parameter number" | One value per placeholder — build params explicitly |
| `LIMIT`/`OFFSET` in SQL passed to `Paginator` | SQL error | Leave them out; include `ORDER BY` |
| SQL built with string interpolation of input | SQL injection | `:named` placeholders |
| `$request->ip()` behind a load balancer | Proxy's address | Read your proxy's header in a `keyResolver` / middleware you control |
| `new RedirectResponse($request->query('next'))` | Open redirect | `RedirectResponse::local(...)` |
| `Utilities::kill()`, `SafeRedirect`, `OAuthClient` instance methods | Deprecated, removed in 5.0 | `RedirectResponse::isLocalPath()`; a debugger |
| Listener registered for a base event class | Never called | Listen on the concrete class |
| `"port": "${DB_PORT}"` used as an `int` | Placeholders yield strings (only whole-value `true`/`false` become booleans) | Cast where you read it |
| Header-adding middleware missing from error responses | Exceptions skip a middleware's "after" code | Implement `ResponseDecoratorInterface` |
| `{id:\d+}` and expecting a 400 for `/products/abc` | Constraint mismatch is a 404 | Fine for pages; drop the constraint if you want the 400 |
| `array` property expected to contain hydrated models | Raw arrays | `#[ListOf(X::class)]` on the property |
| Calling `$container->get()` inside a controller or service | Hidden dependencies | Constructor injection |

---

## 18. Quick API index

| Namespace | Classes you use |
|---|---|
| `Melodic\Core` | `Application`, `Configuration` |
| `Melodic\Http` | `Request`, `Response`, `JsonResponse`, `RedirectResponse`, `HttpMethod` |
| `Melodic\Http\Exception` | `HttpException`, `NotFoundException`, `BadRequestException`, `MethodNotAllowedException` |
| `Melodic\Http\Middleware` | `MiddlewareInterface`, `RequestHandlerInterface`, `ResponseDecoratorInterface`, `CorsMiddleware`, `JsonBodyParserMiddleware`, `SecurityHeadersMiddleware`, `RateLimitMiddleware` |
| `Melodic\Routing` | `Router`, `Route`, `FromQuery` |
| `Melodic\Controller` | `ApiController`, `MvcController` |
| `Melodic\DI` | `Container`, `ContainerInterface`, `ServiceProvider`, `ContainerException`, `NotFoundException` |
| `Melodic\Data` | `Model`, `Guarded`, `ListOf`, `DbContext`, `DbContextInterface`, `QueryInterface`, `CommandInterface`, `Paginator`, `PaginatedResult`, `ModelBindingException` |
| `Melodic\Data\Migration` | `Migrator` |
| `Melodic\Service` | `Service` |
| `Melodic\Validation` | `Validator`, `ValidationResult`, `ValidationException`, `RuleInterface` |
| `Melodic\Validation\Rules` | `Required`, `Email`, `Url`, `Uuid`, `Date`, `MinLength`, `MaxLength`, `Min`, `Max`, `Pattern`, `In` |
| `Melodic\View` | `ViewEngine`, `ViewBag` |
| `Melodic\Security` | `SecurityServiceProvider`, `ApiAuthenticationMiddleware`, `WebAuthenticationMiddleware`, `OptionalWebAuthMiddleware`, `AuthCallbackMiddleware`, `AuthorizationMiddleware`, `CsrfMiddleware`, `UserContextInterface`, `User`, `LocalAuthenticatorInterface`, `SecurityException`, `RefreshTokenService`, `RefreshTokenRepositoryInterface`, `RefreshTokenCookieHelper`, `RefreshTokenMiddleware` |
| `Melodic\Cache` | `CacheInterface`, `CacheServiceProvider`, `FileCache`, `ArrayCache`, `Psr16CacheAdapter` |
| `Melodic\Session` | `SessionInterface`, `SessionServiceProvider`, `NativeSession`, `ArraySession` |
| `Melodic\Log` | `LoggerInterface`, `LoggingServiceProvider`, `FileLogger`, `NullLogger`, `LogLevel`, `PsrLoggerAdapter` |
| `Melodic\Event` | `EventDispatcherInterface`, `EventServiceProvider`, `Event` |
| `Melodic\Error` | `ExceptionHandler` |
| `Melodic\Console` | `Console`, `Command`, `RouteListCommand`, `CacheClearCommand`, `MigrateCommand`, `MigrateRollbackCommand`, `MigrateStatusCommand`, `MakeMigrationCommand` |

**Dependencies:** `firebase/php-jwt`, `psr/container`; PHP 8.2+ with PDO.
Optional: `ext-mbstring` (character- rather than byte-accurate
`#[MinLength]` / `#[MaxLength]`), `psr/log` (for `PsrLoggerAdapter`),
`psr/simple-cache` (for `Psr16CacheAdapter`).

**Upgrading:** `CHANGELOG.md`, `MIGRATION-3.0.md` and `MIGRATION-4.0.md` sit
next to this file in the package. 4.1 is backwards compatible with 4.0.

**Topic guides** (in the repository; not shipped in the Composer package):
<https://github.com/MelodicDevelopment/melodic-php/tree/main/docs>
