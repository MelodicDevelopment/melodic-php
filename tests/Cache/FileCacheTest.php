<?php

declare(strict_types=1);

namespace Tests\Cache;

use Melodic\Cache\FileCache;
use PHPUnit\Framework\TestCase;

class FileCacheTest extends TestCase
{
    private string $cacheDir;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/melodic_cache_test_' . uniqid();
        mkdir($this->cacheDir, 0775, true);
        $this->cache = new FileCache($this->cacheDir);
    }

    protected function tearDown(): void
    {
        $files = glob($this->cacheDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        if (is_dir($this->cacheDir)) {
            rmdir($this->cacheDir);
        }
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertNull($this->cache->get('missing'));
        $this->assertSame('fallback', $this->cache->get('missing', 'fallback'));
    }

    public function testSetAndGetValue(): void
    {
        $this->assertTrue($this->cache->set('key', 'value'));
        $this->assertSame('value', $this->cache->get('key'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->cache->set('key', 'first');
        $this->cache->set('key', 'second');

        $this->assertSame('second', $this->cache->get('key'));
    }

    public function testSetWithTtlExpiration(): void
    {
        $this->cache->set('key', 'value', 1);
        $this->assertSame('value', $this->cache->get('key'));

        sleep(2);

        $this->assertNull($this->cache->get('key'));
    }

    public function testSetWithNullTtlNeverExpires(): void
    {
        $this->cache->set('key', 'value', null);

        $this->assertSame('value', $this->cache->get('key'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->cache->set('key', 'value');
        $this->assertTrue($this->cache->delete('key'));
        $this->assertNull($this->cache->get('key'));
    }

    public function testDeleteReturnsTrueForMissingKey(): void
    {
        $this->assertTrue($this->cache->delete('nonexistent'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->cache->set('key', 'value');

        $this->assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->cache->has('missing'));
    }

    public function testHasReturnsFalseForExpiredKey(): void
    {
        $this->cache->set('key', 'value', 1);

        sleep(2);

        $this->assertFalse($this->cache->has('key'));
    }

    public function testClearRemovesAllKeys(): void
    {
        $this->cache->set('a', 1);
        $this->cache->set('b', 2);
        $this->cache->set('c', 3);

        $this->assertTrue($this->cache->clear());

        $this->assertNull($this->cache->get('a'));
        $this->assertNull($this->cache->get('b'));
        $this->assertNull($this->cache->get('c'));
    }

    public function testStoresVariousTypes(): void
    {
        $this->cache->set('int', 42);
        $this->cache->set('float', 3.14);
        $this->cache->set('bool', true);
        $this->cache->set('array', ['a', 'b']);

        $this->assertSame(42, $this->cache->get('int'));
        $this->assertSame(3.14, $this->cache->get('float'));
        $this->assertTrue($this->cache->get('bool'));
        $this->assertSame(['a', 'b'], $this->cache->get('array'));
    }

    public function testCreatesCacheDirIfNotExists(): void
    {
        $newDir = $this->cacheDir . '/nested/subdir';
        $cache = new FileCache($newDir);

        $this->assertDirectoryExists($newDir);

        // Clean up nested dirs
        rmdir($newDir);
        rmdir($this->cacheDir . '/nested');
    }

    public function testKeySanitization(): void
    {
        $this->cache->set('key with spaces/and:special!chars', 'value');

        $this->assertSame('value', $this->cache->get('key with spaces/and:special!chars'));
    }

    public function testCorruptFileIsTreatedAsMissAndPruned(): void
    {
        $path = $this->cacheDir . '/' . md5('corrupt') . '.cache';
        file_put_contents($path, 'this is not a serialized entry');

        $this->assertSame('default', $this->cache->get('corrupt', 'default'));
        $this->assertFalse($this->cache->has('corrupt'));
        $this->assertFileDoesNotExist($path);
    }

    public function testClearOnlyRemovesCacheFiles(): void
    {
        $this->cache->set('keep-me-cached', 'value');
        $unrelated = $this->cacheDir . '/important.txt';
        file_put_contents($unrelated, 'do not delete');

        $this->assertTrue($this->cache->clear());

        $this->assertNull($this->cache->get('keep-me-cached'));
        $this->assertFileExists($unrelated);

        unlink($unrelated);
    }

    public function testNonPositiveTtlDoesNotStore(): void
    {
        $this->assertTrue($this->cache->set('ephemeral', 'value', 0));

        $this->assertNull($this->cache->get('ephemeral'));
        $this->assertFalse($this->cache->has('ephemeral'));
    }

    public function testNonPositiveTtlClearsExistingEntry(): void
    {
        $this->cache->set('replaceme', 'old');
        $this->cache->set('replaceme', 'new', -5);

        $this->assertNull($this->cache->get('replaceme'));
    }

    public function testTightensPermissionsOfExistingDirectory(): void
    {
        $dir = $this->cacheDir . '/loose';
        mkdir($dir, 0755);
        chmod($dir, 0755); // mkdir mode is masked by umask; force the loose perms

        new FileCache($dir);

        clearstatcache(true, $dir);
        $this->assertSame(0700, fileperms($dir) & 0777);

        rmdir($dir);
    }

    public function testCacheFilesAreOwnerOnly(): void
    {
        $this->cache->set('secret', 'value');

        $path = $this->cacheDir . '/' . md5('secret') . '.cache';
        clearstatcache(true, $path);
        $this->assertSame(0600, fileperms($path) & 0777);
    }

    public function testClearSweepsOrphanedTempFiles(): void
    {
        $orphan = $this->cacheDir . '/' . md5('crashed') . '.cache.1234.tmp';
        file_put_contents($orphan, 'partial write');

        $this->assertTrue($this->cache->clear());

        $this->assertFileDoesNotExist($orphan);
    }
}
