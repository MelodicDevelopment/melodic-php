<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthConfig
{
    /** @var array<string, AuthProviderConfig> */
    private array $providers = [];
    private ?LocalAuthConfig $localAuth = null;

    public function __construct(
        public readonly bool $apiAuthEnabled = true,
        public readonly bool $webAuthEnabled = true,
        public readonly string $loginPath = '/auth/login',
        public readonly string $callbackPath = '/auth/callback',
        public readonly string $postLoginRedirect = '/',
        public readonly string $cookieName = 'melodic_auth',
        public readonly int $cookieLifetime = 3600,
    ) {
    }

    public static function fromArray(array $config): self
    {
        $instance = new self(
            apiAuthEnabled: (bool) ($config['api']['enabled'] ?? true),
            webAuthEnabled: (bool) ($config['web']['enabled'] ?? true),
            loginPath: (string) ($config['loginPath'] ?? '/auth/login'),
            callbackPath: (string) ($config['callbackPath'] ?? '/auth/callback'),
            postLoginRedirect: (string) ($config['postLoginRedirect'] ?? '/'),
            cookieName: (string) ($config['cookieName'] ?? 'melodic_auth'),
            cookieLifetime: (int) ($config['cookieLifetime'] ?? 3600),
        );

        if (isset($config['local']) && is_array($config['local'])) {
            $instance->localAuth = LocalAuthConfig::fromArray($config['local']);
        }

        $providers = $config['providers'] ?? [];

        foreach ($providers as $name => $providerConfig) {
            $instance->providers[$name] = AuthProviderConfig::fromArray($name, $providerConfig);
        }

        return $instance;
    }

    public function getLocalAuth(): ?LocalAuthConfig
    {
        return $this->localAuth;
    }

    public function getProvider(string $name): ?AuthProviderConfig
    {
        return $this->providers[$name] ?? null;
    }

    /** @return array<string, AuthProviderConfig> */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /** @return array<string, AuthProviderConfig> */
    public function getExternalProviders(): array
    {
        return array_filter(
            $this->providers,
            fn(AuthProviderConfig $p) => $p->type !== AuthProviderType::Local,
        );
    }

    public function getLocalProvider(): ?AuthProviderConfig
    {
        foreach ($this->providers as $provider) {
            if ($provider->type === AuthProviderType::Local) {
                return $provider;
            }
        }

        return null;
    }
}
