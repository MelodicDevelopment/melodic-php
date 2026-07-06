<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Session\SessionInterface;

class SessionManager implements SessionInterface
{
    public function __construct(
        private readonly bool $cookieSecure = true,
        private readonly string $cookieSameSite = 'Lax',
        private readonly string $cookiePath = '/',
        private readonly string $cookieDomain = '',
    ) {
    }

    public function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Harden the session cookie before the session starts. Secure defaults
            // on; disable via config for plain-HTTP local development.
            if (!headers_sent()) {
                session_set_cookie_params([
                    'lifetime' => 0,
                    'path' => $this->cookiePath,
                    'domain' => $this->cookieDomain,
                    'secure' => $this->cookieSecure,
                    'httponly' => true,
                    'samesite' => $this->cookieSameSite,
                ]);
            }

            session_start();
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
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
            // Expire the browser's session cookie too — destroying only the
            // server-side state leaves the old id in the browser, and the next
            // start() would adopt it again (fixation via id reuse). Cookie
            // attributes must match the ones it was set with or the browser
            // keeps the original cookie.
            if (!headers_sent()) {
                setcookie(session_name(), '', [
                    'expires' => time() - 3600,
                    'path' => $this->cookiePath,
                    'domain' => $this->cookieDomain,
                    'secure' => $this->cookieSecure,
                    'httponly' => true,
                    'samesite' => $this->cookieSameSite,
                ]);
            }

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
