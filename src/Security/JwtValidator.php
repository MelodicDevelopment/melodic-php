<?php

declare(strict_types=1);

namespace Melodic\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtValidator
{
    public function __construct(
        private readonly AuthProviderRegistry $registry,
        private readonly ?LocalAuthConfig $localAuthConfig = null,
    ) {
    }

    public function validate(string $token): array
    {
        $issuer = $this->peekIssuer($token);

        if ($this->localAuthConfig !== null && $issuer === $this->localAuthConfig->issuer) {
            return $this->validateLocal($token);
        }

        return $this->validateExternal($token);
    }

    private function validateLocal(string $token): array
    {
        try {
            $key = new Key($this->localAuthConfig->signingKey, $this->localAuthConfig->algorithm);
            $claims = (array) JWT::decode($token, $key);
        } catch (\Exception $e) {
            throw new SecurityException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        $tokenAudience = $claims['aud'] ?? null;

        if (is_array($tokenAudience)) {
            if (!in_array($this->localAuthConfig->audience, $tokenAudience, true)) {
                throw new SecurityException('Invalid token audience.');
            }
        } elseif ($tokenAudience !== $this->localAuthConfig->audience) {
            throw new SecurityException('Invalid token audience.');
        }

        return $claims;
    }

    private function validateExternal(string $token): array
    {
        $oidcProviders = $this->registry->getByType(AuthProviderType::Oidc);
        $lastException = null;

        foreach ($oidcProviders as $provider) {
            if (!$provider instanceof OidcAuthProvider) {
                continue;
            }

            try {
                return $provider->validateToken($token);
            } catch (SecurityException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new SecurityException('No OIDC provider could validate the token.');
    }

    private function peekIssuer(string $token): ?string
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), true);

        if ($payload === false) {
            return null;
        }

        $decoded = json_decode($payload, true);

        if (!is_array($decoded)) {
            return null;
        }

        return $decoded['iss'] ?? null;
    }
}
