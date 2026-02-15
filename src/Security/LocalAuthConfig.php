<?php

declare(strict_types=1);

namespace Melodic\Security;

class LocalAuthConfig
{
    public function __construct(
        public readonly string $signingKey,
        public readonly string $issuer = 'melodic-app',
        public readonly string $audience = 'melodic-app',
        public readonly int $tokenLifetime = 3600,
        public readonly string $algorithm = 'HS256',
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            signingKey: (string) ($config['signingKey'] ?? ''),
            issuer: (string) ($config['issuer'] ?? 'melodic-app'),
            audience: (string) ($config['audience'] ?? 'melodic-app'),
            tokenLifetime: (int) ($config['tokenLifetime'] ?? 3600),
            algorithm: (string) ($config['algorithm'] ?? 'HS256'),
        );
    }
}
