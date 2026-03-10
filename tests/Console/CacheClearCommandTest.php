<?php

declare(strict_types=1);

namespace Tests\Console;

use Melodic\Cache\ArrayCache;
use Melodic\Cache\CacheInterface;
use Melodic\Console\CacheClearCommand;
use PHPUnit\Framework\TestCase;

final class CacheClearCommandTest extends TestCase
{
    public function testGetNameReturnsCacheClear(): void
    {
        $command = new CacheClearCommand(new ArrayCache());

        $this->assertSame('cache:clear', $command->getName());
    }

    public function testGetDescriptionReturnsClearTheApplicationCache(): void
    {
        $command = new CacheClearCommand(new ArrayCache());

        $this->assertSame('Clear the application cache', $command->getDescription());
    }

    public function testExecuteClearsCacheAndOutputsSuccessMessage(): void
    {
        $cache = new ArrayCache();
        $cache->set('key1', 'value1');
        $cache->set('key2', 'value2');

        $command = new CacheClearCommand($cache);

        ob_start();
        $exitCode = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertSame("Cache cleared successfully.\n", $output);
        $this->assertFalse($cache->has('key1'));
        $this->assertFalse($cache->has('key2'));
    }

    public function testExecuteReturnsOneWhenClearFails(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('clear')->willReturn(false);

        $command = new CacheClearCommand($cache);

        $exitCode = $command->execute([]);

        $this->assertSame(1, $exitCode);
    }
}
