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

        $container->singleton(SessionManager::class, function () {
            return new SessionManager();
        });

        $container->singleton(AuthProviderRegistry::class, function (Container $c) {
            /** @var AuthConfig $authConfig */
            $authConfig = $c->get(AuthConfig::class);
            $registry = new AuthProviderRegistry();

            $cacheDir = sys_get_temp_dir() . '/melodic_oidc_cache';
            $localAuthConfig = $authConfig->getLocalAuth();

            foreach ($authConfig->getProviders() as $name => $providerConfig) {
                $provider = match ($providerConfig->type) {
                    AuthProviderType::Oidc => new OidcAuthProvider($providerConfig, $cacheDir),
                    AuthProviderType::OAuth2 => new OAuth2AuthProvider(
                        $providerConfig,
                        $localAuthConfig ?? throw new SecurityException(
                            'OAuth2 providers require a "local" signing config in the auth section.'
                        ),
                        new ClaimMapper($providerConfig->claimMap),
                    ),
                    AuthProviderType::Local => new LocalAuthProvider(
                        $providerConfig,
                        $localAuthConfig ?? throw new SecurityException(
                            'Local provider requires a "local" signing config in the auth section.'
                        ),
                        $c->get(LocalAuthenticatorInterface::class),
                    ),
                };

                $registry->register($provider);
            }

            return $registry;
        });

        $container->singleton(JwtValidator::class, function (Container $c) {
            /** @var AuthConfig $authConfig */
            $authConfig = $c->get(AuthConfig::class);
            /** @var AuthProviderRegistry $registry */
            $registry = $c->get(AuthProviderRegistry::class);

            return new JwtValidator($registry, $authConfig->getLocalAuth());
        });

        $container->singleton(AuthLoginRendererInterface::class, function (Container $c) {
            return new AuthLoginRenderer(
                $c->get(AuthConfig::class),
                $c->get(AuthProviderRegistry::class),
            );
        });

        $container->singleton(AuthCallbackMiddleware::class, function (Container $c) {
            return new AuthCallbackMiddleware(
                $c->get(AuthConfig::class),
                $c->get(AuthProviderRegistry::class),
                $c->get(SessionManager::class),
                $c->get(AuthLoginRendererInterface::class),
            );
        });

        $container->singleton(RefreshTokenConfig::class, function (Container $c) {
            /** @var Configuration $config */
            $config = $c->get(Configuration::class);

            return RefreshTokenConfig::fromArray($config->get('auth.refreshToken', []));
        });

        $container->singleton(RefreshTokenService::class, function (Container $c) {
            return new RefreshTokenService(
                $c->get(RefreshTokenRepositoryInterface::class),
                $c->get(RefreshTokenConfig::class),
            );
        });

        $container->singleton(RefreshTokenMiddleware::class, function (Container $c) {
            return new RefreshTokenMiddleware(
                $c->get(RefreshTokenService::class),
                $c->get(RefreshTokenConfig::class),
            );
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
    }
}
