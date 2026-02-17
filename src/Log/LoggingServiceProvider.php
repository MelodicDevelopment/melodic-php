<?php

declare(strict_types=1);

namespace Melodic\Log;

use Melodic\Core\Application;
use Melodic\Core\Configuration;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(LoggerInterface::class, function (Container $c) {
            /** @var Configuration $config */
            $config = $c->get(Configuration::class);

            /** @var Application $app */
            $app = $c->get(Application::class);

            $path = $config->get('logging.path') ?? $app->getBasePath() . '/logs';
            $level = LogLevel::parse($config->get('logging.level', 'debug'));

            return new FileLogger($path, $level);
        });
    }
}
