<?php

declare(strict_types=1);

namespace Melodic\Security;

class CsrfToken
{
    private const SESSION_KEY = 'melodic_csrf_token';

    public function __construct(
        private readonly SessionManager $session,
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

    public function validate(string $token): bool
    {
        $stored = $this->session->get(self::SESSION_KEY);

        if ($stored === null || !is_string($stored)) {
            return false;
        }

        $this->session->remove(self::SESSION_KEY);

        return hash_equals($stored, $token);
    }
}
