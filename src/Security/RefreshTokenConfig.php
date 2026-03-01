<?php

declare(strict_types=1);

namespace Melodic\Security;

class RefreshTokenConfig
{
    public function __construct(
        public readonly int $tokenLifetime = 604800,
        public readonly string $cookieName = 'kingdom_refresh',
        public readonly string $cookieDomain = '',
        public readonly string $cookiePath = '/auth/refresh',
        public readonly bool $cookieSecure = true,
        public readonly string $cookieSameSite = 'Lax',
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            tokenLifetime: (int) ($config['tokenLifetime'] ?? 604800),
            cookieName: (string) ($config['cookieName'] ?? 'kingdom_refresh'),
            cookieDomain: (string) ($config['cookieDomain'] ?? ''),
            cookiePath: (string) ($config['cookiePath'] ?? '/auth/refresh'),
            cookieSecure: (bool) ($config['cookieSecure'] ?? true),
            cookieSameSite: (string) ($config['cookieSameSite'] ?? 'Lax'),
        );
    }
}
