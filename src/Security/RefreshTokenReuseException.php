<?php

declare(strict_types=1);

namespace Melodic\Security;

/**
 * A revoked or superseded refresh token was presented: the token family is
 * compromised and has been revoked. Carries the family id so the revocation
 * can be re-applied if the surrounding transaction rolls back.
 */
class RefreshTokenReuseException extends SecurityException
{
    public function __construct(public readonly string $familyId)
    {
        parent::__construct('Refresh token reuse detected.');
    }
}
