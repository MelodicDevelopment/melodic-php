# Melodic PHP Framework

Modern PHP 8.2+ framework with CQRS, JWT authentication, and middleware pipeline. Uses a layered architecture (Controller → Service → Query/Command).

## Architecture

```
HTTP Request → Middleware Pipeline → Router → Controller → Service → Query/Command → Database
```

### Namespace Mapping
- `Melodic\Core` — Application bootstrap, configuration
- `Melodic\Http` — Request, Response, middleware pipeline
- `Melodic\Routing` — Route registration, matching, API resources
- `Melodic\Controller` — Base controllers (API + MVC)
- `Melodic\DI` — Dependency injection container with auto-wiring
- `Melodic\Data` — DbContext (PDO wrapper), CQRS interfaces
- `Melodic\Security` — JWT validation, authentication/authorization middleware
- `Melodic\Service` — Base service class
- `Melodic\View` — Template engine with layouts and sections

## Project Structure

```
melodic-php/
├── composer.json                        # PSR-4: Melodic\ → src/
├── src/
│   ├── Core/
│   │   ├── Application.php              # App builder: config, middleware, routes, run()
│   │   └── Configuration.php            # JSON config with dot-notation access
│   ├── Http/
│   │   ├── HttpMethod.php               # Enum: GET, POST, PUT, DELETE, PATCH, OPTIONS
│   │   ├── Request.php                  # Wraps superglobals, immutable attributes
│   │   ├── Response.php                 # Status, headers, body, send()
│   │   ├── JsonResponse.php             # JSON-encoded response
│   │   └── Middleware/
│   │       ├── MiddlewareInterface.php
│   │       ├── RequestHandlerInterface.php
│   │       ├── Pipeline.php             # Chains middleware → final handler
│   │       ├── CorsMiddleware.php
│   │       └── JsonBodyParserMiddleware.php
│   ├── Routing/
│   │   ├── Route.php                    # Method + pattern + controller/action
│   │   ├── Router.php                   # Registration, groups, apiResource()
│   │   └── RoutingMiddleware.php        # Resolves route → DI → controller action
│   ├── Controller/
│   │   ├── Controller.php               # Abstract: json(), created(), noContent(), etc.
│   │   ├── ApiController.php            # getUserContext() from JWT
│   │   └── MvcController.php            # view(), viewBag(), layout system
│   ├── DI/
│   │   ├── ContainerInterface.php
│   │   ├── Container.php                # Auto-wiring, singleton, interface bindings
│   │   └── ServiceProvider.php          # Modular registration base class
│   ├── Data/
│   │   ├── DbContextInterface.php       # query(), queryFirst(), command(), transaction()
│   │   ├── DbContext.php                # PDO wrapper with model hydration
│   │   ├── QueryInterface.php           # CQRS query: getSql(), execute()
│   │   ├── CommandInterface.php         # CQRS command: getSql(), execute() → int
│   │   └── Model.php                    # Base DTO: fromArray(), toArray()
│   ├── Security/
│   │   ├── JwtValidator.php             # Firebase JWT validation
│   │   ├── User.php                     # id, username, email, entitlements
│   │   ├── UserContextInterface.php
│   │   ├── UserContext.php              # Built from JWT claims
│   │   ├── AuthenticationMiddleware.php # Bearer token → UserContext
│   │   ├── AuthorizationMiddleware.php  # Entitlement checks
│   │   └── SecurityException.php
│   ├── Service/
│   │   └── Service.php                  # Base: holds DbContext
│   └── View/
│       ├── ViewEngine.php               # .phtml rendering with layouts/sections
│       └── ViewBag.php                  # Dynamic key-value store
└── web/php.melodic.dev/                 # Documentation website (dogfoods the framework)
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
$app = new Application(__DIR__);
$app->loadConfig('config/config.json');
$app->addMiddleware(new CorsMiddleware($corsConfig));
$app->addMiddleware(new AuthenticationMiddleware($jwtValidator));
$app->services(function(Container $c) { /* register bindings */ });
$app->routes(function(Router $r) {
    $r->apiResource('/api/users', UserController::class);
});
$app->run();
```

