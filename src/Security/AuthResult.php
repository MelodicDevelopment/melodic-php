<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthResult
{
    public function __construct(
        public readonly string $token,
        public readonly array $claims,
        public readonly string $providerName,
    ) {
    }
}
