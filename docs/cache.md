# Cache

Melodic provides a caching abstraction modeled on PSR-16 (SimpleCacheInterface) with two built-in drivers: `FileCache` for persistent storage and `ArrayCache` for testing and single-request caching.

## CacheInterface

| Method | Description |
|---|---|
| `get(string $key, mixed $default = null): mixed` | Retrieve a value, or `$default` if missing/expired |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store a value with optional TTL in seconds |
| `delete(string $key): bool` | Remove a value |
| `has(string $key): bool` | Check if a key exists and is not expired |
| `clear(): bool` | Remove all cached values |

## FileCache

Stores serialized values as files in a directory. Keys are hashed with MD5 for filesystem-safe filenames. Expired entries are cleaned up on read.

```php
use Melodic\Cache\FileCache;

$cache = new FileCache('/path/to/cache');

$cache->set('user:42', $userData);                  // no expiration
$cache->set('token:abc', $tokenData, ttl: 3600);    // expires in 1 hour

$user = $cache->get('user:42');                      // returns cached data
$missing = $cache->get('nonexistent', 'default');    // returns 'default'

$cache->has('user:42');   // true
$cache->delete('user:42');
$cache->clear();          // removes all files
```

The cache directory is created automatically if it doesn't exist.

## ArrayCache

In-memory cache for testing or single-request use. Same interface, no filesystem I/O.

```php
use Melodic\Cache\ArrayCache;

$cache = new ArrayCache();
$cache->set('key', 'value', ttl: 60);
$cache->get('key'); // 'value'
```

## Service Provider

Register caching in the DI container:

```php
use Melodic\Cache\CacheServiceProvider;

$app->register(new CacheServiceProvider('/path/to/cache'));
```

This binds `CacheInterface` to a `FileCache` singleton. Inject `CacheInterface` in any service or controller:

```php
class ReportService
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    public function getReport(int $id): array
    {
        $key = "report:{$id}";

        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }

        $report = $this->buildReport($id);
        $this->cache->set($key, $report, ttl: 3600);

        return $report;
    }
}
```

## View Caching

The `ViewEngine` accepts an optional `CacheInterface` and provides `renderCached()` for caching rendered templates:

```php
$engine = new ViewEngine('/path/to/views', $cache);

// Cache the rendered output for 1 hour
$html = $engine->renderCached('home/index', ['name' => 'World'], 'layouts/main', ttl: 3600);
```

Cache keys are derived from the template name, layout, and a hash of the data array, so different data produces different cache entries. See [Views](../README.md#views) for more on the template engine.