### DI Container

```php
// Interface binding
$container->bind(UserServiceInterface::class, UserService::class);

// Singleton with factory
$container->singleton(DbContextInterface::class, fn() => new DbContext($pdo));

// Auto-wiring resolves constructor dependencies automatically
$controller = $container->get(UserController::class); // injects UserService, etc.
```

### Routing

```php
$router->get('/users', UserController::class, 'index');
$router->apiResource('/users', UserController::class); // RESTful CRUD
$router->group('/api', function($r) {
    $r->apiResource('/users', UserController::class);
}, middleware: [AuthorizationMiddleware::class]);
```

### JWT Authentication

```php
// AuthenticationMiddleware reads Bearer token, validates JWT, sets UserContext
// In controller:
$userContext = $this->getUserContext();
if ($userContext->hasEntitlement('admin')) { /* ... */ }
```

### MVC Views

```php
// Controller
$this->setLayout('layouts/main');
return $this->view('home/index', ['message' => 'Hello']);

// Template (home/index.phtml)
<h1><?= $message ?></h1>
<?php $this->beginSection('scripts') ?>
<script src="/app.js"></script>
<?php $this->endSection() ?>

// Layout uses: <?= $this->renderBody() ?> and <?= $this->renderSection('scripts') ?>
```

## Application Structure (for projects built with Melodic)

When generating code for applications that use this framework, follow this canonical layout:

```
my-api/
├── composer.json               # PSR-4: App\ → src/
├── config/
│   ├── config.json
│   └── config.local.json       # gitignored
├── public/
│   ├── index.php               # Entry point
│   └── .htaccess
├── bin/
│   └── console                 # CLI entry point
├── src/
│   ├── Controllers/            # ApiController or MvcController subclasses
│   ├── Services/               # Service subclasses (business logic layer)
│   ├── DTO/                    # Models extending Melodic\Data\Model (flat)
│   ├── Data/
│   │   └── {Entity}/
│   │       ├── Queries/        # QueryInterface implementations
│   │       └── Commands/       # CommandInterface implementations
│   ├── Middleware/              # Custom MiddlewareInterface implementations
│   └── Providers/
│       └── AppServiceProvider.php
├── storage/
│   ├── cache/
│   └── logs/
└── tests/
```

MVC projects additionally have `views/layouts/` and `views/{page}/`.

### Naming Conventions for Application Code

| Type | Location | Naming | Example |
|---|---|---|---|
| DTO / Model | `src/DTO/` | `{Entity}Model` | `ChurchModel` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery`, `GetAll{Plural}Query` | `GetChurchByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create{Entity}Command`, `Update{Entity}Command`, `Delete{Entity}Command` | `CreateChurchCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ChurchService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ChurchController` |
| Provider | `src/Providers/` | `{Name}ServiceProvider` | `AppServiceProvider` |

### Scaffolding Commands

```bash
vendor/bin/melodic make:project my-api                 # API project
vendor/bin/melodic make:project my-site --type=mvc     # MVC project
vendor/bin/melodic make:entity Church                  # Generate 8 CQRS files for an entity
```

`make:entity` generates: DTO model, 2 queries (GetAll, GetById), 3 commands (Create, Update, Delete), service, and controller.

## Conventions

- **Never add Co-Authored-By, Signed-off-by, or any AI/contributor attribution to commits**
- **PHP 8.2+**: enums, readonly properties, constructor promotion, match expressions
- **PascalCase** classes, **camelCase** methods/properties
- **Controller → Service → Query/Command** — no direct DB access in controllers
- **CQRS data access** — Query/Command objects executed via DbContext
- **No facades, no mediator** — direct, explicit instantiation

## Getting Started

```bash
composer install
php -S localhost:8080 -t web/php.melodic.dev/public
```

## Dependencies

- `firebase/php-jwt` — JWT encoding/decoding
- PHP 8.2+ with PDO extension
