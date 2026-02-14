<?php

declare(strict_types=1);

namespace Melodic\Security;

class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $email,
        public readonly array $entitlements = [],
    ) {
    }

    public function hasEntitlement(string $entitlement): bool
    {
        return in_array($entitlement, $this->entitlements, true);
    }

    public function hasAnyEntitlement(string ...$entitlements): bool
    {
        foreach ($entitlements as $entitlement) {
            if ($this->hasEntitlement($entitlement)) {
                return true;
            }
        }

        return false;
    }
}
