<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Session\NativeSession;

/**
 * The session used by the authentication middleware. Behaviorally identical
 * to NativeSession (it previously duplicated that class line for line); kept
 * as its own type because the auth provider interfaces type-hint it.
 */
class SessionManager extends NativeSession
{
}
