<?php

declare(strict_types=1);

namespace Melodic\Security;

class LocalAuthConfig
{
    /**
     * Minimum signing-key length for HMAC algorithms. HS256 is keyed by a shared
     * secret; a short or empty secret lets an attacker forge fully-trusted local
     * tokens, so we require at least 256 bits (32 bytes) of key material.
     */
    private const MIN_HMAC_KEY_LENGTH = 32;

    public function __construct(
        public readonly string $signingKey,
        public readonly string $issuer = 'melodic-app',
        public readonly string $audience = 'melodic-app',
        public readonly int $tokenLifetime = 3600,
        public readonly string $algorithm = 'HS256',
    ) {
        if (str_starts_with($this->algorithm, 'HS') && strlen($this->signingKey) < self::MIN_HMAC_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Local auth signing key must be at least %d characters for %s. '
                . 'Generate a strong secret (e.g. `openssl rand -base64 48`) and set it in config.',
                self::MIN_HMAC_KEY_LENGTH,
                $this->algorithm,
            ));
        }
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
