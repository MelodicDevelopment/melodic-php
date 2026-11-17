<?php

declare(strict_types=1);

namespace Melodic\Security;

/**
 * Guards post-login redirect targets against open redirects. Only local
 * absolute paths are considered safe: a single leading "/" not followed by
 * another "/" or "\". Browsers normalize both "//host" and "/\host" in a
 * Location header to a protocol-relative (off-site) URL, so those are rejected.
 *
 * @deprecated since 4.1 — use \Melodic\Http\RedirectResponse::isLocalPath()
 *             (this class only forwards to it). Will be removed in 5.0.
 */
final class SafeRedirect
{
    public static function isSafePath(string $path): bool
    {
        return \Melodic\Http\RedirectResponse::isLocalPath($path);
    }
}
