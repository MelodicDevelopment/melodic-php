<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Session\SessionInterface;

class CsrfToken
{
    private const SESSION_KEY = 'melodic_csrf_token';

    public function __construct(
        private readonly SessionInterface $session,
    ) {
    }

    public function generate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }

    /**
     * Return the current session token, generating one only if none exists.
     * Prefer this when rendering forms so repeated renders (multiple tabs,
     * prefetch) don't churn the single stored token and invalidate earlier forms.
     */
    public function getToken(): string
    {
        $existing = $this->session->get(self::SESSION_KEY);

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        return $this->generate();
    }

    /**
     * Compare a submitted token with the session token without consuming it.
     * A per-session token stays valid across tabs, multiple forms on a page,
     * and the back button — use this for general form protection
     * (CsrfMiddleware does). validate() is the one-time-use variant.
     */
    public function check(string $token): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        return is_string($stored) && $stored !== '' && hash_equals($stored, $token);
    }

    public function validate(string $token): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if ($stored === null || !is_string($stored)) {
            return false;
        }

        if (!hash_equals($stored, $token)) {
            // Leave the stored token intact on mismatch: consuming it here
            // would let a forged/garbage POST invalidate the token embedded in
            // the user's legitimately rendered form (CSRF-protection self-DoS).
            return false;
        }

        // One-time use: consume only on successful validation.
        $this->session->remove(self::SESSION_KEY);

        return true;
    }
}
