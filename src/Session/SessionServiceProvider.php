<?php

declare(strict_types=1);

namespace Melodic\Session;

use Melodic\Core\Configuration;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class SessionServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        // Honors the same `session.*` config keys as the auth SessionManager
        // (previously the cookie settings were ignored here and could not be
        // relaxed for plain-HTTP local development).
        $container->singleton(SessionInterface::class, function (Container $c): SessionInterface {
            /** @var Configuration $config */
            $config = $c->get(Configuration::class);

            return new NativeSession(
                cookieSecure: (bool) $config->get('session.cookieSecure', true),
                cookieSameSite: (string) $config->get('session.cookieSameSite', 'Lax'),
                cookiePath: (string) $config->get('session.cookiePath', '/'),
                cookieDomain: (string) $config->get('session.cookieDomain', ''),
            );
        });
    }
}
