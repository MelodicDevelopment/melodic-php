<?php

declare(strict_types=1);

namespace Melodic\Session;

class NativeSession implements SessionInterface
{
    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();

        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function has(string $key): bool
    {
        $this->start();

        return array_key_exists($key, $_SESSION);
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        if ($this->isStarted()) {
            session_destroy();
            $_SESSION = [];
        }
    }

    public function regenerate(bool $deleteOld = true): void
    {
        if ($this->isStarted()) {
            session_regenerate_id($deleteOld);
        }
    }

    public function isStarted(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
