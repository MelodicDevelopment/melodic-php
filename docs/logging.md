# Logging

Melodic provides a logging abstraction with `FileLogger` for daily rotating log files and `NullLogger` for testing or environments where logging is not needed.

## LoggerInterface

All loggers implement `LoggerInterface` with standard log level methods:

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

## FileLogger

Writes to daily rotating log files (`melodic-YYYY-MM-DD.log`). Supports a minimum log level to filter out lower-severity messages.

```php
use Melodic\Log\FileLogger;
use Melodic\Log\LogLevel;

// Log everything (DEBUG and above)
$logger = new FileLogger('/path/to/logs');

// Only log WARNING and above
$logger = new FileLogger('/path/to/logs', LogLevel::WARNING);

$logger->info('User {username} logged in', ['username' => 'alice']);
$logger->error('Failed to process order', [
    'exception' => $exception,  // full trace included automatically
    'orderId' => 123,
]);
```

### Log Format

```
[2026-02-17 14:30:00] INFO: User alice logged in
[2026-02-17 14:30:01] ERROR: Failed to process order
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

### Exception Logging

If the context includes an `exception` key with a `Throwable`, the logger appends the exception class, message, file, line, and stack trace.

## NullLogger

Discards all log messages. Use for testing or when logging is not configured.

```php
use Melodic\Log\NullLogger;

$logger = new NullLogger();
$logger->error('This goes nowhere'); // no-op
```

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
        "level": "warning"
    }
}
```

- `logging.path` — log directory (defaults to `{basePath}/logs`)
- `logging.level` — minimum log level (defaults to `debug`)

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
