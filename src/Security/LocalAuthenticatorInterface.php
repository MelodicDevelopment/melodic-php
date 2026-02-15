<?php

declare(strict_types=1);

namespace Melodic\Security;

interface LocalAuthenticatorInterface
{
    /**
     * Authenticate a user with the given credentials.
     *
     * Returns an array of user claims on success (must include 'sub', 'username', 'email').
     * Throws SecurityException on failure.
     *
     * @throws SecurityException
     */
    public function authenticate(string $username, string $password): array;
}
