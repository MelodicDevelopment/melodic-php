<?php

declare(strict_types=1);

namespace Melodic\DI;

abstract class ServiceProvider
{
    abstract public function register(Container $container): void;

    public function boot(Container $container): void
    {
        // Optional hook for post-registration logic
    }
}
