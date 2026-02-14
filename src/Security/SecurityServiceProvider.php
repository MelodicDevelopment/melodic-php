<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Core\Configuration;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;

class SecurityServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->singleton(AuthConfig::class, function (Container $c) {
            /** @var Configuration $config */
            $config = $c->get(Configuration::class);

            return AuthConfig::fromArray($config->get('auth', []));
        });

        $container->singleton(OidcProvider::class, function (Container $c) {
            /** @var AuthConfig $config */
            $config = $c->get(AuthConfig::class);

            // Use system temp directory for cache
            $cacheDir = sys_get_temp_dir() . '/melodic_oidc_cache';

            return new OidcProvider($config->discoveryUrl, $cacheDir);
        });

        $container->singleton(JwtValidator::class, function (Container $c) {
            /** @var AuthConfig $config */
            $config = $c->get(AuthConfig::class);
            /** @var OidcProvider $provider */
            $provider = $c->get(OidcProvider::class);

            return new JwtValidator($provider, $config->audience ?: null);
        });

        $container->singleton(OAuthClient::class, function (Container $c) {
            return new OAuthClient(
                $c->get(OidcProvider::class),
                $c->get(AuthConfig::class),
            );
        });

        $container->singleton(SessionManager::class, function () {
            return new SessionManager();
        });

        $container->singleton(ApiAuthenticationMiddleware::class, function (Container $c) {
            return new ApiAuthenticationMiddleware(
                $c->get(AuthConfig::class),
                $c->get(JwtValidator::class),
            );
        });

        $container->singleton(WebAuthenticationMiddleware::class, function (Container $c) {
            return new WebAuthenticationMiddleware(
                $c->get(AuthConfig::class),
                $c->get(JwtValidator::class),
                $c->get(SessionManager::class),
            );
        });

        $container->singleton(OAuthCallbackMiddleware::class, function (Container $c) {
            return new OAuthCallbackMiddleware(
                $c->get(AuthConfig::class),
                $c->get(OAuthClient::class),
                $c->get(JwtValidator::class),
                $c->get(SessionManager::class),
            );
        });
    }
}
