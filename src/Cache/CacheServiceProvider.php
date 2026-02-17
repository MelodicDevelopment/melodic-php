<?php

declare(strict_types=1);

namespace Melodic\Cache;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class CacheServiceProvider extends ServiceProvider
{
    public function __construct(
        private readonly string $cacheDir
    ) {}

    public function register(Container $container): void
    {
        $container->singleton(CacheInterface::class, fn() => new FileCache($this->cacheDir));
    }
}
