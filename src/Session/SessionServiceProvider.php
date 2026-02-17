<?php

declare(strict_types=1);

namespace Melodic\Session;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(SessionInterface::class, NativeSession::class);
    }
}
