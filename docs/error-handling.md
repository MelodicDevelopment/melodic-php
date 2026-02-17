# Error Handling

Melodic provides structured exception handling through the `ExceptionHandler` class, which catches exceptions and returns appropriate JSON or HTML error responses.

## How It Works

The `ExceptionHandler` is integrated into `Application::run()`. When any exception escapes the middleware pipeline, the handler:

1. Maps the exception to an HTTP status code
2. Logs the error via `LoggerInterface`
3. Detects whether the client expects JSON or HTML
4. Returns a formatted error response

## Exception-to-Status-Code Mapping

| Exception | Status Code |
|---|---|
| `HttpException` (or subclass) | Uses `getStatusCode()` |
| `ValidationException` | 422 |
| `SecurityException` | 401 |
| `JsonException` | 400 |
| Any other `Throwable` | 500 |

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

// Generic with any status code
throw new HttpException(409, 'Resource conflict');

// Static factory methods on HttpException
throw HttpException::notFound('User not found');
throw HttpException::forbidden('Access denied');
throw HttpException::badRequest('Invalid input');
throw HttpException::methodNotAllowed();
```

## JSON vs HTML Detection

The handler returns JSON when any of these are true:
- The `Accept` header contains `application/json`
- The `Content-Type` header contains `application/json`
- The request path starts with `/api`

Otherwise it returns a styled HTML error page.

## Debug Mode

```php
$handler = new ExceptionHandler($logger);
$handler->setDebug(true); // Show full details in responses
```

**Debug mode enabled** — responses include exception class, file, line number, and full stack trace. Use this in development.

**Debug mode disabled** (default) — 5xx errors return a generic "An internal server error occurred." message. 4xx errors still show their specific message. Use this in production.

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

All exceptions are logged automatically:
- **5xx errors** → `error` level
- **4xx errors** → `warning` level

Log entries include the HTTP method, path, status code, and full exception details. See [Logging](logging.md) for configuration.

## Configuration

The exception handler is typically configured in `Application` bootstrap or a service provider:

```php
$app->services(function (Container $container) {
    $container->singleton(ExceptionHandler::class, function (Container $c) {
        $handler = new ExceptionHandler($c->get(LoggerInterface::class));
        $handler->setDebug($c->get(Configuration::class)->get('app.debug', false));
        return $handler;
    });
});
```
