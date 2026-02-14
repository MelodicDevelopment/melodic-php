# Melodic PHP Framework

A modern PHP 8.2+ framework with CQRS data patterns, JWT authentication, and a PSR-15-style middleware pipeline. Uses a layered architecture: **Controller → Service → Query/Command**.

## Requirements

- PHP 8.2+
- Composer
- PDO extension (SQLite, MySQL, PostgreSQL, or any PDO driver)

## Installation

```bash
composer install
```

## Quick Start

```bash
# Start the example application
php -S localhost:8080 -t example/public
```

Then visit:
- `http://localhost:8080/` — MVC home page
- `http://localhost:8080/about` — About page
- `http://localhost:8080/api/users` — JSON API listing all users
- `http://localhost:8080/api/users/1` — Single user by ID

## Architecture

```
HTTP Request → Middleware Pipeline → Router → Controller → Service → Query/Command → Database
```

The framework enforces a clean separation of concerns across layers:

| Layer | Responsibility | Framework Namespace |
|---|---|---|
| HTTP | Request/Response objects, middleware pipeline | `Melodic\Http` |
| Routing | URL pattern matching, route groups, API resources | `Melodic\Routing` |
| Controller | Receives requests, returns responses, delegates to services | `Melodic\Controller` |
| Service | Business logic, orchestrates queries and commands | `Melodic\Service` |
| Data | Database access via DbContext, CQRS query/command objects | `Melodic\Data` |
| Security | JWT validation, authentication, authorization | `Melodic\Security` |
| DI | Dependency injection container with auto-wiring | `Melodic\DI` |
| View | Template engine with layouts and sections | `Melodic\View` |
| Core | Application bootstrap, configuration | `Melodic\Core` |

## Project Structure

```
melodic-php/
├── composer.json                            # PSR-4: Melodic\ → src/
├── src/
│   ├── Core/
│   │   ├── Application.php                  # App builder: config, middleware, routes, run()
│   │   └── Configuration.php                # JSON config loader with dot-notation access
│   ├── Http/
│   │   ├── HttpMethod.php                   # Enum: GET, POST, PUT, DELETE, PATCH, OPTIONS
│   │   ├── Request.php                      # Wraps superglobals, immutable attributes
│   │   ├── Response.php                     # Status code, headers, body, send()
│   │   ├── JsonResponse.php                 # JSON-encoded response
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php       # process(Request, RequestHandler): Response
│   │       ├── RequestHandlerInterface.php
│   │       ├── Pipeline.php                 # Chains middleware to a final handler
│   │       ├── CorsMiddleware.php           # Configurable CORS headers
│   │       └── JsonBodyParserMiddleware.php
│   ├── Routing/
│   │   ├── Route.php                        # Method + URI pattern + controller/action
│   │   ├── Router.php                       # Registration, groups, apiResource()
│   │   └── RoutingMiddleware.php            # Resolves route, invokes controller via DI
│   ├── Controller/
│   │   ├── Controller.php                   # Abstract base with json(), created(), noContent(), etc.
│   │   ├── ApiController.php                # JSON API controller with getUserContext()
│   │   └── MvcController.php                # View rendering with layout/section support
│   ├── DI/
│   │   ├── ContainerInterface.php           # get(), has(), bind(), singleton()
│   │   ├── Container.php                    # Auto-wiring, singletons, interface bindings, factories
│   │   └── ServiceProvider.php              # Modular registration base class
│   ├── Data/
│   │   ├── DbContextInterface.php           # query(), queryFirst(), command(), scalar(), transaction()
│   │   ├── DbContext.php                    # PDO wrapper with model hydration via Reflection
│   │   ├── QueryInterface.php               # CQRS query: getSql(), execute()
│   │   ├── CommandInterface.php             # CQRS command: getSql(), execute() returns int
│   │   └── Model.php                        # Base DTO with fromArray() and toArray()
│   ├── Security/
│   │   ├── JwtValidator.php                 # Firebase JWT validation with issuer/audience checks
│   │   ├── User.php                         # User with id, username, email, entitlements
│   │   ├── UserContextInterface.php         # isAuthenticated(), getUser(), hasEntitlement()
│   │   ├── UserContext.php                  # Built from JWT claims
│   │   ├── AuthenticationMiddleware.php     # Bearer token extraction and validation
│   │   ├── AuthorizationMiddleware.php      # Entitlement-based access control
│   │   └── SecurityException.php
│   ├── Service/
│   │   └── Service.php                      # Base service holding DbContext references
│   └── View/
│       ├── ViewEngine.php                   # Renders .phtml templates with layouts/sections
│       └── ViewBag.php                      # Dynamic key-value store for view data
└── example/                                 # Working demo application
    ├── config/config.json
    ├── public/
    │   ├── index.php                    # Entry point
    │   └── .htaccess                    # Apache rewrite rules
    ├── src/
    │   ├── Controllers/
    │   │   ├── UserApiController.php    # RESTful API example
    │   │   └── HomeController.php       # MVC view example
    │   ├── Services/
    │   │   └── UserService.php          # Business logic layer
    │   ├── Queries/
    │   │   ├── GetAllUsersQuery.php
    │   │   └── GetUserByIdQuery.php
    │   ├── Commands/
    │   │   ├── CreateUserCommand.php
    │   │   └── DeleteUserCommand.php
    │   └── Models/
    │       └── UserModel.php
    └── views/
        ├── layouts/main.phtml
        └── home/
            ├── index.phtml
            └── about.phtml
```

