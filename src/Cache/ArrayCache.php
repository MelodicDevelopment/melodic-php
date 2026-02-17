<?php

declare(strict_types=1);

namespace Melodic\Cache;

class ArrayCache implements CacheInterface
{
    private array $cache = [];

    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->has($key)) {
            return $default;
        }

        return $this->cache[$key]['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $this->cache[$key] = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->cache[$key]);

        return true;
    }

    public function has(string $key): bool
    {
        if (!isset($this->cache[$key])) {
            return false;
        }

        $entry = $this->cache[$key];

        if ($entry['expires'] !== null && $entry['expires'] <= time()) {
            unset($this->cache[$key]);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $this->cache = [];

        return true;
    }
}
