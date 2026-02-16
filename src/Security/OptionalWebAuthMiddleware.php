<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class OptionalWebAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly JwtValidator $validator,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $token = $request->cookie($this->config->cookieName);

        if ($token !== null && is_string($token) && $token !== '') {
            try {
                $claims = $this->validator->validate($token);
                $userContext = UserContext::fromClaims($claims);
                $request = $request->withAttribute('userContext', $userContext);
            } catch (SecurityException) {
                $request = $request->withAttribute('userContext', UserContext::anonymous());
            }
        } else {
            $request = $request->withAttribute('userContext', UserContext::anonymous());
        }

        return $handler->handle($request);
    }
}
