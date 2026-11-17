# Logging

Melodic provides a logging abstraction with `FileLogger` for daily rotating log files and `NullLogger` for testing or environments where logging is not needed.

## LoggerInterface

All loggers implement `LoggerInterface` with standard log level methods. Every method takes `(string $message, array $context = [])` and returns `void`:

| Method | Level |
|---|---|
| `emergency($message, $context)` | System is unusable |
| `alert($message, $context)` | Immediate action required |
| `critical($message, $context)` | Critical conditions |
| `error($message, $context)` | Runtime errors |
| `warning($message, $context)` | Warnings |
| `notice($message, $context)` | Normal but significant events |
| `info($message, $context)` | Informational messages |
| `debug($message, $context)` | Debug details |
| `log(LogLevel $level, $message, $context)` | Log at a specific level |

## Log Levels

The `LogLevel` enum defines severity from most to least critical:

```
EMERGENCY > ALERT > CRITICAL > ERROR > WARNING > NOTICE > INFO > DEBUG
```

It is a string-backed enum (`LogLevel::WARNING->value === 'warning'`). `LogLevel::parse('warning')` converts a level name (case-insensitive) to the enum and throws a `\ValueError` for an unknown name.

## FileLogger

Writes to one log file per day (`melodic-YYYY-MM-DD.log`, dated in PHP's default timezone). Supports a minimum log level to filter out lower-severity messages, and an optional retention limit.

```php
use Melodic\Log\FileLogger;
use Melodic\Log\LogLevel;

// Log everything (DEBUG and above)
$logger = new FileLogger('/path/to/logs');

// Only log WARNING and above
$logger = new FileLogger('/path/to/logs', LogLevel::WARNING);

// Keep only the 14 newest daily files (since 4.1)
$logger = new FileLogger('/path/to/logs', LogLevel::INFO, maxFiles: 14);

$logger->info('User {username} logged in', ['username' => 'alice']);
$logger->error('Failed to process order {orderId}', [
    'exception' => $exception,  // full trace included automatically
    'orderId' => 123,
]);
```

### Files and Permissions

- The log directory is created on the first write (recursively) with mode `0750`; an existing directory is used as-is.
- Each new log file is set to `0640`. Logs routinely contain paths and stack traces, so they are kept away from other local users.
- Entries are appended with an exclusive lock (`LOCK_EX`), so concurrent requests do not interleave lines.
- **Retention.** With `maxFiles` greater than `0`, only the newest N `melodic-YYYY-MM-DD.log` files are kept; older ones are deleted when a new day's file is created. The default `0` keeps every file, so set a limit or use your own rotation (e.g. `logrotate`).
- **The logger never throws.** If the directory cannot be created or the file cannot be written, the entry is silently dropped — check directory ownership/permissions if no log file appears.

### Log Format

```
[2026-02-17 14:30:00] INFO: User alice logged in
[2026-02-17 14:30:01] ERROR: Failed to process order 123
  Exception: RuntimeException
  Message: Connection refused
  At: /app/src/Services/OrderService.php:42
  Trace:
    #0 /app/src/Controllers/OrderController.php(18): OrderService->process()
    #1 ...
```

### Message Interpolation

Context values are interpolated into the message using `{key}` placeholders:

```php
$logger->info('Order {orderId} placed by {username}', [
    'orderId' => 123,
    'username' => 'alice',
]);
// [2026-02-17 14:30:00] INFO: Order 123 placed by alice
```

- Only scalar values and objects with `__toString()` are interpolated. Arrays, `null`, and other objects leave the `{key}` placeholder in the message unchanged.
- Context values that have no matching placeholder are **not written** — there is no separate context dump. Put everything you want logged into the message.
- The `exception` key is reserved (see below) and is never interpolated.

### Log Injection Protection

Carriage returns and line feeds are replaced with a space in every interpolated context value *and* in the final message line as a whole, so user-controlled data — whether passed as context or concatenated into the message — cannot forge additional log entries. The exception block is the one exception: it is intentionally multi-line and is written verbatim (including the exception message).

### Exception Logging

If the context includes an `exception` key holding a `Throwable`, the logger appends the exception class, message, file, line, and stack trace as indented lines below the entry.

## NullLogger

Discards all log messages. Use for testing or when logging is not configured.

```php
use Melodic\Log\NullLogger;

$logger = new NullLogger();
$logger->error('This goes nowhere'); // no-op
```

## PSR-3 Loggers

`Melodic\Log\PsrLoggerAdapter` (since 4.1) wraps any PSR-3 logger (Monolog, etc.) as a Melodic `LoggerInterface`. It needs the `psr/log` package, which any PSR-3 logger already requires:

```php
use Melodic\Log\LoggerInterface;
use Melodic\Log\PsrLoggerAdapter;

$container->singleton(LoggerInterface::class, fn() => new PsrLoggerAdapter($monolog));
```

Messages and context are passed through unchanged, so interpolation, formatting, and sanitisation are whatever the wrapped logger does — the `FileLogger` behavior described above does not apply.

## Service Provider

Register logging in the DI container:

```php
use Melodic\Log\LoggingServiceProvider;

$app->register(new LoggingServiceProvider());
```

The `LoggingServiceProvider` reads from configuration:

```json
{
    "logging": {
        "path": "/path/to/logs",
        "level": "warning",
        "maxFiles": 14
    }
}
```

- `logging.path` — log directory (defaults to `{basePath}/logs`). The value is passed to `FileLogger` unchanged, so use an absolute path; a relative path resolves against the PHP process's working directory, not the application base path.
- `logging.level` — minimum log level, case-insensitive (defaults to `debug`). An unrecognised level throws a `\ValueError` when the logger is first resolved.
- `logging.maxFiles` — number of daily files to keep (defaults to `0`, keep all; since 4.1)

The provider binds `LoggerInterface` to a singleton `FileLogger`, created lazily the first time it is resolved.

> Projects scaffolded with `make:project` contain a `storage/logs/` directory, but the default log path is `{basePath}/logs`. To log into `storage/logs`, set the path before the logger is first used:
>
> ```php
> $app->getConfiguration()->set('logging.path', $app->getBasePath() . '/storage/logs');
> $app->register(new LoggingServiceProvider());
> ```

The framework's [exception handler](error-handling.md) logs through `LoggerInterface` when one is bound in the container. Without a binding (i.e. without this provider or your own registration) it falls back to `NullLogger`, and unhandled exceptions are not logged anywhere. Likewise, `ApiAuthenticationMiddleware` (as bound by `SecurityServiceProvider`) logs `API token rejected: {reason}` at `warning` level only when a logger is bound; the client still receives a generic 401.

Inject `LoggerInterface` wherever needed:

```php
class PaymentService
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function charge(int $userId, float $amount): void
    {
        $this->logger->info('Charging {amount} for user {userId}', [
            'amount' => $amount,
            'userId' => $userId,
        ]);

        // ...
    }
}
```