## Application Bootstrap

Create an entry point (`public/index.php`) that configures and runs the app:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Data\DbContext;
use Melodic\Data\DbContextInterface;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Security\AuthenticationMiddleware;
use Melodic\Security\JwtValidator;

$app = new Application(__DIR__ . '/..');
$app->loadConfig('config/config.json');

// Register services
$app->services(function ($container) use ($app) {
    $container->singleton(DbContextInterface::class, function () use ($app) {
        $pdo = new PDO($app->config('database.dsn'));
        return new DbContext($pdo);
    });

    $container->singleton(JwtValidator::class, function () use ($app) {
        return new JwtValidator(
            secret: $app->config('jwt.secret'),
            algorithm: $app->config('jwt.algorithm', 'HS256'),
        );
    });
});

// Add middleware (executed in order)
$app->addMiddleware(new CorsMiddleware($app->config('cors') ?? []));
$app->addMiddleware(new JsonBodyParserMiddleware());
$app->addMiddleware(new AuthenticationMiddleware(
    $app->getContainer()->get(JwtValidator::class)
));

// Define routes
$app->routes(function ($router) {
    $router->apiResource('/api/users', UserApiController::class);
});

$app->run();
```

### `Application` API

| Method | Description |
|---|---|
| `loadConfig(string $path)` | Load a JSON config file (relative to base path or absolute) |
| `config(?string $key, mixed $default)` | Read config value using dot-notation, e.g. `$app->config('database.host')` |
| `addMiddleware(MiddlewareInterface $m)` | Add middleware to the pipeline (order matters) |
| `services(callable $callback)` | Register services in the DI container; receives `Container` |
| `register(ServiceProvider $provider)` | Register a service provider module |
| `routes(callable $callback)` | Define routes; receives `Router` |
| `getContainer()` | Access the DI container directly |
| `getRouter()` | Access the router directly |
| `run(?Request $request)` | Boot providers, build pipeline, dispatch request, send response |

## Configuration

Configuration is loaded from JSON files and supports dot-notation access:

```json
{
    "database": {
        "dsn": "mysql:host=localhost;dbname=myapp",
        "username": "root",
        "password": "secret"
    },
    "jwt": {
        "secret": "your-secret-key",
        "algorithm": "HS256",
        "issuer": "my-app",
        "audience": "my-app"
    },
    "cors": {
        "allowedOrigins": ["*"],
        "allowedMethods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    }
}
```

```php
$app->config('database.dsn');          // "mysql:host=localhost;dbname=myapp"
$app->config('jwt.algorithm', 'HS256'); // with default fallback
```

You can also load and merge multiple config files:

```php
$app->loadConfig('config/config.json');
$app->loadConfig('config/config.local.json'); // overrides/merges
```

## Routing

### Basic Routes

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

Register all five RESTful routes in one call:

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
| DELETE | `/users/{id}` | `destroy($id)` |

### Route Groups

Group routes under a common prefix:

```php
$router->group('/api', function (Router $router) {
    $router->apiResource('/users', UserController::class);
    $router->apiResource('/posts', PostController::class);
});
// Produces: /api/users, /api/users/{id}, /api/posts, etc.
```

### Route Parameters

Parameters in `{braces}` are extracted and passed as arguments to the controller action:

```php
$router->get('/users/{id}/posts/{postId}', UserPostController::class, 'show');

// Controller receives them as named arguments:
public function show(string $id, string $postId): JsonResponse
{
    // ...
}
```

## Controllers

### API Controllers

Extend `ApiController` for JSON API endpoints:

```php
use Melodic\Controller\ApiController;

class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService, // auto-injected by DI
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->userService->getAll();
        return $this->json(array_map(fn($u) => $u->toArray(), $users));
    }

    public function show(string $id): JsonResponse
    {
        $user = $this->userService->getById((int) $id);
        return $user ? $this->json($user->toArray()) : $this->notFound();
    }

    public function store(): JsonResponse
    {
        $body = $this->request->body();
        $id = $this->userService->create($body['username'], $body['email']);
        $user = $this->userService->getById($id);
        return $this->created($user->toArray(), "/api/users/{$id}");
    }

    public function destroy(string $id): Response
    {
        $this->userService->delete((int) $id);
        return $this->noContent();
    }
}
```

### Response Helpers

The base `Controller` class provides these helpers:

| Method | Status | Description |
|---|---|---|
| `json($data, $status)` | 200 | JSON response |
| `created($data, $location)` | 201 | Created with optional Location header |
| `noContent()` | 204 | Empty response |
| `badRequest($data)` | 400 | Bad request error |
| `unauthorized($data)` | 401 | Authentication required |
| `forbidden($data)` | 403 | Access denied |
| `notFound($data)` | 404 | Resource not found |

### MVC Controllers

Extend `MvcController` for HTML views with layout support:

```php
use Melodic\Controller\MvcController;

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

