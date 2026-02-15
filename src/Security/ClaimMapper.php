<?php

declare(strict_types=1);

namespace Melodic\Security;

class ClaimMapper
{
    public function __construct(
        private readonly array $claimMap = [],
    ) {
    }

    public function map(array $rawClaims): array
    {
        $mapped = [];

        $subKey = $this->claimMap['sub'] ?? 'sub';
        $usernameKey = $this->claimMap['username'] ?? 'username';
        $emailKey = $this->claimMap['email'] ?? 'email';
        $entitlementsKey = $this->claimMap['entitlements'] ?? 'entitlements';

        $mapped['sub'] = (string) ($rawClaims[$subKey] ?? '');
        $mapped['username'] = (string) ($rawClaims[$usernameKey] ?? $rawClaims['preferred_username'] ?? '');
        $mapped['email'] = (string) ($rawClaims[$emailKey] ?? '');
        $mapped['entitlements'] = (array) ($rawClaims[$entitlementsKey] ?? []);

        return $mapped;
    }
}
