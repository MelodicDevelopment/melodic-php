---
name: melodic:add-middleware
description: Scaffold a new middleware class for the Melodic HTTP pipeline. Use when the user wants to create custom middleware.
disable-model-invocation: true
argument-hint: [MiddlewareName]
---

# Scaffold a Middleware

You are adding a new middleware class to a Melodic PHP application. The middleware name is `$ARGUMENTS` (if no argument was provided, ask the user for a name — e.g. "RateLimiting", "RequestLogging", "ApiKeyAuth").

## Step 1: Gather Requirements

Use AskUserQuestion to ask:

1. **What should this middleware do?** Get a brief description of the middleware's purpose.

2. **Should it run before or after the handler?**
    - **Before (Recommended)** — Inspect/modify the request, possibly short-circuit (e.g., auth checks, rate limiting)
    - **After** — Inspect/modify the response (e.g., add headers, logging)
    - **Both** — Wrap the handler (e.g., timing, try/catch)

3. **Does it need configuration?** (e.g., allowed origins, rate limits, API keys)
    - **Yes** — Accept config in constructor
    - **No** — No configuration needed

## Step 2: Detect Project Structure

- Check `composer.json` for the PSR-4 namespace
- Look for existing middleware in `src/Middleware/` to match the style
- Check how middleware is registered (usually in `public/index.php` or a bootstrap file)

## Step 3: Create the Middleware

Create `src/Middleware/{Name}Middleware.php`:

```php
<?php

declare(strict_types=1);

namespace {Namespace}\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;

class {Name}Middleware implements MiddlewareInterface
{
    public function __construct(
        // Configuration parameters if needed
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        // Before handler: inspect/modify request or short-circuit
        // $response = new Response(403); return $response; // short-circuit example

        $response = $handler->handle($request);

        // After handler: inspect/modify response
        // $response->setHeader('X-Custom-Header', 'value');

        return $response;
    }
}
```

### Pattern: Before-only middleware

```php
public function process(Request $request, RequestHandlerInterface $handler): Response
{
    // Check something on the request
    if (!$this->isAllowed($request)) {
        return new JsonResponse(['error' => 'Forbidden'], 403);
    }

    return $handler->handle($request);
}
```

### Pattern: After-only middleware

```php
public function process(Request $request, RequestHandlerInterface $handler): Response
{
    $response = $handler->handle($request);
    $response->setHeader('X-Request-Id', uniqid());
    return $response;
}
```

### Pattern: Wrapping middleware

```php
public function process(Request $request, RequestHandlerInterface $handler): Response
{
    $start = microtime(true);

    try {
        $response = $handler->handle($request);
    } finally {
        $duration = microtime(true) - $start;
        // Log or add timing header
    }

    return $response;
}
```

## Step 4: Show Registration

Show the user how to register the middleware. If it's global:

```php
// In public/index.php or bootstrap
$app->addMiddleware(new {Name}Middleware(/* config */));
```

If it's route-specific:

```php
// In route registration
$router->group('/api', function (Router $router) {
    // routes...
}, middleware: [{Name}Middleware::class]);
```

## Step 5: Summary

Print:
- The file created
- How to register it (global vs route-specific)
- Any configuration needed

## Important

- Follow all conventions from CLAUDE.md
- Use `declare(strict_types=1)`
- Use constructor promotion and readonly for config parameters
- Match the style of existing middleware in the project
- The middleware MUST implement `Melodic\Http\Middleware\MiddlewareInterface`
- The `process()` method signature is: `process(Request $request, RequestHandlerInterface $handler): Response`
