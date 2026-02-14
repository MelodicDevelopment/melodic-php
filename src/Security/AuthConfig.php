<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthConfig
{
    public function __construct(
        public readonly bool $apiAuthEnabled = true,
        public readonly bool $webAuthEnabled = true,
        public readonly string $discoveryUrl = '',
        public readonly string $audience = '',
        public readonly string $clientId = '',
        public readonly string $redirectUri = '',
        public readonly string $loginPath = '/auth/login',
        public readonly string $callbackPath = '/auth/callback',
        public readonly string $postLoginRedirect = '/',
        public readonly string $cookieName = 'melodic_auth',
        public readonly int $cookieLifetime = 3600,
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            apiAuthEnabled: (bool) ($config['api']['enabled'] ?? true),
            webAuthEnabled: (bool) ($config['web']['enabled'] ?? true),
            discoveryUrl: (string) ($config['oidc']['discoveryUrl'] ?? ''),
            audience: (string) ($config['oidc']['audience'] ?? ''),
            clientId: (string) ($config['oidc']['clientId'] ?? ''),
            redirectUri: (string) ($config['redirectUri'] ?? ''),
            loginPath: (string) ($config['loginPath'] ?? '/auth/login'),
            callbackPath: (string) ($config['callbackPath'] ?? '/auth/callback'),
            postLoginRedirect: (string) ($config['postLoginRedirect'] ?? '/'),
            cookieName: (string) ($config['cookieName'] ?? 'melodic_auth'),
            cookieLifetime: (int) ($config['cookieLifetime'] ?? 3600),
        );
    }
}
