# Cache

Melodic provides a caching abstraction modeled on PSR-16 (SimpleCacheInterface) with two built-in drivers: `FileCache` for persistent storage and `ArrayCache` for testing and single-request caching. `Melodic\Cache\CacheInterface` is PSR-16-*style* — it does not extend `Psr\SimpleCache\CacheInterface`, and TTLs are plain integer seconds.

## CacheInterface

| Method | Description |
|---|---|
| `get(string $key, mixed $default = null): mixed` | Retrieve a value, or `$default` if missing/expired |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | Store a value with optional TTL in seconds (`null` = never expires) |
| `delete(string $key): bool` | Remove a value (returns `true` if the key did not exist) |
| `has(string $key): bool` | Check if a key exists and is not expired |
| `clear(): bool` | Remove all cached values |

**TTL semantics (both drivers):** `null` stores the value with no expiry. A zero or negative TTL means "already expired": nothing is stored, any existing entry for the key is deleted, and `set()` returns `true`.

`get()` returns `$default` for a missing key, so a cached `null` is indistinguishable from a miss — use `has()` if you need to tell them apart.

## FileCache

Stores serialized values as files in a directory. Each entry is a file named `md5($key).cache`. Expired entries are deleted when they are next read.

```php
use Melodic\Cache\FileCache;

$cache = new FileCache('/path/to/cache');

$cache->set('user:42', $userData);                  // no expiration
$cache->set('token:abc', $tokenData, ttl: 3600);    // expires in 1 hour

$user = $cache->get('user:42');                      // returns cached data
$missing = $cache->get('nonexistent', 'default');    // returns 'default'

$cache->has('user:42');   // true
$cache->delete('user:42');
$cache->clear();          // removes every cache entry in the directory
```

Behavior worth knowing:

- **Directory permissions** — the cache directory is created automatically (recursively) with mode `0700`. A directory that already exists with group/other permission bits is tightened to `0700` (4.0). If the directory cannot be created or restricted, the constructor throws a `RuntimeException`.
- **File permissions** — entry files are written `0600`.
- **Atomic writes** — each entry is written to a temporary file and renamed into place, so a concurrent reader never sees a half-written entry.
- **Corruption-safe reads** — an unreadable or corrupt entry is treated as a miss and deleted.
- **Lazy expiry** — an expired entry is removed only when it is read (`get()`/`has()`). Entries that are never read again stay on disk until `clear()`.
- **Scoped `clear()`** — only the cache's own `*.cache` files, plus temporary files orphaned by an interrupted write, are removed. Other files in the directory are left alone.
- **Serialization** — values are stored with `serialize()` and restored with `unserialize()`, so they must be serializable (no closures, PDO handles, or other resources). Because cached objects are deserialized on read, the directory is kept private; never point `FileCache` at a directory other users or processes can write to.

## ArrayCache

In-memory cache for testing or single-request use. Same interface and TTL semantics, no filesystem I/O. Values are kept as-is (not serialized or copied — a cached object is returned by handle) and are lost when the `ArrayCache` instance goes away.

```php
use Melodic\Cache\ArrayCache;

$cache = new ArrayCache();
$cache->set('key', 'value', ttl: 60);
$cache->get('key'); // 'value'
```

## PSR-16 Caches

`Melodic\Cache\Psr16CacheAdapter` (since 4.1) wraps any PSR-16 cache (Redis, Memcached, APCu via `symfony/cache`, ...) as a Melodic `CacheInterface`. It needs the `psr/simple-cache` package, which any PSR-16 cache already requires:

```php
use Melodic\Cache\CacheInterface;
use Melodic\Cache\Psr16CacheAdapter;

$container->singleton(CacheInterface::class, fn() => new Psr16CacheAdapter($psr16Cache));
```

Keys are passed through as-is unless they are empty, longer than 64 characters, or contain a character PSR-16 reserves (`{}()/\@:`); those are replaced by `melodic.` plus a SHA-1 hash of the key. TTL handling and `clear()` are delegated to the wrapped cache, so the `FileCache` specifics above do not apply.

## Service Provider

Register caching in the DI container:

```php
use Melodic\Cache\CacheServiceProvider;

$app->register(new CacheServiceProvider($app->getBasePath() . '/storage/cache'));
```

The cache directory is a required constructor argument — the provider does not read it from configuration. It binds `CacheInterface` to a `FileCache` singleton, created (along with its directory) the first time it is resolved. Inject `CacheInterface` in any service or controller:

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

## Rate Limiting

`Melodic\Http\Middleware\RateLimitMiddleware` (since 4.1) keeps its counters in a `CacheInterface`, so it needs a cache that persists between requests — `FileCache` or a PSR-16 adapter, not `ArrayCache`:

```php
// 10 attempts per minute per client on the login callback
$app->addMiddleware(new RateLimitMiddleware($cache, 10, 60, paths: ['/auth/callback']));
```

## Clearing the Cache from the CLI

`Melodic\Console\CacheClearCommand` (`cache:clear`) calls `CacheInterface::clear()`. It is not registered in `vendor/bin/melodic`; add it to your application's own `bin/console` with the cache instance it should clear:

```php
$console->register(new CacheClearCommand($app->getContainer()->get(CacheInterface::class)));
```

See [Console](console.md#built-in-commands).

## View Caching

The `ViewEngine` accepts an optional `CacheInterface` and provides `renderCached()` for caching rendered templates:

```php
$engine = new ViewEngine('/path/to/views', $cache);

// Cache the rendered output for 1 hour
$html = $engine->renderCached('home/index', ['name' => 'World'], 'layouts/main', ttl: 3600);
```

Cache keys are derived from the template name, layout, and a hash of the serialized data array (`view:{template}:{layout}:{md5(serialize($data))}`), so different data produces different cache entries — and the data must be serializable. The `ttl` defaults to 3600 seconds. If the engine was constructed without a cache, `renderCached()` simply renders the template every time.

`MvcController::view()` always calls `render()`; to serve cached output, call `renderCached()` on the `ViewEngine` yourself.

`Application` registers a default `ViewEngine` (templates in `{basePath}/views`, or the `views.path` config key; since 4.1). It is constructed with the container's `CacheInterface` when one has been explicitly bound — e.g. by `CacheServiceProvider` — so `renderCached()` works on the default engine without building it by hand.

See [Views](../README.md#views) for more on the template engine.
