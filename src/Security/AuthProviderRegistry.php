<?php

declare(strict_types=1);

namespace Melodic\Security;

class AuthProviderRegistry
{
    /** @var array<string, AuthProviderInterface> */
    private array $providers = [];

    public function register(AuthProviderInterface $provider): void
    {
        $this->providers[$provider->getName()] = $provider;
    }

    public function get(string $name): AuthProviderInterface
    {
        return $this->providers[$name]
            ?? throw new SecurityException("Unknown auth provider: {$name}");
    }

    /** @return array<string, AuthProviderInterface> */
    public function all(): array
    {
        return $this->providers;
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /** @return array<string, AuthProviderInterface> */
    public function getByType(AuthProviderType $type): array
    {
        return array_filter(
            $this->providers,
            fn(AuthProviderInterface $p) => $p->getType() === $type,
        );
    }
}
