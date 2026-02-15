---
name: melodic:scaffold-resource
description: Scaffold a full CQRS resource (Model, Queries, Commands, Service, Controller, routes). Use when the user wants to add a new domain entity or API resource.
disable-model-invocation: true
argument-hint: [EntityName]
---

# Scaffold a CQRS Resource

You are adding a new domain entity to a Melodic PHP application. The entity name is `$ARGUMENTS` (if no argument was provided, ask the user for the entity name — e.g. "Post", "Product", "Order").

## Step 1: Gather Requirements

Use AskUserQuestion to ask:

1. **Entity fields**: What fields does this entity have? Suggest common defaults based on the entity name and let the user customize. Always include `id` (auto-increment int) and `created_at` (datetime string). Provide 2-3 suggested fields based on the entity name (e.g. for "Post": title, body; for "Product": name, price, description).

2. **Controller type**: What kind of controller?
    - **API only (Recommended)** — JSON API controller with full CRUD
    - **Web + API** — Both MVC views and JSON API endpoints
    - **Web only** — MVC views with forms

3. **Auth required**: Should the routes require authentication?
    - **Yes (Recommended)** — Wrap in auth middleware
    - **No** — Public routes

## Step 2: Detect Project Structure

Before creating files, read the project to understand:

- Check `composer.json` for the PSR-4 namespace (e.g. `App\\` or `Example\\`)
- Check existing files in `src/` to match the directory structure (Controllers/, Services/, Models/, Queries/, Commands/)
- Check `config/routes.php` for the existing route style and middleware usage
- Use the same patterns found in existing code

## Step 3: Create the Files

All class names use **PascalCase**. The entity name should be singular (e.g. "Post" not "Posts"). The URL path should be plural lowercase (e.g. "/posts").

### 3a. Model — `src/Models/{Entity}Model.php`

```php
<?php

declare(strict_types=1);

namespace {Namespace}\Models;

use Melodic\Data\Model;

class {Entity}Model extends Model
{
    public int $id;
    // ... user-specified fields with appropriate types
    public string $createdAt;
}
```

Use PHP types that match the database column types: `int`, `string`, `float`, `bool`.

### 3b. Queries

**GetAll{Entities}Query.php** — `src/Queries/GetAll{Entities}Query.php`

```php
<?php

declare(strict_types=1);

namespace {Namespace}\Queries;

use Melodic\Data\QueryInterface;
use Melodic\Data\DbContextInterface;
use {Namespace}\Models\{Entity}Model;

class GetAll{Entities}Query implements QueryInterface
{
    private readonly string $sql;

    public function __construct()
    {
        $this->sql = "SELECT * FROM {table} ORDER BY created_at DESC";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): array
    {
        return $context->query({Entity}Model::class, $this->sql);
    }
}
```

**Get{Entity}ByIdQuery.php** — `src/Queries/Get{Entity}ByIdQuery.php`

```php
// Same pattern — takes int $id in constructor, uses WHERE id = :id, returns ?{Entity}Model
```

### 3c. Commands

**Create{Entity}Command.php** — `src/Commands/Create{Entity}Command.php`

```php
// Takes entity fields (excluding id and created_at) in constructor
// INSERT INTO {table} (...) VALUES (...)
// created_at uses date('Y-m-d H:i:s')
```

**Update{Entity}Command.php** — `src/Commands/Update{Entity}Command.php`

```php
// Takes id + entity fields in constructor
// UPDATE {table} SET ... WHERE id = :id
```

**Delete{Entity}Command.php** — `src/Commands/Delete{Entity}Command.php`

```php
// Takes int $id in constructor
// DELETE FROM {table} WHERE id = :id
```

### 3d. Service Interface — `src/Services/{Entity}ServiceInterface.php`

```php
<?php

declare(strict_types=1);

namespace {Namespace}\Services;

use {Namespace}\Models\{Entity}Model;

interface {Entity}ServiceInterface
{
    public function getAll(): array;
    public function getById(int $id): ?{Entity}Model;
    public function create(/* fields */): int;
    public function update(int $id, /* fields */): void;
    public function delete(int $id): void;
}
```

### 3e. Service — `src/Services/{Entity}Service.php`

```php
// Extends Melodic\Service\Service
// Implements {Entity}ServiceInterface
// Each method instantiates the appropriate Query/Command and calls ->execute($this->context)
// create() returns $this->context->lastInsertId()
```

### 3f. API Controller — `src/Controllers/{Entity}ApiController.php` (if API)

```php
// Extends Melodic\Controller\ApiController
// Constructor takes {Entity}ServiceInterface (auto-injected)
// index() — return $this->json(array_map(fn($e) => $e->toArray(), $this->service->getAll()))
// show(string $id) — getById, return json or notFound()
// store() — read fields from $this->request->body(), create, return created()
// update(string $id) — read fields from body, update, return json
// destroy(string $id) — delete, return noContent()
```

### 3g. MVC Controller — `src/Controllers/{Entity}Controller.php` (if Web)

```php
// Extends Melodic\Controller\MvcController
// Constructor takes {Entity}ServiceInterface + ViewEngine
// index() — list view
// show(string $id) — detail view
// create() — form view
// store() — handle form POST, redirect
```

### 3h. Views (if Web)

Create views in `views/{entities}/`:

- `index.phtml` — table listing all entities
- `show.phtml` — single entity detail
- `create.phtml` — form for creating new entity

## Step 4: Update Existing Files

### Register the service binding

Find where services are registered (usually `index.php` or a ServiceProvider) and add:

```php
$container->bind({Entity}ServiceInterface::class, {Entity}Service::class);
```

### Add routes

Open `config/routes.php` and add the new routes:

```php
// API routes
$router->apiResource('/{entities}', {Entity}ApiController::class);

// Or inside an existing group:
$router->group('/api', function (Router $router) {
    $router->apiResource('/{entities}', {Entity}ApiController::class);
}, middleware: [ApiAuthenticationMiddleware::class]);
```

## Step 5: Database Migration Hint

After creating all files, show the user the SQL to create the table:

```sql
CREATE TABLE {table} (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    -- ... fields based on user input
    created_at TEXT NOT NULL
);
```

Adjust syntax for the database driver if known (e.g. `SERIAL` for PostgreSQL, `AUTO_INCREMENT` for MySQL).

## Step 6: Summary

Print a summary of:

- All files created
- Files modified (routes, service bindings)
- The SQL migration to run
- URLs to test (e.g. `GET /api/{entities}`, `POST /api/{entities}`, etc.)

## Important

- Follow all conventions from CLAUDE.md
- Match the existing code style in the project (check an existing Query, Command, Service, or Controller for reference)
- Use `declare(strict_types=1)` in every file
- Table name is plural lowercase (e.g. entity "Post" → table "posts")
- URL path is plural lowercase with hyphens for multi-word (e.g. "BlogPost" → "/blog-posts")
- The entity class name is always singular PascalCase
