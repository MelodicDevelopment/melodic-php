<?php

declare(strict_types=1);

namespace Melodic\Cache;

class FileCache implements CacheInterface
{
    /** File extension used for cache entries so clear() only touches our files. */
    private const EXTENSION = '.cache';

    public function __construct(
        private readonly string $cacheDir
    ) {
        // 0700: the cache may hold serialized objects, which are deserialized on
        // read. A world-writable cache dir would let another local user plant a
        // crafted payload (object-injection), so the directory is kept private.
        if (!is_dir($this->cacheDir) && !mkdir($this->cacheDir, 0700, true) && !is_dir($this->cacheDir)) {
            throw new \RuntimeException("Unable to create cache directory: {$this->cacheDir}");
        }

        // An existing directory (the normal deploy case) may have been created
        // with looser permissions than mkdir() above would use — tighten it so
        // the guarantee holds regardless of who created the directory.
        clearstatcache(true, $this->cacheDir);
        $perms = fileperms($this->cacheDir);

        if ($perms !== false && ($perms & 0077) !== 0 && !chmod($this->cacheDir, 0700)) {
            throw new \RuntimeException("Unable to restrict cache directory permissions: {$this->cacheDir}");
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $entry = $this->readEntry($this->path($key));

        if ($entry === null) {
            return $default;
        }

        return $entry['value'];
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        // PSR-16 semantics: a non-positive TTL means "already expired" — store
        // nothing and clear any existing entry rather than writing a dead file.
        if ($ttl !== null && $ttl <= 0) {
            $this->delete($key);

            return true;
        }

        $entry = [
            'value' => $value,
            'expires' => $ttl !== null ? time() + $ttl : null,
        ];

        return $this->writeAtomic($this->path($key), serialize($entry));
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
        return $this->readEntry($this->path($key)) !== null;
    }

    public function clear(): bool
    {
        $entries = glob($this->cacheDir . '/*' . self::EXTENSION);
        // Also sweep temp files orphaned by a crash between write and rename.
        $orphans = glob($this->cacheDir . '/*' . self::EXTENSION . '.*.tmp');

        if ($entries === false || $orphans === false) {
            return false;
        }

        foreach ([...$entries, ...$orphans] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        return true;
    }

    /**
     * Read and validate a cache entry, returning null on a miss, an expired
     * entry, or a corrupt/partially-written file (which is also pruned).
     *
     * @return array{value: mixed, expires: int|null}|null
     */
    private function readEntry(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return null;
        }

        // Suppress notices: a corrupt or partially-written file is an expected
        // condition we handle below by treating the entry as a miss.
        $entry = @unserialize($contents);

        // Guard against a partially-written file or a corrupt payload: a valid
        // entry is always an array carrying both 'value' and 'expires'.
        if (!is_array($entry) || !array_key_exists('value', $entry) || !array_key_exists('expires', $entry)) {
            unlink($path);

            return null;
        }

        if ($entry['expires'] !== null && $entry['expires'] <= time()) {
            unlink($path);

            return null;
        }

        return $entry;
    }

    /**
     * Write the payload to a temp file then atomically rename it into place, so
     * a concurrent reader never observes a half-written cache file.
     */
    private function writeAtomic(string $path, string $contents): bool
    {
        $tmp = $path . '.' . getmypid() . '.tmp';

        if (file_put_contents($tmp, $contents, LOCK_EX) === false) {
            return false;
        }

        // Entries are unserialized on read, so keep them owner-only: a
        // world-readable file could leak cached secrets, and loosening to
        // world-writable would enable object-injection via a planted payload.
        if (!chmod($tmp, 0600)) {
            @unlink($tmp);

            return false;
        }

        if (!rename($tmp, $path)) {
            @unlink($tmp);

            return false;
        }

        return true;
    }

    private function path(string $key): string
    {
        return $this->cacheDir . '/' . md5($key) . self::EXTENSION;
    }
}
