<?php

declare(strict_types=1);

namespace Melodic\Security;

class UserContext implements UserContextInterface
{
    /** @param array<string, mixed> $claims */
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

    /** @return array<string, mixed> */
    public function getClaims(): array
    {
        return $this->claims;
    }

    public static function anonymous(): self
    {
        return new self();
    }

    /** @param array<string, mixed> $claims */
    public static function fromClaims(array $claims): self
    {
        // Claims come from tokens minted by external identity providers, so
        // their shapes are not guaranteed: coerce rather than let an odd
        // claim (a non-string name, a single-string entitlement) blow up as
        // a TypeError.
        $string = static fn(mixed $value): string => is_scalar($value) ? (string) $value : '';

        $entitlements = $claims['entitlements'] ?? [];
        $entitlements = is_array($entitlements) ? $entitlements : [$entitlements];

        $user = new User(
            id: $string($claims['sub'] ?? ''),
            username: $string($claims['username'] ?? $claims['preferred_username'] ?? $claims['name'] ?? ''),
            email: $string($claims['email'] ?? ''),
            entitlements: array_values(array_filter(array_map($string, $entitlements), fn(string $e) => $e !== '')),
        );

        $provider = $claims['provider'] ?? null;

        return new self($user, is_string($provider) ? $provider : null, $claims);
    }
}
