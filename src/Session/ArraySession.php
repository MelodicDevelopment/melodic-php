<?php

declare(strict_types=1);

namespace Melodic\Session;

class ArraySession implements SessionInterface
{
    private array $data = [];
    private bool $started = false;

    public function start(): void
    {
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->started = true;
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function destroy(): void
    {
        $this->data = [];
        $this->started = false;
    }

    public function regenerate(bool $deleteOld = true): void
    {
        // No-op for in-memory session
    }

    public function isStarted(): bool
    {
        return $this->started;
    }
}
