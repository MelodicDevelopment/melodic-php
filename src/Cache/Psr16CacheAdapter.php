<?php

declare(strict_types=1);

namespace Melodic\Cache;

/**
 * Use any PSR-16 cache (Redis, Memcached, APCu via symfony/cache, ...) as the
 * framework's cache:
 *
 *     $container->singleton(CacheInterface::class,
 *         fn() => new Psr16CacheAdapter($psr16Cache));
 *
 * Requires the `psr/simple-cache` package (installed with any PSR-16 cache).
 * Keys are hashed when they contain characters PSR-16 reserves ({}()/\@:),
 * since the framework's own keys use ":" as a separator.
 */
class Psr16CacheAdapter implements CacheInterface
{
    public function __construct(
        private readonly \Psr\SimpleCache\CacheInterface $cache,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($key), $default);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->cache->set($this->key($key), $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->cache->delete($this->key($key));
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->key($key));
    }

    public function clear(): bool
    {
        return $this->cache->clear();
    }

    private function key(string $key): string
    {
        return $key !== '' && strlen($key) <= 64 && strpbrk($key, '{}()/\\@:') === false
            ? $key
            : 'melodic.' . sha1($key);
    }
}
