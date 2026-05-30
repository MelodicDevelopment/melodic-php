<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthProviderConfig
{
    public function __construct(
        public readonly string $name,
        public readonly AuthProviderType $type,
        public readonly string $label = '',
        public readonly string $discoveryUrl = '',
        public readonly string $authorizeUrl = '',
        public readonly string $tokenUrl = '',
        public readonly string $userInfoUrl = '',
        public readonly string $clientId = '',
        public readonly string $clientSecret = '',
        public readonly string $redirectUri = '',
        public readonly string $audience = '',
        public readonly string $scopes = '',
        /** @var array<string, string> */
        public readonly array $claimMap = [],
    ) {
    }

    /** @param array<string, mixed> $config */
    public static function fromArray(string $name, array $config): self
    {
        return new self(
            name: $name,
            type: AuthProviderType::from($config['type'] ?? 'oidc'),
            label: (string) ($config['label'] ?? ''),
            discoveryUrl: (string) ($config['discoveryUrl'] ?? ''),
            authorizeUrl: (string) ($config['authorizeUrl'] ?? ''),
            tokenUrl: (string) ($config['tokenUrl'] ?? ''),
            userInfoUrl: (string) ($config['userInfoUrl'] ?? ''),
            clientId: (string) ($config['clientId'] ?? ''),
            clientSecret: (string) ($config['clientSecret'] ?? ''),
            redirectUri: (string) ($config['redirectUri'] ?? ''),
            audience: (string) ($config['audience'] ?? ''),
            scopes: (string) ($config['scopes'] ?? ''),
            claimMap: (array) ($config['claimMap'] ?? []),
        );
    }
}
