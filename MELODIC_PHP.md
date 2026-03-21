# Melodic PHP Framework — Complete Reference

> **Version:** 1.7.3 · **PHP:** 8.2+ · **License:** MIT
> **Packagist:** `melodicdev/framework` · **Docs:** https://php.melodic.dev

---

## Table of Contents

1. [Core Architecture](#core-architecture)
2. [CQRS Architecture](#cqrs-architecture)
3. [Routing](#routing)
4. [Request Handling](#request-handling)
5. [Response](#response)
6. [Database & Persistence](#database--persistence)
7. [Models & Entities](#models--entities)
8. [Services](#services)
9. [Dependency Injection](#dependency-injection)
10. [Middleware](#middleware)
11. [Validation](#validation)
12. [Authentication & Authorization](#authentication--authorization)
13. [Events](#events)
14. [Caching](#caching)
15. [Sessions](#sessions)
16. [Logging](#logging)
17. [Error Handling](#error-handling)
18. [View Engine (MVC)](#view-engine-mvc)
19. [Console & CLI](#console--cli)
20. [Testing](#testing)
21. [Best Practices](#best-practices)
22. [Extension Points](#extension-points)

---

## Core Architecture

### Request Lifecycle

```
HTTP Request
  → Application::run()
    → Boot all ServiceProviders
    → Request::capture() (wraps PHP superglobals)
    → Pipeline (middleware chain)
      → ErrorHandlerMiddleware (wraps everything in try/catch)
      → CorsMiddleware
      → JsonBodyParserMiddleware
      → AuthenticationMiddleware (API or Web)
      → ... (user middleware)
      → RoutingMiddleware (final handler)
        → Router::match() → resolve controller via DI
        → Route-level middleware mini-pipeline
        → Resolve action arguments (route params, model binding, validation)
        → Controller action executes
    → Response::send()
```

### Entry Point

The entry point is `public/index.php`. A typical bootstrap:

```php
<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;

$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();

$app->register(new AppServiceProvider());

$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());

$app->routes(function ($router) {
    $router->apiResource('/api/users', UserController::class);
});

$app->run();
```

### Application Class

**Namespace:** `Melodic\Core\Application`

The `Application` class is the framework's bootstrap hub. It owns:
- A `Configuration` instance (JSON config with dot-notation access)
- A `Container` instance (DI container)
- A `Router` instance
- A list of `MiddlewareInterface` instances
- A list of `ServiceProvider` instances

**Key methods:**

| Method | Description |
|---|---|
| `__construct(string $basePath)` | Creates app with base path. Registers `Configuration`, `Container`, `Router`, and `Application` itself in the container. |
| `loadConfig(string $path)` | Load a single JSON config file. Paths without `/` prefix are relative to `$basePath`. |
| `loadEnvironmentConfig(string $configDir = 'config')` | Loads `config.json` → `config.{APP_ENV}.json` → `config.dev.json` (see [Configuration](#configuration)). |
| `getEnvironment()` | Returns current environment string (default `'dev'`). |
| `config(?string $key, mixed $default)` | Shortcut: `null` key returns the `Configuration` object; string key calls `Configuration::get()`. |
| `addMiddleware(MiddlewareInterface $middleware)` | Appends middleware to the global pipeline. |
| `services(callable $callback)` | Passes the `Container` to a closure for registering bindings. |
| `register(ServiceProvider $provider)` | Registers and immediately calls `$provider->register($container)`. |
| `routes(callable $callback)` | Passes the `Router` to a closure for defining routes. |
| `getContainer()` | Returns the DI container. |
| `getRouter()` | Returns the router. |
| `getConfiguration()` | Returns the configuration. |
| `getBasePath()` | Returns the base path. |
| `run(?Request $request)` | Boots providers, builds middleware pipeline, dispatches request, sends response. |

**`run()` in detail:**

1. Calls `boot()` on every registered `ServiceProvider`
2. Resolves a `LoggerInterface` from the container (falls back to `NullLogger`)
3. Creates an `ExceptionHandler` and registers it in the container
4. Captures the `Request` (from PHP superglobals via `Request::capture()`)
5. Wraps `RoutingMiddleware` as the final handler in a `Pipeline`
6. Prepends `ErrorHandlerMiddleware` (so it wraps the entire stack)
7. Appends all user-registered middleware
8. Calls `$pipeline->handle($request)` → `$response->send()`
9. If anything throws outside the pipeline (catastrophic failure), a last-resort handler renders a plain error

### Configuration

**Namespace:** `Melodic\Core\Configuration`

Configuration is a key-value store loaded from JSON files with dot-notation access and deep-merge semantics.

**Loading order** (via `loadEnvironmentConfig()`):

```
config/config.json           ← base (always loaded)
config/config.{APP_ENV}.json ← environment overrides (skipped for 'dev')
config/config.dev.json       ← local developer overrides (always gitignored)
```

- `APP_ENV` is read from `getenv('APP_ENV')`, defaults to `'dev'`
- When `APP_ENV` is `'dev'`, no environment file is loaded (just base + dev)
- After loading, `app.environment` is set to the detected environment value

**Methods:**

| Method | Description |
|---|---|
| `loadFile(string $path)` | Loads and deep-merges a JSON file. Throws `RuntimeException` if file not found or invalid JSON. |
| `get(string $key, mixed $default = null)` | Dot-notation access: `'database.dsn'` navigates `['database']['dsn']`. |
| `set(string $key, mixed $value)` | Dot-notation set: creates nested arrays as needed. |
| `has(string $key)` | Returns `true` if the key path exists. |
| `all()` | Returns the entire config array. |
| `merge(array $data)` | Deep-merges an array into the existing config. |

**Deep merge behavior:** Arrays are merged recursively. Scalar values in `$override` replace values in `$base`. This means environment files only need to specify the keys they want to override.

### Directory Structure

**Framework (`melodicdev/framework`):**

```
src/
├── Core/           Application, Configuration
├── Http/           Request, Response, JsonResponse, RedirectResponse
│   ├── Exception/  HttpException, BadRequestException, NotFoundException, MethodNotAllowedException
│   └── Middleware/ MiddlewareInterface, RequestHandlerInterface, Pipeline, CorsMiddleware,
│                    JsonBodyParserMiddleware, ErrorHandlerMiddleware
├── Routing/        Route, Router, RoutingMiddleware
├── Controller/     Controller (abstract), ApiController, MvcController
├── DI/             ContainerInterface, Container, ServiceProvider
├── Data/           DbContextInterface, DbContext, QueryInterface, CommandInterface, Model
├── Security/       JWT, OIDC, OAuth2, local auth, CSRF, refresh tokens, session manager, middleware
├── Service/        Service (base class)
├── Validation/     Validator, ValidationResult, ValidationException, Rules/*
├── View/           ViewEngine, ViewBag
├── Event/          Event, EventDispatcher, EventDispatcherInterface, EventServiceProvider
├── Cache/          CacheInterface, FileCache, ArrayCache, CacheServiceProvider
├── Session/        SessionInterface, NativeSession, ArraySession, SessionServiceProvider
├── Log/            LogLevel, LoggerInterface, FileLogger, NullLogger, LoggingServiceProvider
├── Error/          ExceptionHandler
├── Console/        Console, Command, CommandInterface, route:list, cache:clear, claude:install
│   └── Make/       MakeEntityCommand, MakeProjectCommand, MakeConfigCommand, Stub
├── Framework.php   Version constant (Framework::VERSION)
└── Utilities.php   Debug helper (kill)
```

**Application projects built with Melodic:**

```
my-app/
├── composer.json                  PSR-4: App\ → src/
├── config/
│   ├── config.json                Base config
│   ├── config.qa.json             QA overrides
│   ├── config.pd.json             Production overrides
│   └── config.dev.json            Local overrides (gitignored)
├── public/
│   ├── index.php                  Entry point
│   └── .htaccess                  Apache URL rewriting
├── bin/
│   └── console                    CLI entry point
├── src/
│   ├── Controllers/               ApiController / MvcController subclasses
│   ├── Services/                  Service subclasses
│   ├── DTO/                       Model subclasses (flat directory)
│   ├── Data/
│   │   └── {Entity}/
│   │       ├── Queries/           QueryInterface implementations
│   │       └── Commands/          CommandInterface implementations
│   ├── Middleware/                 Custom middleware
│   └── Providers/
│       └── AppServiceProvider.php
├── views/                         MVC templates (.phtml)
│   ├── layouts/
│   └── {page}/
├── storage/
│   ├── cache/
│   └── logs/
└── tests/
```

---

## CQRS Architecture

Melodic implements CQRS (Command Query Responsibility Segregation) without a mediator. Queries and Commands are plain PHP objects that hold their SQL and parameters. They are instantiated directly in services and executed against a `DbContext`.

### QueryInterface

**Namespace:** `Melodic\Data\QueryInterface`

```php
interface QueryInterface
{
    public function getSql(): string;
    public function execute(DbContextInterface $context): mixed;
}
```

- `getSql()` returns the SQL statement (useful for debugging/logging).
- `execute()` runs the query against the provided `DbContext` and returns the result — typically a model instance, array of models, or `null`.

**Example Query:**

```php
class GetUserByIdQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct(private readonly int $id)
    {
        $this->sql = "SELECT * FROM users WHERE id = :id";
    }

    public function getSql(): string { return $this->sql; }

    public function execute(DbContextInterface $context): ?UserModel
    {
        return $context->queryFirst(UserModel::class, $this->sql, ['id' => $this->id]);
    }
}
```

**Example "Get All" Query:**

```php
class GetAllUsersQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct()
    {
        $this->sql = "SELECT * FROM users";
    }

    public function getSql(): string { return $this->sql; }

    /**
     * @return array<UserModel>
     */
    public function execute(DbContextInterface $context): array
    {
        return $context->query(UserModel::class, $this->sql);
    }
}
```

### CommandInterface

**Namespace:** `Melodic\Data\CommandInterface`

```php
interface CommandInterface
{
    public function getSql(): string;
    public function execute(DbContextInterface $context): int;
}
```

- `execute()` returns the number of affected rows (from `PDOStatement::rowCount()`).

**Example Commands:**

```php
class CreateUserCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(private readonly UserModel $model)
    {
        $this->sql = "INSERT INTO users (Username, Email) VALUES (:Username, :Email)";
    }

    public function getSql(): string { return $this->sql; }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, $this->model->toPascalArray());
    }
}

class DeleteUserCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(private readonly int $id)
    {
        $this->sql = "DELETE FROM users WHERE id = :id";
    }

    public function getSql(): string { return $this->sql; }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, ['id' => $this->id]);
    }
}
```

### How Queries and Commands Are Dispatched

There is **no mediator, no command bus, no handler registry**. Services instantiate query/command objects directly and call `execute()`:

```php
class UserService extends Service
{
    public function getById(int $id): ?UserModel
    {
        return (new GetUserByIdQuery($id))->execute($this->context);
    }

    public function create(UserModel $model): int
    {
        (new CreateUserCommand($model))->execute($this->context);
        return $this->context->lastInsertId();
    }
}
```

### Read vs Write Separation

The `Service` base class accepts two optional `DbContextInterface` instances:

```php
class Service
{
    public function __construct(
        protected readonly DbContextInterface $context,
        protected readonly ?DbContextInterface $readOnlyContext = null,
    ) {}

    protected function getContext(): DbContextInterface { return $this->context; }
    protected function getReadOnlyContext(): DbContextInterface { return $this->readOnlyContext ?? $this->context; }
}
```

- `$context` is the primary (read-write) database connection
- `$readOnlyContext` is an optional read-only replica; falls back to `$context` if not provided
- Queries should use `$this->getReadOnlyContext()` (or `$this->context` as default)
- Commands should use `$this->context` (or `$this->getContext()`)

### Error Handling in CQRS

- Exceptions thrown in query/command `execute()` propagate through the service to the controller
- The `ErrorHandlerMiddleware` catches any uncaught `\Throwable` and converts it to an appropriate HTTP response
- For validation failures during model binding, the `RoutingMiddleware` returns a `400 JsonResponse` with error details before the controller action is called
- For transactional operations, use `DbContext::transaction()` which automatically rolls back on exception

---

## Routing

### Route Registration

**Namespace:** `Melodic\Routing\Router`

Routes are registered in the `routes()` callback on the `Application`:

```php
$app->routes(function (Router $router) {
    $router->get('/users', UserController::class, 'index');
    $router->post('/users', UserController::class, 'store');
    $router->get('/users/{id}', UserController::class, 'show');
    $router->put('/users/{id}', UserController::class, 'update');
    $router->delete('/users/{id}', UserController::class, 'destroy');
    $router->patch('/users/{id}', UserController::class, 'partialUpdate');
});
```

**Available HTTP method shortcuts:**

| Method | Registers |
|---|---|
| `get(path, controller, action, middleware)` | GET route |
| `post(path, controller, action, middleware)` | POST route |
| `put(path, controller, action, middleware)` | PUT route |
| `delete(path, controller, action, middleware)` | DELETE route |
| `patch(path, controller, action, middleware)` | PATCH route |

### RESTful API Resource

`apiResource()` registers all five CRUD routes at once:

```php
$router->apiResource('/api/users', UserController::class);
```

This registers:

| HTTP Method | Path | Action |
|---|---|---|
| GET | `/api/users` | `index` |
| GET | `/api/users/{id}` | `show` |
| POST | `/api/users` | `store` |
| PUT | `/api/users/{id}` | `update` |
| DELETE | `/api/users/{id}` | `destroy` |

### Route Parameters

Route parameters use `{name}` syntax and are captured as named groups:

```php
$router->get('/posts/{postId}/comments/{commentId}', CommentController::class, 'show');
```

The pattern `{id}` is converted to the regex `(?P<id>[^/]+)`. Parameters are passed to controller action methods by name:

```php
public function show(string $postId, string $commentId): JsonResponse
{
    // $postId and $commentId are populated from the URL
}
```

**Query parameters** are accessed via the `Request` object, not through route definitions.

### Route Groups

Groups apply a path prefix and/or middleware to a set of routes:

```php
$router->group('/api', function (Router $r) {
    $r->apiResource('/users', UserController::class);
    $r->apiResource('/posts', PostController::class);
}, middleware: [AuthorizationMiddleware::class]);
```

Groups can be nested. Prefixes and middleware accumulate from outer to inner groups.

### Route Matching

`Router::match(HttpMethod $method, string $path)` iterates all registered routes in order and returns the first match:

```php
$result = $router->match($request->method(), $request->path());
// Returns: ['route' => Route, 'params' => ['id' => '42']] or null
```

The `Route::matches()` method converts `{param}` placeholders to named regex groups and tests against the path with full-string anchoring (`^...$`).

### Route-Level Middleware

Middleware can be attached per-route or per-group. These are class names (strings) resolved through the DI container:

```php
$router->get('/admin', AdminController::class, 'dashboard', middleware: [
    AuthorizationMiddleware::class,
]);
```

Route-level middleware runs in a mini-pipeline after the global middleware pipeline resolves the route.

---

## Request Handling

### Request Class

**Namespace:** `Melodic\Http\Request`

The `Request` class is immutable — `withAttribute()` returns a new instance.

**Construction:**

```php
// Automatic capture from PHP superglobals
$request = Request::capture();

// Manual construction (for testing)
$request = new Request(
    server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users'],
    query: ['page' => '2'],
    body: ['name' => 'Alice'],
    headers: ['Content-Type' => 'application/json'],
    attributes: [],
    rawBody: '{"name":"Alice"}',
    cookies: [],
);
```

**Key methods:**

| Method | Returns | Description |
|---|---|---|
| `method()` | `HttpMethod` | The HTTP method enum (GET, POST, PUT, DELETE, PATCH, OPTIONS) |
| `path()` | `string` | The URL path (parsed from `REQUEST_URI`) |
| `query(?string $key, mixed $default)` | `mixed` | Query string parameters; `null` key returns all |
| `body(?string $key, mixed $default)` | `mixed` | Body parameters (form data or parsed JSON); `null` key returns all |
| `header(string $name)` | `?string` | Case-insensitive header lookup |
| `bearerToken()` | `?string` | Extracts token from `Authorization: Bearer <token>` header |
| `getAttribute(string $name, mixed $default)` | `mixed` | Request attributes (set by middleware) |
| `withAttribute(string $name, mixed $value)` | `Request` | Returns new request with added attribute (immutable) |
| `cookie(string $name, mixed $default)` | `mixed` | Cookie value by name |
| `rawBody()` | `string` | Raw request body string |

**Body resolution:** The `body()` method first checks `$_POST` params. If empty, it falls back to the `parsedBody` attribute set by `JsonBodyParserMiddleware`. This means JSON request bodies are transparently available.

### HttpMethod Enum

**Namespace:** `Melodic\Http\HttpMethod`

```php
enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case OPTIONS = 'OPTIONS';

    public static function parse(string $method): self; // throws ValueError if invalid
}
```

### Automatic Model Binding

When a controller action parameter is typed as a `Model` subclass, the `RoutingMiddleware` automatically:

1. Hydrates the model from `$request->body()` via `Model::fromArray()`
2. Validates it using the `Validator`
3. If validation fails, returns a `400 JsonResponse` with error details — the controller is never called
4. If validation passes, injects the populated model as the argument

```php
// Model with validation attributes
class CreateUserRequest extends Model
{
    #[Required]
    #[MaxLength(50)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;
}

// Controller — $request is hydrated and validated automatically
public function store(CreateUserRequest $request): JsonResponse
{
    // $request->username and $request->email are populated and validated
}
```

**Parameter resolution order in `RoutingMiddleware`:**

1. Route parameters matched by name (`{id}` → `$id`)
2. Parameters typed as `Model` subclasses → hydrated from request body
3. Parameters with default values → use the default
4. Unresolvable parameters are skipped

### File Uploads

File uploads are not handled by a dedicated framework abstraction. Access PHP's `$_FILES` superglobal directly or read the raw body.

---

## Response

### Response Class

**Namespace:** `Melodic\Http\Response`

Responses are mutable-by-clone — `with*()` methods return new instances:

```php
$response = new Response(statusCode: 200, body: 'Hello', headers: ['Content-Type' => 'text/plain']);
$response = $response->withStatus(201)->withHeader('X-Custom', 'value')->withBody('Created');
```

**Methods:**

| Method | Returns | Description |
|---|---|---|
| `withStatus(int $code)` | `static` | Clone with new status code |
| `withHeader(string $name, string $value)` | `static` | Clone with added/replaced header |
| `withBody(string $body)` | `static` | Clone with new body |
| `withCookie(string $name, string $value, array $options)` | `static` | Clone with set-cookie |
| `getStatusCode()` | `int` | Current status code |
| `getHeaders()` | `array` | All headers |
| `getBody()` | `string` | Response body |
| `send()` | `void` | Sends status code, headers, cookies, and body to the client |

**Cookie options:** `expires`, `path` (default `/`), `domain`, `secure`, `httponly` (default `true`), `samesite` (default `Lax`).

### JsonResponse

**Namespace:** `Melodic\Http\JsonResponse`

Extends `Response`. Automatically sets `Content-Type: application/json` and JSON-encodes the data:

```php
new JsonResponse($data, statusCode: 200, headers: []);
```

Uses `JSON_THROW_ON_ERROR` — throws `\JsonException` if encoding fails.

### RedirectResponse

**Namespace:** `Melodic\Http\RedirectResponse`

Extends `Response`. Sets the `Location` header:

```php
new RedirectResponse('/dashboard', statusCode: 302);
```

### Controller Response Helpers

The abstract `Controller` base class provides convenience methods:

| Method | Status | Description |
|---|---|---|
| `json(mixed $data, int $statusCode = 200)` | 200 | JSON response |
| `created(mixed $data, ?string $location = null)` | 201 | JSON response with optional Location header |
| `noContent()` | 204 | Empty response |
| `notFound(mixed $data = null)` | 404 | JSON error: `{"error": "Not Found"}` |
| `badRequest(mixed $data = null)` | 400 | JSON error: `{"error": "Bad Request"}` |
| `unauthorized(mixed $data = null)` | 401 | JSON error: `{"error": "Unauthorized"}` |
| `forbidden(mixed $data = null)` | 403 | JSON error: `{"error": "Forbidden"}` |

### Error Response Structure

Error responses follow a consistent pattern:

```json
{"error": "Human-readable message"}
```

In debug mode, server errors (5xx) include additional diagnostic fields:

```json
{
    "error": "Something went wrong",
    "exception": "RuntimeException",
    "file": "/app/src/Services/UserService.php",
    "line": 42,
    "trace": ["#0 ...", "#1 ..."]
}
```

For validation errors (from model binding):

```json
{
    "username": ["This field is required"],
    "email": ["Must be a valid email address"]
}
```

---

## Database & Persistence

### DbContext

**Namespace:** `Melodic\Data\DbContext` (implements `DbContextInterface`)

A thin PDO wrapper with model hydration support.

**Construction:**

```php
// From DSN string
$db = new DbContext('mysql:host=localhost;dbname=myapp', 'user', 'pass', []);

// From existing PDO
$db = new DbContext($pdo);
```

When constructing from a DSN, it sets `ERRMODE_EXCEPTION` and `FETCH_ASSOC`.

**Methods:**

| Method | Returns | Description |
|---|---|---|
| `query(string $class, string $sql, array $params = [])` | `array<T>` | Executes SQL and returns an array of hydrated model instances |
| `queryFirst(string $class, string $sql, array $params = [])` | `?T` | Executes SQL and returns the first result, or `null` |
| `command(string $sql, array $params = [])` | `int` | Executes a write SQL and returns affected row count |
| `scalar(string $sql, array $params = [])` | `mixed` | Returns the first column of the first row |
| `transaction(callable $callback)` | `mixed` | Wraps callback in `beginTransaction()`/`commit()` with auto-rollback |
| `lastInsertId()` | `int` | Returns the last inserted ID as an integer |

**Model hydration:**

- Uses `ReflectionClass::newInstanceWithoutConstructor()` to create the model
- Maps database columns to public properties by name
- Casts values based on the property's type hint: `int`, `float`, `bool`, `string`
- For `stdClass`, returns `(object) $row`

**Transaction handling:**

```php
$result = $db->transaction(function (DbContext $ctx) {
    $ctx->command("INSERT INTO ...", [...]);
    $ctx->command("UPDATE ...", [...]);
    return $ctx->lastInsertId();
});
// Commits on success, rolls back on any Throwable
```

### DbContextInterface

```php
interface DbContextInterface
{
    /** @return T[] */
    public function query(string $class, string $sql, array $params = []): array;

    /** @return T|null */
    public function queryFirst(string $class, string $sql, array $params = []): ?object;

    public function command(string $sql, array $params = []): int;

    public function scalar(string $sql, array $params = []): mixed;

    public function transaction(callable $callback): mixed;

    public function lastInsertId(): int;
}
```

### Registering the Database Connection

In your `ServiceProvider` or bootstrap:

```php
$container->singleton(DbContextInterface::class, function () use ($config) {
    return new DbContext(
        $config->get('database.dsn'),
        $config->get('database.username'),
        $config->get('database.password'),
    );
});
```

### Migrations

The framework does not include a migration system. Use external tools or raw SQL scripts.

---

## Models & Entities

### Model Base Class

**Namespace:** `Melodic\Data\Model` (implements `JsonSerializable`)

Models are DTOs (Data Transfer Objects) with public properties. They extend `Model` for hydration, serialization, and validation support.

```php
class UserModel extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public ?string $phone = null;
    public bool $isActive;
}
```

**Key methods:**

| Method | Returns | Description |
|---|---|---|
| `static fromArray(array $data)` | `static` | Hydrates a new instance from an associative array |
| `toArray()` | `array` | Returns all initialized public properties with **camelCase** keys |
| `toPascalArray()` | `array` | Returns all initialized properties with **PascalCase** keys; booleans → int |
| `toUpdateArray()` | `array` | Returns only non-null properties with **PascalCase** keys; booleans → int |
| `jsonSerialize()` | `mixed` | Delegates to `toArray()` for `json_encode()` |

**`fromArray()` property matching:**
1. Tries the key as-is (PascalCase columns from DB)
2. Tries `ucfirst($key)` (camelCase keys from frontend JSON)
3. After hydration, initializes any remaining **nullable** public properties to `null` (prevents uninitialized property errors)

**`toPascalArray()` vs `toUpdateArray()`:**
- `toPascalArray()` is for **INSERT** — includes all initialized properties (including null values)
- `toUpdateArray()` is for **UPDATE** — only includes non-null properties (null means "not provided")
- Both convert `bool` → `int` for PDO compatibility

**Typical usage in commands:**

```php
// INSERT — use toPascalArray()
$this->sql = "INSERT INTO users (Username, Email) VALUES (:Username, :Email)";
$context->command($this->sql, $model->toPascalArray());

// UPDATE — use toUpdateArray() for partial updates
$context->command($this->sql, $model->toUpdateArray());
```

### Relationships

Models are flat DTOs. There is no ORM or relationship mapping. To load related data, write separate queries and compose results in the service layer.

### Validation at the Model Layer

Validation is applied via PHP 8 attributes on model properties. See the [Validation](#validation) section for all available rules.

```php
class CreateUserRequest extends Model
{
    #[Required]
    #[MinLength(3)]
    #[MaxLength(50)]
    public string $username;

    #[Required]
    #[Email]
    public string $email;

    #[Min(0)]
    #[Max(150)]
    public ?int $age = null;
}
```

---

## Services

### Service Base Class

**Namespace:** `Melodic\Service\Service`

The base class holds the database context(s):

```php
class Service
{
    public function __construct(
        protected readonly DbContextInterface $context,
        protected readonly ?DbContextInterface $readOnlyContext = null,
    ) {}

    protected function getContext(): DbContextInterface { return $this->context; }
    protected function getReadOnlyContext(): DbContextInterface { return $this->readOnlyContext ?? $this->context; }
}
```

### Writing a Service

Services are the business logic layer between controllers and data access (queries/commands):

```php
class UserService extends Service
{
    /** @return array<UserModel> */
    public function getAll(): array
    {
        return (new GetAllUsersQuery())->execute($this->context);
    }

    public function getById(int $id): ?UserModel
    {
        return (new GetUserByIdQuery($id))->execute($this->context);
    }

    public function create(UserModel $model): int
    {
        (new CreateUserCommand($model))->execute($this->context);
        return $this->context->lastInsertId();
    }

    public function update(int $id, UserModel $model): int
    {
        return (new UpdateUserCommand($id, $model))->execute($this->context);
    }

    public function delete(int $id): int
    {
        return (new DeleteUserCommand($id))->execute($this->context);
    }
}
```

### Registering Services

Services are resolved via the DI container. If a service's constructor dependencies can be auto-wired, no explicit binding is needed. Otherwise, register in a `ServiceProvider`:

```php
$container->singleton(DbContextInterface::class, fn() => new DbContext($pdo));
// UserService auto-wires: constructor takes DbContextInterface
```

---

## Dependency Injection

### Container

**Namespace:** `Melodic\DI\Container` (implements `ContainerInterface`)

The container supports:
- **Auto-wiring** — resolves constructor dependencies by type hint
- **Interface binding** — maps an interface to a concrete class
- **Singleton registration** — only instantiated once
- **Instance registration** — pre-built objects
- **Circular dependency detection** — throws `RuntimeException` with the full chain

**Methods:**

| Method | Description |
|---|---|
| `get(string $id)` | Resolves a class or binding. Auto-wires if no explicit binding exists. |
| `has(string $id)` | Checks if a binding, instance, or class exists. |
| `bind(string $abstract, string\|callable $concrete)` | Registers a transient binding (new instance each time). |
| `singleton(string $abstract, string\|callable $concrete)` | Registers a singleton (cached after first resolution). |
| `instance(string $abstract, object $instance)` | Registers a pre-built instance directly. |

**Resolution order:**

1. Check `$instances` (pre-registered or cached singletons)
2. Check `$bindings` → build via callable or class name
3. Fall back to auto-wiring via reflection

**Auto-wiring rules:**

- Only concrete, instantiable classes can be auto-wired
- Constructor parameters with class/interface type hints are recursively resolved
- Parameters with default values use the default if resolution fails
- Builtin types without defaults throw `RuntimeException`
- Circular dependencies are detected and reported with the full dependency chain

**Callable bindings** receive the container as an argument:

```php
$container->singleton(DbContextInterface::class, function (Container $c) {
    $config = $c->get(Configuration::class);
    return new DbContext($config->get('database.dsn'));
});
```

### ContainerInterface

```php
interface ContainerInterface
{
    public function get(string $id): mixed;
    public function has(string $id): bool;
    public function bind(string $abstract, string|callable $concrete): void;
    public function singleton(string $abstract, string|callable $concrete): void;
}
```

### ServiceProvider

**Namespace:** `Melodic\DI\ServiceProvider`

Abstract base class for modular service registration:

```php
abstract class ServiceProvider
{
    abstract public function register(Container $container): void;
    public function boot(Container $container): void {} // optional post-registration hook
}
```

- `register()` is called immediately when `$app->register($provider)` is invoked
- `boot()` is called during `$app->run()`, after all providers have been registered
- Use `register()` for binding definitions; use `boot()` for logic that depends on other services being registered

**Built-in service providers:**
- `EventServiceProvider` — registers `EventDispatcherInterface` → `EventDispatcher`
- `SessionServiceProvider` — registers `SessionInterface` → `NativeSession`
- `CacheServiceProvider` — registers `CacheInterface` → `FileCache`
- `LoggingServiceProvider` — registers `LoggerInterface` → `FileLogger`
- `SecurityServiceProvider` — registers all auth-related services

---

## Middleware

### MiddlewareInterface

**Namespace:** `Melodic\Http\Middleware\MiddlewareInterface`

```php
interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response;
}
```

### RequestHandlerInterface

```php
interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
```

### Pipeline

**Namespace:** `Melodic\Http\Middleware\Pipeline`

Chains middleware into a handler stack. Middleware are called in the order they were piped:

```php
$pipeline = new Pipeline($finalHandler);
$pipeline->pipe($middleware1);  // called first
$pipeline->pipe($middleware2);  // called second
$response = $pipeline->handle($request);
```

Internally, `buildHandler()` recursively wraps each middleware around the next, with the fallback handler at the end.

### Global vs Route Middleware

**Global middleware** — added via `$app->addMiddleware()`. Runs on every request.

**Route middleware** — specified as class name strings on routes or groups. Resolved via DI container and run in a mini-pipeline after routing matches.

### Execution Order

1. `ErrorHandlerMiddleware` (always first — wraps everything)
2. User-registered global middleware (in order of `addMiddleware()` calls)
3. `RoutingMiddleware` (resolves the route, acts as the final handler)
4. Route-level middleware (from group and route definitions)
5. Controller action

### Short-Circuiting

Any middleware can return a `Response` directly without calling `$handler->handle($request)`:

```php
public function process(Request $request, RequestHandlerInterface $handler): Response
{
    if (!$this->isAuthorized($request)) {
        return new JsonResponse(['error' => 'Forbidden'], 403);
    }
    return $handler->handle($request);
}
```

### Built-in Middleware

#### ErrorHandlerMiddleware

Wraps the entire pipeline in a try/catch. Delegates to `ExceptionHandler` for error formatting.

```php
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(LoggerInterface $logger, bool $debug = false) {}
}
```

#### CorsMiddleware

Handles CORS headers and preflight OPTIONS requests:

```php
$corsConfig = [
    'allowedOrigins' => ['*'],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    'allowedHeaders' => ['Content-Type', 'Authorization'],
    'maxAge' => 86400,
];
$app->addMiddleware(new CorsMiddleware($corsConfig));
```

OPTIONS requests receive a `204` response immediately without proceeding through the rest of the pipeline.

#### JsonBodyParserMiddleware

Parses JSON request bodies and stores the result in the `parsedBody` request attribute:

```php
$app->addMiddleware(new JsonBodyParserMiddleware());
```

- Only parses if `Content-Type` contains `application/json`
- Throws `BadRequestException` for invalid JSON
- The parsed body is accessible via `$request->body()` (transparent fallback)

---

## Validation

### Validator

**Namespace:** `Melodic\Validation\Validator`

Validates objects or arrays against PHP 8 attribute rules on public properties:

```php
$validator = new Validator();

// Validate an object
$result = $validator->validate($model);

// Validate raw array against a DTO class
$result = $validator->validateArray($data, CreateUserRequest::class);
```

### ValidationResult

```php
class ValidationResult
{
    public readonly bool $isValid;
    /** @var array<string, string[]> */
    public readonly array $errors;

    public static function success(): self;
    public static function failure(array $errors): self;
}
```

### ValidationException

```php
class ValidationException extends \RuntimeException
{
    public readonly ValidationResult $result;
}
```

### Available Validation Rules

All rules are PHP 8 attributes in `Melodic\Validation\Rules\`:

| Rule | Parameters | Message | Behavior |
|---|---|---|---|
| `#[Required]` | `?string $message` | "This field is required" | Fails on `null`, empty string, or whitespace-only string |
| `#[Email]` | `?string $message` | "Must be a valid email address" | Uses `FILTER_VALIDATE_EMAIL` |
| `#[MaxLength(int $max)]` | `int $max, ?string $message` | "Must be no more than {max} characters" | `mb_strlen()` check; fails on non-string |
| `#[MinLength(int $min)]` | `int $min, ?string $message` | "Must be at least {min} characters" | `mb_strlen()` check; fails on non-string |
| `#[Max(int\|float $max)]` | `int\|float $max, ?string $message` | "Must be no more than {max}" | Numeric comparison; fails on non-numeric |
| `#[Min(int\|float $min)]` | `int\|float $min, ?string $message` | "Must be at least {min}" | Numeric comparison; fails on non-numeric |
| `#[In(array $values)]` | `array $values, ?string $message` | "Must be one of: {list}" | Strict `in_array()` check |
| `#[Pattern(string $regex)]` | `string $regex, ?string $message` | "Must match the pattern {regex}" | `preg_match()` check; fails on non-string |

All rules have a `$message` property (customizable via constructor) and a `validate(mixed $value): bool` method.

### Automatic Validation (Model Binding)

When controller action parameters are typed as `Model` subclasses, the `RoutingMiddleware` automatically validates them. If validation fails, a `400 JsonResponse` is returned:

```json
{
    "username": ["This field is required", "Must be at least 3 characters"],
    "email": ["Must be a valid email address"]
}
```

### Manual Validation

```php
$validator = $container->get(Validator::class);
$result = $validator->validate($model);

if (!$result->isValid) {
    return $this->badRequest($result->errors);
}
```

---

## Authentication & Authorization

The security system supports three authentication strategies: **OIDC**, **OAuth2**, and **local** (username/password with JWT). It supports both API (Bearer token) and web (cookie-based) authentication.

### Architecture Overview

```
SecurityServiceProvider registers everything from config:
  AuthConfig ← config.json "auth" section
  AuthProviderRegistry ← contains OidcAuthProvider, OAuth2AuthProvider, LocalAuthProvider
  JwtValidator ← validates tokens from any registered provider
  SessionManager ← server-side session for OAuth state/CSRF
  AuthCallbackMiddleware ← handles /auth/login, /auth/callback, /auth/logout

API flow:  ApiAuthenticationMiddleware → validates Bearer token → sets UserContext
Web flow:  WebAuthenticationMiddleware → validates cookie token → redirects to login if invalid
```

### Configuration

Authentication is configured in the `auth` section of your JSON config:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "loginPath": "/auth/login",
        "callbackPath": "/auth/callback",
        "postLoginRedirect": "/",
        "cookieName": "melodic_auth",
        "cookieLifetime": 3600,
        "loginPage": {
            "title": "Sign In",
            "primaryColor": "#4a90d9",
            "logoUrl": "/logo.png"
        },
        "local": {
            "signingKey": "your-secret-key",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600,
            "algorithm": "HS256"
        },
        "providers": {
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "...",
                "clientSecret": "...",
                "redirectUri": "https://myapp.com/auth/callback/google",
                "audience": "...",
                "scopes": "openid profile email"
            },
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "...",
                "clientSecret": "...",
                "redirectUri": "https://myapp.com/auth/callback/github",
                "scopes": "user:email",
                "claimMap": { "sub": "id", "username": "login", "email": "email" }
            },
            "local": {
                "type": "local",
                "label": "Sign in with Email"
            }
        }
    }
}
```

### Auth Provider Types

**`AuthProviderType` enum:**
- `Oidc` — OpenID Connect (validates tokens via JWKS from discovery document)
- `OAuth2` — Generic OAuth2 (authorization code flow, fetches user info, issues local JWT)
- `Local` — Username/password (uses `LocalAuthenticatorInterface`, issues local JWT)

### API Authentication

**`ApiAuthenticationMiddleware`:**
- Reads `Authorization: Bearer <token>` header
- If `auth.api.enabled` is `false`, sets an anonymous `UserContext` and passes through
- Validates the token via `JwtValidator` (tries local config first, then OIDC providers)
- Sets `UserContext` on the request as the `userContext` attribute
- Returns `401` if token is missing or invalid

```php
$app->addMiddleware($container->get(ApiAuthenticationMiddleware::class));
```

### Web Authentication

**`WebAuthenticationMiddleware`:**
- Reads the auth token from a cookie (configurable name, default `melodic_auth`)
- If valid, sets `UserContext` and proceeds
- If invalid or missing, saves the current path in session and redirects to login page
- If `auth.web.enabled` is `false`, sets anonymous context and passes through

**`OptionalWebAuthMiddleware`:**
- Same as `WebAuthenticationMiddleware` but never redirects
- Sets `UserContext::anonymous()` if no valid token is found
- Use on pages that should work for both authenticated and anonymous users

### Login Flow

**`AuthCallbackMiddleware`** handles these routes:

| Route | Method | Action |
|---|---|---|
| `/auth/login` | GET | Renders login page with all configured providers |
| `/auth/login/{provider}` | GET | Initiates OAuth redirect for external providers |
| `/auth/callback/{provider}` | GET/POST | Handles OAuth callback or local form POST |
| `/auth/logout` | GET | Clears auth cookie and redirects to `/` |

**OIDC flow:** Redirect to provider → callback with code → exchange for tokens → validate ID token via JWKS → set cookie

**OAuth2 flow:** Redirect to provider → callback with code → exchange for access token → fetch user info → issue local JWT → set cookie

**Local flow:** POST username/password → `LocalAuthenticatorInterface::authenticate()` → issue local JWT → set cookie

### Authorization

**`AuthorizationMiddleware`:**

```php
new AuthorizationMiddleware(
    requiredEntitlements: ['admin', 'editor'], // user must have ANY of these
    requireAuthentication: true,               // require authenticated user
);
```

- Returns `401` if authentication is required but user is not authenticated
- Returns `403` if user lacks all required entitlements
- Uses `UserContextInterface::hasAnyEntitlement()` (OR logic)

### UserContext

**`UserContext`** (implements `UserContextInterface`):

```php
interface UserContextInterface
{
    public function isAuthenticated(): bool;
    public function getUser(): ?User;
    public function getUsername(): ?string;
    public function hasEntitlement(string $entitlement): bool;
    public function hasAnyEntitlement(string ...$entitlements): bool;
    public function getClaim(string $key, mixed $default = null): mixed;
    public function getClaims(): array;
}
```

**`User` class:**
```php
class User
{
    public readonly string $id;
    public readonly string $username;
    public readonly string $email;
    public readonly array $entitlements;
}
```

**Accessing in controllers:**

```php
// In ApiController or MvcController
$userContext = $this->getUserContext();
$username = $userContext?->getUsername();
if ($userContext?->hasEntitlement('admin')) { /* ... */ }
```

### JWT Validation

**`JwtValidator`:**
1. Peeks at the `iss` claim without full validation
2. If issuer matches `LocalAuthConfig::$issuer`, validates with the local signing key
3. Otherwise, tries each registered OIDC provider's JWKS keys
4. Validates audience claims against provider configuration

### CSRF Protection

The `CsrfToken` class generates and validates tokens stored in the session. Used automatically by `AuthCallbackMiddleware` for local auth form POSTs.

### Refresh Tokens

The framework includes a complete refresh token system:

- **`RefreshToken`** — immutable model with `userId`, `tokenHash`, `familyId`, `generation`, `expiresAt`, `revokedAt`
- **`RefreshTokenService`** — creates, validates, rotates tokens with family-based reuse detection
- **`RefreshTokenRepositoryInterface`** — storage interface (app must implement)
- **`RefreshTokenMiddleware`** — validates refresh token cookie and sets request attributes
- **`RefreshTokenCookieHelper`** — attaches/clears refresh token cookies on responses
- **`RefreshTokenConfig`** — configurable lifetime, cookie name, domain, path, security settings

**Reuse detection:** If a revoked token is presented, the entire token family is revoked (detecting token theft).

### SecurityServiceProvider

Registers all auth services as singletons. Uses the `auth` config section to configure providers, JWT validation, session management, and middleware.

### Login Page Customization

The `LoginPageConfig` class controls the built-in login page appearance:

```json
{
    "loginPage": {
        "title": "Sign In",
        "primaryColor": "#4a90d9",
        "primaryHoverColor": "#357abd",
        "backgroundColor": "#f5f5f5",
        "cardBackground": "#ffffff",
        "textColor": "#333333",
        "subtextColor": "#555555",
        "logoUrl": "/images/logo.png",
        "logoAlt": "My App",
        "faviconUrl": "/favicon.ico",
        "customCss": "body { font-family: 'Inter', sans-serif; }"
    }
}
```

You can replace the built-in renderer by binding your own `AuthLoginRendererInterface` implementation.

---

## Events

### Event System

**Namespace:** `Melodic\Event`

A simple, synchronous event dispatcher with priority support.

### Event Base Class

```php
abstract class Event
{
    private bool $propagationStopped = false;

    public function isPropagationStopped(): bool;
    public function stopPropagation(): void;
}
```

### EventDispatcherInterface

```php
interface EventDispatcherInterface
{
    public function dispatch(object $event): object;
    public function listen(string $eventClass, callable $listener, int $priority = 0): void;
}
```

### EventDispatcher

```php
$dispatcher->listen(UserCreatedEvent::class, function (UserCreatedEvent $e) {
    // handle event
}, priority: 10);

$dispatcher->dispatch(new UserCreatedEvent($user));
```

- Listeners are sorted by priority (higher priority runs first, using `krsort`)
- If an event extends `Event` and has `stopPropagation()` called, remaining listeners are skipped
- Non-`Event` objects can be dispatched but cannot stop propagation

### Registration

```php
$app->register(new EventServiceProvider());
```

This registers `EventDispatcherInterface` → `EventDispatcher` as a singleton.

---

## Caching

### CacheInterface

**Namespace:** `Melodic\Cache\CacheInterface`

```php
interface CacheInterface
{
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function has(string $key): bool;
    public function clear(): bool;
}
```

### Implementations

**`FileCache`** — serializes entries to disk with TTL support:
- Cache directory is created automatically
- Files are named by `md5($key)`
- Expired entries are cleaned up on read
- `clear()` deletes all files in the cache directory

```php
$cache = new FileCache('/path/to/cache');
```

**`ArrayCache`** — in-memory cache (useful for testing):
- Same interface, stores in a PHP array
- Supports TTL via `time()` comparison
- Data is lost when the process ends

### Registration

```php
$app->register(new CacheServiceProvider($app->getBasePath() . '/storage/cache'));
```

This registers `CacheInterface` → `FileCache` as a singleton.

---

## Sessions

### SessionInterface

**Namespace:** `Melodic\Session\SessionInterface`

```php
interface SessionInterface
{
    public function start(): void;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function has(string $key): bool;
    public function remove(string $key): void;
    public function destroy(): void;
    public function regenerate(bool $deleteOld = true): void;
    public function isStarted(): bool;
}
```

### Implementations

**`NativeSession`** — wraps PHP's native session:
- Auto-starts session on first `get()`/`set()`/`has()` call
- Uses `$_SESSION` superglobal
- `regenerate()` calls `session_regenerate_id()`
- `destroy()` calls `session_destroy()` and clears `$_SESSION`

**`ArraySession`** — in-memory session for testing:
- Same interface, stores in a PHP array
- `regenerate()` is a no-op

### SessionManager

**Namespace:** `Melodic\Security\SessionManager`

The `SessionManager` in the Security namespace implements `SessionInterface` and is used specifically by the auth system. It wraps PHP's native session with the same behavior as `NativeSession`.

### Registration

```php
$app->register(new SessionServiceProvider());
```

This registers `SessionInterface` → `NativeSession` as a singleton.

---

## Logging

### LoggerInterface

**Namespace:** `Melodic\Log\LoggerInterface`

```php
interface LoggerInterface
{
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
    public function log(LogLevel $level, string $message, array $context = []): void;
}
```

### LogLevel Enum

```php
enum LogLevel: string
{
    case EMERGENCY = 'emergency'; // severity 0
    case ALERT     = 'alert';     // severity 1
    case CRITICAL  = 'critical';  // severity 2
    case ERROR     = 'error';     // severity 3
    case WARNING   = 'warning';   // severity 4
    case NOTICE    = 'notice';    // severity 5
    case INFO      = 'info';      // severity 6
    case DEBUG     = 'debug';     // severity 7
}
```

### Implementations

**`FileLogger`** — writes daily log files:
- File naming: `melodic-YYYY-MM-DD.log`
- Minimum level filtering (messages below minimum are discarded)
- Message interpolation: `{key}` placeholders replaced from context
- Exception formatting: if `$context['exception']` is a `Throwable`, appends class, message, file:line, and trace
- Uses `FILE_APPEND | LOCK_EX` for safe concurrent writes
- Silent failure: logger never crashes the application

```
[2026-03-21 14:30:00] ERROR: Failed to process order {orderId}
  Exception: RuntimeException
  Message: Connection refused
  At: /app/src/Services/OrderService.php:42
  Trace:
    #0 /app/src/Controllers/OrderController.php(28): ...
```

**`NullLogger`** — discards all messages (default when no logger is registered).

### Registration

```php
$app->register(new LoggingServiceProvider());
```

Reads `logging.path` and `logging.level` from config. Defaults to `$basePath/logs` and `debug`.

### Configuration

```json
{
    "logging": {
        "path": "storage/logs",
        "level": "warning"
    }
}
```

---

## Error Handling

### ExceptionHandler

**Namespace:** `Melodic\Error\ExceptionHandler`

Converts exceptions to HTTP responses with content negotiation (JSON vs HTML).

**Status code resolution:**

| Exception Type | Status Code |
|---|---|
| `HttpException` | Uses `getStatusCode()` |
| `SecurityException` | 401 |
| `\JsonException` | 400 |
| Everything else | 500 |

**Content negotiation:** Returns JSON if:
- `Accept` header contains `application/json`, OR
- `Content-Type` header contains `application/json`, OR
- Request path starts with `/api`

**Debug mode:**
- When `app.debug` is `true`: 5xx errors include exception class, file, line, and trace
- When `false`: 5xx errors show generic "An internal server error occurred."
- 4xx errors always show the exception message

**Logging:**
- 5xx → `$logger->error()`
- 4xx → `$logger->warning()`
- Context includes: exception object, status code, HTTP method, path

### ErrorHandlerMiddleware

```php
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(LoggerInterface $logger, bool $debug = false) {}
}
```

Wraps the entire middleware pipeline. Catches any `\Throwable` and delegates to `ExceptionHandler`.

### HTTP Exceptions

**`HttpException`** (base class):
```php
class HttpException extends \RuntimeException
{
    public function __construct(int $statusCode, string $message = '', ?\Throwable $previous = null);
    public function getStatusCode(): int;

    // Static factories
    public static function notFound(string $message = 'Not Found'): self;
    public static function forbidden(string $message = 'Forbidden'): self;
    public static function badRequest(string $message = 'Bad Request'): self;
    public static function methodNotAllowed(string $message = 'Method Not Allowed'): self;
}
```

**Specific exceptions:**
- `BadRequestException` — 400
- `NotFoundException` — 404
- `MethodNotAllowedException` — 405

### SecurityException

```php
class SecurityException extends \RuntimeException {}
```

Automatically resolves to HTTP 401.

---

## View Engine (MVC)

### ViewEngine

**Namespace:** `Melodic\View\ViewEngine`

Renders `.phtml` templates with layout support and named sections.

```php
$viewEngine = new ViewEngine(
    viewsPath: '/path/to/views',
    cache: $cacheInstance, // optional CacheInterface
);
```

**Methods:**

| Method | Description |
|---|---|
| `render(string $template, array $data, ?string $layout)` | Renders template with optional layout |
| `renderCached(string $template, array $data, ?string $layout, int $ttl)` | Cached rendering (uses CacheInterface) |
| `renderBody()` | Called in layouts to output the page content |
| `renderSection(string $name)` | Called in layouts to output a named section |
| `beginSection(string $name)` | Starts capturing a named section (in templates) |
| `endSection()` | Ends the current section capture |

**How rendering works:**

1. Template is rendered first — sections are captured via `beginSection()`/`endSection()`
2. Template output becomes the "body"
3. Layout is rendered — calls `renderBody()` for main content and `renderSection()` for named sections

**Template example (`views/home/index.phtml`):**
```php
<h1><?= htmlspecialchars($heading) ?></h1>
<p>Welcome to the site.</p>

<?php $this->beginSection('scripts') ?>
<script src="/app.js"></script>
<?php $this->endSection() ?>
```

**Layout example (`views/layouts/main.phtml`):**
```php
<!DOCTYPE html>
<html>
<head>
    <title><?= htmlspecialchars($viewBag->title ?? 'App') ?></title>
    <?= $this->renderSection('head') ?>
</head>
<body>
    <main><?= $this->renderBody() ?></main>
    <?= $this->renderSection('scripts') ?>
</body>
</html>
```

**Template data:** Variables from the `$data` array are extracted into the template scope with `extract()`. The `ViewEngine` instance is accessible as `$this`.

### ViewBag

**Namespace:** `Melodic\View\ViewBag`

A dynamic key-value store using `__get`/`__set` magic methods:

```php
$viewBag = new ViewBag();
$viewBag->title = 'Home Page';
$viewBag->user = $currentUser;
echo $viewBag->title;        // 'Home Page'
echo $viewBag->nonexistent;  // null (no error)
isset($viewBag->title);      // true
```

### MvcController

```php
class MvcController extends Controller
{
    protected ViewBag $viewBag;
    protected ?string $layout = null;

    public function __construct(private readonly ViewEngine $viewEngine) {}

    protected function view(string $template, array $data = []): Response;
    public function viewBag(): ViewBag;
    public function setLayout(string $layout): void;
    protected function getUserContext(): ?UserContextInterface;
}
```

**Usage:**

```php
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
```

The `view()` method automatically adds `$viewBag` to the template data.

---

## Console & CLI

### Console

**Namespace:** `Melodic\Console\Console`

The CLI application runner:

```php
$console = new Console();
$console->setName('My App');
$console->setVersion('1.0.0'); // defaults to Framework::VERSION
$console->register(new RouteListCommand($router));
$console->register(new CacheClearCommand($cache));
exit($console->run($argv));
```

**Behavior:**
- First argument is the command name: `php bin/console route:list`
- `help` or no argument shows available commands
- Unknown commands show error + help
- Returns exit code (0 = success, 1 = error)

### Command Base Class

```php
abstract class Command implements CommandInterface
{
    public function __construct(string $name, string $description) {}

    abstract public function execute(array $args): int;

    protected function writeln(string $text): void;  // stdout + newline
    protected function write(string $text): void;     // stdout
    protected function error(string $text): void;     // stderr
    protected function table(array $headers, array $rows): void; // formatted table
}
```

### Built-in Commands

| Command | Description |
|---|---|
| `route:list` | Lists all registered routes in a table |
| `cache:clear` | Clears the application cache |
| `claude:install` | Installs Claude Code agents and skills |
| `make:entity <Name>` | Generates 8 CQRS files for an entity |
| `make:project <name> [--type=full\|api\|mvc]` | Scaffolds a new project |
| `make:config <environment>` | Creates an environment config file |

### make:entity

Generates all files for a CQRS entity:

```bash
vendor/bin/melodic make:entity Church
```

Creates:
1. `src/DTO/ChurchModel.php` — Model with `id` property
2. `src/Data/Church/Queries/GetAllChurchesQuery.php` — Get all query
3. `src/Data/Church/Queries/GetChurchByIdQuery.php` — Get by ID query
4. `src/Data/Church/Commands/CreateChurchCommand.php` — Create command
5. `src/Data/Church/Commands/UpdateChurchCommand.php` — Update command
6. `src/Data/Church/Commands/DeleteChurchCommand.php` — Delete command
7. `src/Services/ChurchService.php` — Full CRUD service
8. `src/Controllers/ChurchController.php` — Full CRUD API controller

Uses `Stub` helper for pluralization, case conversion, and template rendering.

### make:project

Scaffolds a new application with three project types:
- `full` (default) — MVC views + API routing
- `api` — API-only (no views directory)
- `mvc` — MVC-only

```bash
vendor/bin/melodic make:project my-app --type=api
```

Creates: composer.json, config files, public/index.php, .htaccess, .gitignore, bin/console, AppServiceProvider, and (for MVC) HomeController with layout and view templates.

### Stub Helper

**Namespace:** `Melodic\Console\Make\Stub`

Utility class for code generation:

| Method | Description | Example |
|---|---|---|
| `render(string $template, array $replacements)` | Replaces `{key}` placeholders | `{entity}` → `Church` |
| `pascalCase(string $input)` | Converts to PascalCase | `church_group` → `ChurchGroup` |
| `camelCase(string $input)` | Converts to camelCase | `church_group` → `churchGroup` |
| `snakeCase(string $input)` | Converts to snake_case | `ChurchGroup` → `church_group` |
| `pluralize(string $word)` | English pluralization | `Church` → `Churches`, `Person` → `People` |

Pluralization handles: irregular words, words ending in s/sh/ch/x/z (→ es), consonant+y (→ ies), f/fe (→ ves), and default (→ s).

---

## Testing

### Test Configuration

Tests use PHPUnit 11.0+ with the standard configuration:

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

**Test namespace:** `Tests\` maps to `tests/` (PSR-4).

### Test Structure

Tests mirror the source directory structure:

```
tests/
├── Cache/          CacheInterface tests (FileCache, ArrayCache)
├── Console/        Console, Command, Stub tests
├── Controller/     Controller response helper tests
├── Core/           Application, Configuration tests
├── Data/           DbContext, Model tests
├── DI/             Container, ServiceProvider tests
├── Error/          ExceptionHandler tests
├── Event/          EventDispatcher tests
├── Http/           Request, Response, Middleware tests
├── Log/            Logger tests
├── Routing/        Route, Router tests
├── Security/       Auth middleware, JWT, UserContext tests
├── Service/        Service base class tests
├── Session/        Session tests
└── Validation/     Validator, validation rules tests
```

### Testing Patterns

**Testing queries and commands:**

```php
// Create a real or mock DbContext
$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT)');
$context = new DbContext($pdo);

// Test a query
$query = new GetUserByIdQuery(1);
$result = $query->execute($context);
$this->assertNull($result);
```

**Testing services:**

```php
$context = new DbContext($pdo);
$service = new UserService($context);
$users = $service->getAll();
$this->assertIsArray($users);
```

**Testing controllers:**

```php
$controller = new UserController($userService);
$controller->setRequest(new Request(...));
$response = $controller->index();
$this->assertEquals(200, $response->getStatusCode());
```

**Testing middleware:**

```php
$middleware = new CorsMiddleware(['allowedOrigins' => ['https://example.com']]);
$handler = new class implements RequestHandlerInterface {
    public function handle(Request $request): Response {
        return new Response(200);
    }
};
$response = $middleware->process($request, $handler);
$this->assertStringContainsString('https://example.com', $response->getHeaders()['Access-Control-Allow-Origin']);
```

**Testing validation:**

```php
$validator = new Validator();
$model = UserModel::fromArray(['username' => '', 'email' => 'invalid']);
$result = $validator->validate($model);
$this->assertFalse($result->isValid);
$this->assertArrayHasKey('username', $result->errors);
```

**`ArrayCache` and `ArraySession`** are available as test doubles for `CacheInterface` and `SessionInterface`.

### Running Tests

```bash
composer test           # or: vendor/bin/phpunit
vendor/bin/phpstan analyse   # static analysis (level 6)
```

---

## Best Practices

### Naming Conventions

| Type | Location | Pattern | Example |
|---|---|---|---|
| DTO / Model | `src/DTO/` | `{Entity}Model` | `ChurchModel` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery`, `GetAll{Plural}Query` | `GetChurchByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create{Entity}Command`, `Update{Entity}Command` | `CreateChurchCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ChurchService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ChurchController` |
| Service Provider | `src/Providers/` | `{Name}ServiceProvider` | `AppServiceProvider` |
| Middleware | `src/Middleware/` | `{Purpose}Middleware` | `RateLimitMiddleware` |

### End-to-End Feature Structure

To add a new `Order` feature:

1. **Model** — `src/DTO/OrderModel.php` extending `Model`
2. **Queries** — `src/Data/Order/Queries/GetAllOrdersQuery.php`, `GetOrderByIdQuery.php`
3. **Commands** — `src/Data/Order/Commands/CreateOrderCommand.php`, `UpdateOrderCommand.php`, `DeleteOrderCommand.php`
4. **Service** — `src/Services/OrderService.php` extending `Service`
5. **Controller** — `src/Controllers/OrderController.php` extending `ApiController`
6. **Routes** — `$router->apiResource('/api/orders', OrderController::class)`

Or use the scaffold: `vendor/bin/melodic make:entity Order`

### What Belongs Where

| Concern | Location |
|---|---|
| SQL and database access | Query/Command classes |
| Business logic, orchestration | Service classes |
| HTTP input/output | Controllers |
| Cross-cutting concerns | Middleware |
| Configuration, service wiring | ServiceProvider |
| Data validation rules | Attributes on Model properties |

### Anti-Patterns

- **No direct DB in controllers** — always go through a service
- **No business logic in queries/commands** — they are pure data access; logic belongs in services
- **No mediator** — instantiate queries/commands directly in services
- **No facades** — use constructor injection
- **No magic** — explicit, direct code paths
- **Keep controllers thin** — delegate to services, return responses
- **Keep services focused** — one entity per service, compose services for cross-entity operations

### Code Style

- `declare(strict_types=1)` in every PHP file
- PHP 8.2+ features: enums, readonly properties, constructor promotion, match expressions, named arguments
- PascalCase classes, camelCase methods/properties
- Use `Model::toPascalArray()` for INSERT params, `Model::toUpdateArray()` for UPDATE params

---

## Extension Points

### Adding Middleware

1. Create a class implementing `MiddlewareInterface`:

```php
class RateLimitMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($this->isRateLimited($request)) {
            return new JsonResponse(['error' => 'Rate limit exceeded'], 429);
        }
        return $handler->handle($request);
    }
}
```

2. Register globally or per-route:

```php
// Global
$app->addMiddleware(new RateLimitMiddleware());

// Per-route
$router->get('/api/search', SearchController::class, 'index', [RateLimitMiddleware::class]);
```

### Registering Services

Create a `ServiceProvider`:

```php
class MailServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(MailerInterface::class, function (Container $c) {
            $config = $c->get(Configuration::class);
            return new SmtpMailer($config->get('mail'));
        });
    }
}
```

Register it:

```php
$app->register(new MailServiceProvider());
```

### Custom Validation Rules

Create a PHP 8 attribute with a `validate()` method and a `$message` property:

```php
#[Attribute(Attribute::TARGET_PROPERTY)]
class Unique
{
    public function __construct(
        public readonly string $table,
        public readonly string $column,
        public readonly string $message = 'This value already exists',
    ) {}

    public function validate(mixed $value): bool
    {
        // Custom validation logic
        return true;
    }
}
```

### Custom Login Page

Implement `AuthLoginRendererInterface` and bind it in your service provider:

```php
$container->singleton(AuthLoginRendererInterface::class, function (Container $c) {
    return new MyCustomLoginRenderer($c->get(ViewEngine::class));
});
```

### Custom Auth Provider

Implement `AuthProviderInterface`:

```php
class SamlAuthProvider implements AuthProviderInterface
{
    public function getName(): string { return 'saml'; }
    public function getLabel(): string { return 'Sign in with SAML'; }
    public function getType(): AuthProviderType { return AuthProviderType::Oidc; }
    public function handleLogin(Request $request, SessionManager $session): Response { /* ... */ }
    public function handleCallback(Request $request, SessionManager $session): AuthResult { /* ... */ }
}
```

Register it in the `AuthProviderRegistry`.

### Plugin / Module System

There is no formal plugin system. Extend the framework by:
- Creating `ServiceProvider` subclasses for modular registration
- Using the DI container for interface binding
- Using middleware for cross-cutting concerns
- Using the event dispatcher for decoupled communication

---

## Appendix: Framework Version

The framework version is defined as a constant in `src/Framework.php`:

```php
class Framework
{
    public const VERSION = '1.7.3';
}
```

The `Console` class reads `Framework::VERSION` automatically for CLI output.

## Appendix: Dependencies

- `php` >= 8.2
- `firebase/php-jwt` ^6.0 || ^7.0 — JWT encoding/decoding/validation
- `phpstan/phpstan` ^2.0 (dev) — static analysis
- `phpunit/phpunit` ^11.0 (dev) — testing

## Appendix: Utilities

```php
Melodic\Utilities::kill(mixed $var): never
```

Dumps a variable with `print_r()` wrapped in `<pre>` tags and calls `exit(1)`. Debug-only utility.