### Accessing the User Context

In `ApiController`, access the authenticated user from the JWT:

```php
$userContext = $this->getUserContext();

if ($userContext->isAuthenticated()) {
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
$app->services(function (Container $container) {
    // Singleton: same instance every time
    $container->singleton(DbContextInterface::class, fn() => new DbContext($pdo));

    // Transient: new instance each time (default)
    $container->bind(UserService::class, UserService::class);

    // Interface to implementation
    $container->bind(UserServiceInterface::class, UserService::class);

    // Factory with access to the container
    $container->singleton(JwtValidator::class, fn(Container $c) => new JwtValidator(
        secret: $c->get(Configuration::class)->get('jwt.secret'),
    ));

    // Register an existing instance
    $container->instance(Configuration::class, $config);
});
```

### Auto-Wiring

The container automatically resolves constructor dependencies via Reflection. If a class type-hints its constructor parameters, the container resolves each one recursively:

```php
class UserApiController extends ApiController
{
    public function __construct(
        private readonly UserService $userService, // resolved automatically
    ) {}
}

class UserService extends Service
{
    // DbContextInterface is resolved from the container binding
    public function __construct(
        protected readonly DbContextInterface $context,
    ) {}
}
```

### Service Providers

For modular registration, extend `ServiceProvider`:

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(DbContextInterface::class, fn() => new DbContext($pdo));
        $container->bind(UserService::class, UserService::class);
    }

    public function boot(Container $container): void
    {
        // Post-registration logic (all providers registered)
    }
}

$app->register(new AppServiceProvider());
```

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

Services orchestrate queries and commands. They hold a `DbContext` reference and expose domain-level methods:

```php
use Melodic\Service\Service;

class UserService extends Service
{
    public function getAll(): array
    {
        return (new GetAllUsersQuery())->execute($this->context);
    }

    public function getById(int $id): ?UserModel
    {
        return (new GetUserByIdQuery($id))->execute($this->context);
    }

    public function create(string $username, string $email): int
    {
        (new CreateUserCommand($username, $email))->execute($this->context);
        return $this->context->lastInsertId();
    }
}
```

### DbContext

The `DbContext` wraps PDO with typed query methods and automatic model hydration:

| Method | Description |
|---|---|
| `query(string $class, string $sql, array $params): array` | Execute SELECT, return array of hydrated model objects |
| `queryFirst(string $class, string $sql, array $params): ?object` | Execute SELECT, return first result or null |
| `command(string $sql, array $params): int` | Execute INSERT/UPDATE/DELETE, return affected row count |
| `scalar(string $sql, array $params): mixed` | Execute query, return single column value |
| `transaction(callable $callback): mixed` | Wrap callback in BEGIN/COMMIT with automatic ROLLBACK on exception |
| `lastInsertId(): int` | Return last auto-increment ID |

### Models

Extend `Model` for DTOs with automatic array conversion:

```php
use Melodic\Data\Model;

class UserModel extends Model
{
    public int $id;
    public string $username;
    public string $email;
}

