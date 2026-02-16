<?php

declare(strict_types=1);

namespace Melodic\Security;

class UserContext implements UserContextInterface
{
    public function __construct(
        private readonly ?User $user = null,
        private readonly ?string $provider = null,
        private readonly array $claims = [],
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->user !== null;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getUsername(): ?string
    {
        return $this->user?->username;
    }

    public function hasEntitlement(string $entitlement): bool
    {
        return $this->user?->hasEntitlement($entitlement) ?? false;
    }

    public function hasAnyEntitlement(string ...$entitlements): bool
    {
        return $this->user?->hasAnyEntitlement(...$entitlements) ?? false;
    }

    public function getProvider(): ?string
    {
        return $this->provider;
    }

    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }

    public function getClaims(): array
    {
        return $this->claims;
    }

    public static function anonymous(): self
    {
        return new self();
    }

    public static function fromClaims(array $claims): self
    {
        $user = new User(
            id: (string) ($claims['sub'] ?? ''),
            username: $claims['username'] ?? $claims['preferred_username'] ?? $claims['name'] ?? '',
            email: $claims['email'] ?? '',
            entitlements: $claims['entitlements'] ?? [],
        );

        return new self($user, $claims['provider'] ?? null, $claims);
    }
}
