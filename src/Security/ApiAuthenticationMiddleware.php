<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class ApiAuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly AuthConfig $config,
        private readonly JwtValidator $validator,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->config->apiAuthEnabled) {
            $request = $request->withAttribute('userContext', UserContext::anonymous());

            return $handler->handle($request);
        }

        $token = $request->bearerToken();

        if ($token === null) {
            return new JsonResponse(['error' => 'Authentication required.'], 401);
        }

        try {
            $claims = $this->validator->validate($token);
            $userContext = UserContext::fromClaims($claims);
        } catch (SecurityException $e) {
            return new JsonResponse(['error' => 'Authentication failed: ' . $e->getMessage()], 401);
        }

        $request = $request->withAttribute('userContext', $userContext);

        return $handler->handle($request);
    }
}