// Hydrated automatically by DbContext
$user = $context->queryFirst(UserModel::class, "SELECT * FROM users WHERE id = :id", ['id' => 1]);
$user->toArray(); // ['id' => 1, 'username' => 'alice', 'email' => 'alice@example.com']
```

## Middleware

### Built-in Middleware

**CorsMiddleware** — Adds CORS headers to all responses and handles preflight OPTIONS requests:

```php
$app->addMiddleware(new CorsMiddleware([
    'allowedOrigins' => ['https://example.com'],
    'allowedMethods' => ['GET', 'POST', 'PUT', 'DELETE'],
    'allowedHeaders' => ['Content-Type', 'Authorization'],
    'maxAge' => 3600,
]));
```

**JsonBodyParserMiddleware** — Parses `application/json` request bodies so they are available via `$request->body()`:

```php
$app->addMiddleware(new JsonBodyParserMiddleware());
```

**AuthenticationMiddleware** — Extracts the Bearer token from the Authorization header, validates it via `JwtValidator`, and attaches a `UserContext` to the request:

```php
$app->addMiddleware(new AuthenticationMiddleware($jwtValidator));
```

**AuthorizationMiddleware** — Enforces authentication and/or entitlement checks:

```php
// Require authentication
$app->addMiddleware(new AuthorizationMiddleware());

// Require specific entitlements
$app->addMiddleware(new AuthorizationMiddleware(
    requiredEntitlements: ['admin', 'editor'],
    requireAuthentication: true,
));
```

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

Middleware is executed in the order it is added. Each middleware can:
- Modify the request before passing it down
- Short-circuit the pipeline by returning a response directly
- Modify the response on the way back up

## Security

### JWT Authentication

The `JwtValidator` validates tokens using [firebase/php-jwt](https://github.com/firebase/php-jwt):

```php
$validator = new JwtValidator(
    secret: 'your-secret-key',
    algorithm: 'HS256',
    issuer: 'my-app',       // optional: validate iss claim
    audience: 'my-app',     // optional: validate aud claim
);

// Validate a token (throws SecurityException on failure)
$claims = $validator->validate($token);

// Create a token (useful for testing)
$token = $validator->encode([
    'sub' => 1,
    'username' => 'alice',
    'email' => 'alice@example.com',
    'entitlements' => ['admin', 'editor'],
    'iss' => 'my-app',
    'aud' => 'my-app',
    'exp' => time() + 3600,
]);
```

### User Context

When `AuthenticationMiddleware` is active, controllers can access the authenticated user:

```php
$userContext = $this->getUserContext(); // available in ApiController

$userContext->isAuthenticated();                  // bool
$userContext->getUser();                          // User object or null
$userContext->getUsername();                       // string or null
$userContext->hasEntitlement('admin');             // bool
$userContext->hasAnyEntitlement('admin', 'editor'); // bool
```

The `UserContext` is built from JWT claims with this mapping:
- `sub` → user ID
- `username` or `preferred_username` → username
- `email` → email
- `entitlements` → array of entitlement strings

## Views

### Template Rendering

Views are `.phtml` files rendered by `ViewEngine`. Data is extracted as local variables:

```php
// Controller
$this->setLayout('layouts/main');
return $this->view('home/index', ['message' => 'Hello World', 'items' => $items]);
```

```php
<!-- views/home/index.phtml -->
<h1><?= htmlspecialchars($message) ?></h1>
<ul>
    <?php foreach ($items as $item): ?>
        <li><?= htmlspecialchars($item) ?></li>
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
    <title><?= htmlspecialchars($viewBag->title ?? 'App') ?></title>
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

`ViewBag` is a dynamic key-value store for passing data between controllers and layouts:

```php
// Controller
$this->viewBag->title = 'My Page';
$this->viewBag->breadcrumbs = ['Home', 'Users'];

// Layout
<title><?= htmlspecialchars($viewBag->title) ?></title>
```

## Conventions

- **PHP 8.2+** features throughout: enums, readonly properties, constructor promotion, match expressions, named arguments
- **PascalCase** for classes, **camelCase** for methods and properties
- **Controller → Service → Query/Command** — controllers never access the database directly
- **CQRS data access** — Query/Command objects executed via DbContext
- **No facades, no mediator** — dependencies are explicit and directly instantiated
- **Immutable request/response** — `withAttribute()`, `withHeader()`, etc. return new instances

## Dependencies

| Package | Purpose |
|---|---|
| `firebase/php-jwt` | JWT token encoding and decoding |
