<?php

declare(strict_types=1);

namespace Melodic\Security;

/**
 * Guards post-login redirect targets against open redirects. Only local
 * absolute paths are considered safe: a single leading "/" not followed by
 * another "/" or "\". Browsers normalize both "//host" and "/\host" in a
 * Location header to a protocol-relative (off-site) URL, so those are rejected.
 */
final class SafeRedirect
{
    public static function isSafePath(string $path): bool
    {
        return \Melodic\Http\RedirectResponse::isLocalPath($path);
    }
}
