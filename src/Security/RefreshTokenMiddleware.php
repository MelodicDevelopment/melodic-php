<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class RefreshTokenMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RefreshTokenService $service,
        private readonly RefreshTokenConfig $config,
    ) {
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $rawToken = $request->cookie($this->config->cookieName);

        if ($rawToken === null) {
            return new JsonResponse(['error' => 'Refresh token required.'], 401);
        }

        try {
            $refreshToken = $this->service->validate($rawToken);
        } catch (SecurityException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        }

        $request = $request->withAttribute('refreshToken', $refreshToken);
        $request = $request->withAttribute('refreshTokenUserId', $refreshToken->userId);

        return $handler->handle($request);
    }
}
