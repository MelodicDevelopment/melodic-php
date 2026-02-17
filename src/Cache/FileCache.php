<?php

declare(strict_types=1);

namespace Melodic\Cache;

class FileCache implements CacheInterface
{
    public function __construct(
        private readonly string $cacheDir
    ) {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return $default;
        }

        $entry = unserialize(file_get_contents($path));

        if ($entry['expires'] !== null && $entry['expires'] <= time()) {
            unlink($path);
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $entry = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];

        return file_put_contents($this->path($key), serialize($entry)) !== false;
    }

    public function delete(string $key): bool
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function has(string $key): bool
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return false;
        }

        $entry = unserialize(file_get_contents($path));

        if ($entry['expires'] !== null && $entry['expires'] <= time()) {
            unlink($path);
            return false;
        }

        return true;
    }

    public function clear(): bool
    {
        $files = glob($this->cacheDir . '/*');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    private function path(string $key): string
    {
        return $this->cacheDir . '/' . md5($key);
    }
}
