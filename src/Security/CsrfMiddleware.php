<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Exception\HttpException;
use Melodic\Http\HttpMethod;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Session\SessionInterface;

/**
 * CSRF protection for cookie-authenticated (MVC) requests.
 *
 * Every state-changing request (anything but GET/HEAD/OPTIONS) must carry the
 * session's CSRF token, either as a `csrf_token` form/JSON field or an
 * `X-CSRF-Token` header; otherwise the request is rejected with 403.
 *
 * Not checked:
 *  - paths under $exceptPaths (default: /api — token-authenticated APIs);
 *  - requests carrying an `Authorization: Bearer` header. A cross-site form
 *    cannot set that header, so such a request is not forgeable.
 *
 * The token is exposed as the `csrfToken` request attribute; MvcController
 * passes `$csrfToken` and a ready-made `$csrfField` hidden input to views.
 * The token is per-session (not one-time), so multiple tabs and forms work.
 */
class CsrfMiddleware implements MiddlewareInterface
{
    private readonly CsrfToken $csrf;

    /** @param string[] $exceptPaths Path prefixes that skip the check. */
    public function __construct(
        SessionInterface $session,
        private readonly array $exceptPaths = ['/api'],
    ) {
        $this->csrf = new CsrfToken($session);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($this->isExempt($request)) {
            return $handler->handle($request);
        }

        $safeMethod = in_array(
            $request->method(),
            [HttpMethod::GET, HttpMethod::HEAD, HttpMethod::OPTIONS],
            true,
        );

        if (!$safeMethod) {
            $submitted = $request->body('csrf_token') ?? $request->header('X-CSRF-Token');

            if (!is_string($submitted) || $submitted === '' || !$this->csrf->check($submitted)) {
                throw HttpException::forbidden('Invalid or missing CSRF token.');
            }
        }

        return $handler->handle($request->withAttribute('csrfToken', $this->csrf->getToken()));
    }

    private function isExempt(Request $request): bool
    {
        if ($request->bearerToken() !== null) {
            return true;
        }

        $path = $request->path();

        foreach ($this->exceptPaths as $prefix) {
            $prefix = rtrim($prefix, '/');

            if ($prefix === '' || $path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
