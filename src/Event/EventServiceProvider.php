<?php

declare(strict_types=1);

namespace Melodic\Event;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(EventDispatcherInterface::class, EventDispatcher::class);
    }
}
