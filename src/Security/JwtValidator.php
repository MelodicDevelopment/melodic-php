<?php

declare(strict_types=1);

namespace Melodic\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

class JwtValidator
{
    public function __construct(
        private readonly OidcProvider $provider,
        private readonly ?string $audience = null,
    ) {
    }

    public function validate(string $token): array
    {
        try {
            $jwks = $this->provider->getJwks();
            $keys = JWK::parseKeySet($jwks);
            $claims = (array) JWT::decode($token, $keys);
        } catch (SecurityException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new SecurityException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        if ($this->audience !== null) {
            $tokenAudience = $claims['aud'] ?? null;

            if (is_array($tokenAudience)) {
                if (!in_array($this->audience, $tokenAudience, true)) {
                    throw new SecurityException('Invalid token audience.');
                }
            } elseif ($tokenAudience !== $this->audience) {
                throw new SecurityException('Invalid token audience.');
            }
        }

        return $claims;
    }
}
