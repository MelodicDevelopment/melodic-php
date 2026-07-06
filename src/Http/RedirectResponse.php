<?php

declare(strict_types=1);

namespace Melodic\Http;

/**
 * A Location-header redirect. CRLF injection is not possible — header() rejects
 * values containing newlines — but the *destination* is only as trustworthy as
 * the URL you pass. Never construct one directly from user input (query params,
 * form fields, stored return-URLs): that is an open redirect. Use local() when
 * the target should always be a same-origin path.
 */
class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302)
    {
        parent::__construct(
            statusCode: $statusCode,
            body: '',
            headers: ['Location' => $url],
        );
    }

    /**
     * Redirect to a local (same-origin) path, rejecting anything else. Guards
     * against open redirects when the path originates outside the code base.
     *
     * @throws \InvalidArgumentException When $path is not a local path.
     */
    public static function local(string $path, int $statusCode = 302): static
    {
        if (!self::isLocalPath($path)) {
            throw new \InvalidArgumentException(
                "Redirect target must be a local path (got '{$path}')."
            );
        }

        return new static($path, $statusCode);
    }

    /**
     * A local path starts with exactly one "/": browsers normalize both
     * "//host" and "/\host" in a Location header to protocol-relative
     * (off-site) URLs, so those are rejected along with absolute URLs.
     */
    public static function isLocalPath(string $path): bool
    {
        return preg_match('#^/(?![/\\\\])#', $path) === 1;
    }
}
