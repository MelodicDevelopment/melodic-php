# Error Handling

Melodic provides structured exception handling through the `ExceptionHandler` class, which catches exceptions and returns appropriate JSON or HTML error responses.

## How It Works

The `ExceptionHandler` is wired up by `Application`, which wraps the entire middleware stack in an outermost error-handling layer, so an exception thrown anywhere in the pipeline — your middleware, routing, a controller, a service — is caught and converted into a response. The handler:

1. Returns the response of a matching [custom mapper](#custom-exception-mappers), if one is registered
2. Otherwise maps the exception to an HTTP status code
3. Detects whether the client expects JSON or HTML and builds a formatted error response

Either way the exception is logged via `LoggerInterface` with the status of the response actually returned, and the response then picks up the headers of any [response decorators](#headers-on-error-responses) (CORS, security headers).

The handler is created with `app.debug` from configuration and with the `LoggerInterface` bound in the container (or a `NullLogger` when none is bound). `Application` registers it as a container singleton when the application boots, **before** service providers' `boot()` methods run.

`Application::handle(Request $request): Response` (since 4.1) runs a request through the same pipeline and returns the response — including error responses — without sending it, which is convenient in tests. `run()` is `handle()` plus capturing the request and sending the response.

### Failures outside the pipeline

`run()` has a last-resort `try`/`catch` for failures that happen before the pipeline exists, such as an exception thrown from a provider's `boot()`. If the `ExceptionHandler` is available it is used as usual; otherwise the response is a plain-text `500` — `An internal server error occurred.`, or the message, file, line, and trace when `app.debug` is on.

A request with an HTTP method the framework does not model (`PROPFIND`, `TRACE`, ...) is answered with `501 Not Implemented`.

## Exception-to-Status-Code Mapping

| Exception | Status Code |
|---|---|
| `Melodic\Http\Exception\HttpException` (or subclass) | Uses `getStatusCode()` |
| `Melodic\Security\SecurityException` | 401 |
| `\JsonException` | 400 |
| `Melodic\Validation\ValidationException` | 400 |
| `Melodic\Data\ModelBindingException` | 400 |
| Any other `Throwable` | 500 |

The first matching row wins. Things the framework itself throws:

| Situation | Exception | Status |
|---|---|---|
| No route matches the path | `NotFoundException` | 404 |
| The path exists, but not for this HTTP method (3.0) | `MethodNotAllowedException` | 405, with an `Allow` header |
| `JsonBodyParserMiddleware` receives malformed JSON | `BadRequestException` (`Invalid JSON in request body: …`) | 400 |
| A `SecurityException` escapes the security layer or your own code | `SecurityException` | 401 |

A `ValidationException` thrown by your code is rendered differently from the other rows: the body is its `$result->errors` map (`{"field": ["message", ...]}`), always as JSON and without debug detail — the same shape as automatic model-binding failures. A `ModelBindingException` uses the normal `{"error": "..."}` / HTML rendering with its field message.

Model-binding and validation failures on controller action parameters never reach the handler at all — `RoutingMiddleware` returns their `400` response directly (see [Validation](validation.md#automatic-model-binding)), so they are not logged.

## HTTP Exception Hierarchy

Throw these from controllers or middleware for clean error responses:

```php
use Melodic\Http\Exception\HttpException;
use Melodic\Http\Exception\NotFoundException;
use Melodic\Http\Exception\BadRequestException;
use Melodic\Http\Exception\MethodNotAllowedException;

// Specific exception classes
throw new NotFoundException('User not found');           // 404
throw new BadRequestException('Missing required field'); // 400
throw new MethodNotAllowedException();                   // 405

// 405 that advertises the supported methods in an Allow header
throw MethodNotAllowedException::forMethods(['GET', 'POST']);

// Generic with any status code
throw new HttpException(409, 'Resource conflict');

// Static factory methods on HttpException
throw HttpException::notFound('User not found');
throw HttpException::forbidden('Access denied');
throw HttpException::badRequest('Invalid input');
throw HttpException::methodNotAllowed();
```

Notes:

- `HttpException`'s constructor is `(int $statusCode, string $message = '', ?\Throwable $previous = null)`. The subclasses take `(string $message = '…', ?\Throwable $previous = null)`.
- There are dedicated classes only for 400, 404, and 405. Use `new HttpException($status, $message)` for everything else (401, 403, 409, 422, ...).
- The static factories return a plain `HttpException`, not the subclass — `HttpException::notFound()` is not an `instanceof NotFoundException`, and `HttpException::methodNotAllowed()` carries no method list, so no `Allow` header is sent.
- If the message is empty, the response uses the standard reason phrase for the status (`Not Found`, `Forbidden`, `Conflict`, `Too Many Requests`, ...), or `Error` for a status the handler has no phrase for.

### 405 and the `Allow` header

When the exception is a `MethodNotAllowedException` with a non-empty method list, the response includes `Allow: GET, POST` (RFC 9110). The router's own 405s always carry it.

## JSON vs HTML Detection

The handler returns JSON when any of these are true:
- The `Accept` header contains `application/json`
- The `Content-Type` header contains `application/json`
- The request path is exactly `/api` or starts with `/api/` (since 4.0 this is a segment match — `/apiary` is not an API path)

Otherwise it returns a styled HTML error page. The rule is fixed; a JSON API mounted somewhere other than `/api` gets JSON errors only when the client sends one of those headers (or you register a [custom mapper](#custom-exception-mappers)).

## Debug Mode

Debug mode follows the `app.debug` configuration value (default `false`):

```json
{ "app": { "debug": true } }
```

When constructing a handler yourself, call `setDebug()`:

```php
$handler = new ExceptionHandler($logger);
$handler->setDebug(true); // Show full details in responses
```

**Debug mode enabled** — responses include the real exception message plus the exception class, file, line number, and full stack trace. Use this in development only.

**Debug mode disabled** (default) — internal detail is masked:

- **5xx** errors return the generic message `An internal server error occurred.`
- **`SecurityException`** (401) returns `Authentication failed.` (4.0). Its real message describes *why* authentication failed (expired token, wrong audience, ...) and is only written to the log.
- Other **4xx** errors (`HttpException` and subclasses) show their own message, so write those messages for the client — never put internal detail in them.

### JSON response (debug mode)

```json
{
    "error": "User not found",
    "exception": "Melodic\\Http\\Exception\\NotFoundException",
    "file": "/app/src/Controllers/UserController.php",
    "line": 42,
    "trace": ["#0 ...", "#1 ..."]
}
```

### JSON response (production)

```json
{
    "error": "User not found"
}
```

## Logging

Every exception that reaches the handler is logged. The level follows the status of the response: the built-in mapping above, or — for an exception handled by a custom mapper — the status of the response the mapper returned.
- **5xx errors** → `error` level: `Server error: GET /path [500]`, followed by the exception class, message, location, and stack trace
- **4xx errors** → `warning` level: a single line, `Client error: GET /path [404]` followed by the exception class and message. There is no stack trace unless debug mode is on, so routine 404s do not flood the log (since 4.1; earlier versions logged the full trace).

Logging goes to the `LoggerInterface` bound in the container. **If none is bound, nothing is logged** (a `NullLogger` is used) — register `LoggingServiceProvider` or your own binding. See [Logging](logging.md) for configuration.

### PHP warnings and notices

By default PHP warnings and notices are not exceptions and never reach the handler. Set `app.errorsAsExceptions` to `true` (since 4.1) and `Application::run()` installs an error handler that turns them into `ErrorException`s — and therefore into logged 500 responses. Deprecations are never converted, and errors silenced by `error_reporting` or `@` are left alone. The setting is off by default.

## Custom Exception Mappers

`ExceptionHandler::registerMapper()` lets you turn your own exception types into any response you like:

```php
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Error\ExceptionHandler;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // bindings ...
    }

    public function boot(Container $container): void
    {
        $handler = $container->get(ExceptionHandler::class);

        $handler->registerMapper(
            InsufficientStockException::class,
            fn(\Throwable $e, Request $request) => new JsonResponse(['error' => $e->getMessage()], 409),
        );
    }
}
```

- **Register mappers in a provider's `boot()`.** The shared handler is bound in the container just before providers are booted. In `register()` or an `$app->services()` callback it is not bound yet, so `get(ExceptionHandler::class)` would either fail or auto-wire a different, throwaway instance, and the mapper would never run.
- A mapper is a `callable(\Throwable $exception, Request $request): Response`.
- **Matching is subclass-aware** (`is_a`): a mapper for a base class or an interface also handles its subclasses / implementations.
- **The first registered match wins** — registration order, not specificity. Register specific classes before general ones. Registering the same class again replaces its mapper.
- A mapper for a broad type also captures framework exceptions: `HttpException` extends `\RuntimeException`, so a `\RuntimeException` (or `\Throwable`) mapper would swallow every 404 and 405 too.
- **Mappers run before the built-in mapping**, so a mapper can also override how `HttpException`, `ValidationException`, and the other built-in types are rendered.
- The handler adds nothing to the mapper's response — no debug detail, no JSON/HTML negotiation, no `Allow` header — and no message masking applies, so decide yourself what is safe to expose. [Response decorators](#headers-on-error-responses) still apply.
- The exception is logged with the status of the mapper's response: a domain exception mapped to 409 is logged as a one-line `Client error`, not as a 500.

## Headers on Error Responses

An exception unwinds past the middleware stack, so middleware that adds headers on the way out never sees the error response. To keep CORS and security headers on errors — without them a cross-origin browser cannot even read the error body — a global middleware can implement `Melodic\Http\Middleware\ResponseDecoratorInterface` (since 4.1):

```php
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Middleware\ResponseDecoratorInterface;

class RequestIdMiddleware implements MiddlewareInterface, ResponseDecoratorInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        return $this->decorate($request, $handler->handle($request));
    }

    public function decorate(Request $request, Response $response): Response
    {
        return $response->withHeader('X-Request-Id', $this->id);
    }
}
```

After the `ExceptionHandler` has produced an error response (built-in or from a custom mapper), `Application` calls `decorate()` on every middleware registered with `addMiddleware()` that implements the interface. `CorsMiddleware` and `SecurityHeadersMiddleware` do. Other middleware — `RateLimitMiddleware`, route-level middleware, anything that does not implement the interface — does not touch error responses. The plain-text last-resort response described under [Failures outside the pipeline](#failures-outside-the-pipeline) is not decorated either.

## Configuration

Normally there is nothing to configure: `Application` creates the handler from `app.debug` and the bound logger, and you customize it through `registerMapper()` in `boot()`.

To replace the handler entirely, bind your own `ExceptionHandler` singleton before the application runs; `Application` leaves an existing binding alone (since 4.1 — earlier versions always replaced it). You are then responsible for the logger and the debug flag:

```php
$app->services(function (Container $container) {
    $container->singleton(ExceptionHandler::class, function (Container $c) {
        $handler = new ExceptionHandler($c->get(LoggerInterface::class));
        $handler->setDebug((bool) $c->get(Configuration::class)->get('app.debug', false));
        return $handler;
    });
});
```
