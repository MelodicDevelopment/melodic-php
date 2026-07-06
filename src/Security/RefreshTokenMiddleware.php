<?php

declare(strict_types=1);

namespace Melodic\Security;

use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\LoggerInterface;
use Melodic\Log\NullLogger;

class RefreshTokenMiddleware implements MiddlewareInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly RefreshTokenService $service,
        private readonly RefreshTokenConfig $config,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
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
            // Return a generic message — distinguishing "reuse detected" from
            // "invalid token" would tell an attacker whether a stolen token's
            // family was already revoked. The real reason is logged server-side.
            $this->logger->warning('Refresh token rejected: {reason}', ['reason' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Authentication failed.'], 401);
        }

        $request = $request->withAttribute('refreshToken', $refreshToken);
        $request = $request->withAttribute('refreshTokenUserId', $refreshToken->userId);

        return $handler->handle($request);
    }
}
