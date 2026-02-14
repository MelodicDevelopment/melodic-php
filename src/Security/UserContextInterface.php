<?php

declare(strict_types=1);

namespace Melodic\Security;

interface UserContextInterface
{
    public function isAuthenticated(): bool;

    public function getUser(): ?User;

    public function getUsername(): ?string;

    public function hasEntitlement(string $entitlement): bool;

    public function hasAnyEntitlement(string ...$entitlements): bool;
}
