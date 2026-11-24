<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

/**
 * Adds baseline browser-hardening headers to every response. A header the
 * application already set is never overwritten; set a config value to null or
 * false to omit that header.
 *
 * Config keys (defaults):
 *  - contentTypeOptions     "nosniff"
 *  - frameOptions           "SAMEORIGIN"
 *  - referrerPolicy         "strict-origin-when-cross-origin"
 *  - hsts                   null — e.g. "max-age=31536000; includeSubDomains";
 *                           only sent on HTTPS requests
 *  - contentSecurityPolicy  null — e.g. "default-src 'self'"
 *  - permissionsPolicy      null
 */
class SecurityHeadersMiddleware implements MiddlewareInterface, ResponseDecoratorInterface
{
    private const DEFAULTS = [
        'contentTypeOptions' => 'nosniff',
        'frameOptions' => 'SAMEORIGIN',
        'referrerPolicy' => 'strict-origin-when-cross-origin',
        'hsts' => null,
        'contentSecurityPolicy' => null,
        'permissionsPolicy' => null,
    ];

    private const HEADER_NAMES = [
        'contentTypeOptions' => 'X-Content-Type-Options',
        'frameOptions' => 'X-Frame-Options',
        'referrerPolicy' => 'Referrer-Policy',
        'hsts' => 'Strict-Transport-Security',
        'contentSecurityPolicy' => 'Content-Security-Policy',
        'permissionsPolicy' => 'Permissions-Policy',
    ];

    /** @var array<string, mixed> */
    private readonly array $config;

    /** @param array<string, mixed> $config */
    public function __construct(array $config = [])
    {
        $this->config = $config + self::DEFAULTS;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        return $this->decorate($request, $handler->handle($request));
    }

    public function decorate(Request $request, Response $response): Response
    {
        foreach (self::HEADER_NAMES as $key => $header) {
            $value = $this->config[$key] ?? null;

            if (!is_string($value) || $value === '' || $response->getHeader($header) !== null) {
                continue;
            }

            if ($key === 'hsts' && !$request->isSecure()) {
                continue;
            }

            $response = $response->withHeader($header, $value);
        }

        return $response;
    }
}
