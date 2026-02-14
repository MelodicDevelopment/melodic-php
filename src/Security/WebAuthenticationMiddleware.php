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
            try {
                $claims = $this->validator->validate($token);
                $userContext = UserContext::fromClaims($claims);
                $request = $request->withAttribute('userContext', $userContext);

                return $handler->handle($request);
            } catch (SecurityException) {
                // Invalid token — fall through to redirect
            }
        }

        $this->session->set('melodic_redirect_after_login', $request->path());

        return new RedirectResponse($this->config->loginPath);
    }
}
