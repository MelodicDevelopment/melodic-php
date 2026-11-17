<?php

declare(strict_types=1);

namespace Melodic\Security;

use Firebase\JWT\JWT;

/**
 * Mints the application's own session JWT from a set of user claims. Shared
 * by the local and OAuth2 providers (which previously each carried a copy).
 *
 * @internal
 */
final class LocalJwtIssuer
{
    /** @param array<string, mixed> $claims */
    public static function issue(LocalAuthConfig $config, array $claims, string $providerName): string
    {
        $now = time();

        $payload = [
            'iss' => $config->issuer,
            'aud' => $config->audience,
            'iat' => $now,
            'exp' => $now + $config->tokenLifetime,
            'sub' => $claims['sub'] ?? '',
            'username' => $claims['username'] ?? '',
            'email' => $claims['email'] ?? '',
            'entitlements' => $claims['entitlements'] ?? [],
            'provider' => $claims['provider'] ?? $providerName,
        ];

        return JWT::encode($payload, $config->signingKey, $config->algorithm);
    }
}
