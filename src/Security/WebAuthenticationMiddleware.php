<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class WebAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly JwtValidator $validator,
        private readonly SessionManager $session,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->config->webAuthEnabled) {
            $request = $request->withAttribute('userContext', UserContext::anonymous());

            return $handler->handle($request);
        }

        $token = $request->cookie($this->config->cookieName);

        if ($token !== null && is_string($token) && $token !== '') {
            $userContext = null;

            try {
                $userContext = UserContext::fromClaims($this->validator->validate($token));
            } catch (SecurityException) {
                // Invalid token — fall through to redirect
            }

            // Outside the try: a SecurityException thrown by the application
            // itself (controller, later middleware) must reach the error
            // handler, not be mistaken for a bad token and turned into a
            // login redirect.
            if ($userContext !== null) {
                return $handler->handle($request->withAttribute('userContext', $userContext));
            }
        }

        // Only remember local paths as the post-login target — a crafted path
        // like "/\evil.com" would otherwise bounce the user off-site after login.
        // The query string is kept so the user lands back on the same page.
        $target = $request->uri();

        if (RedirectResponse::isLocalPath($target)) {
            $this->session->set('melodic_redirect_after_login', $target);
        }

        return new RedirectResponse($this->config->loginPath);
    }
}
