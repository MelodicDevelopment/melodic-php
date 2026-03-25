<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\HttpMethod;
use Melodic\Http\Request;
use Melodic\Http\Response;

class CorsMiddleware implements MiddlewareInterface
{
    private readonly array $allowedOrigins;
    private readonly array $allowedMethods;
    private readonly array $allowedHeaders;
    private readonly int $maxAge;
    private readonly bool $allowCredentials;

    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $config['allowedOrigins'] ?? ['*'];
        $this->allowedMethods = $config['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $this->allowedHeaders = $config['allowedHeaders'] ?? ['Content-Type', 'Authorization'];
        $this->maxAge = $config['maxAge'] ?? 86400;
        $this->allowCredentials = $config['allowCredentials'] ?? false;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->method() === HttpMethod::OPTIONS) {
            return $this->addCorsHeaders(new Response(204), $request);
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $request);
    }

    private function addCorsHeaders(Response $response, Request $request): Response
    {
        $origin = $request->header('Origin');
        $matchedOrigin = $this->resolveOrigin($origin);

        if ($matchedOrigin === null) {
            return $response;
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $matchedOrigin)
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);

        if ($matchedOrigin !== '*') {
            $response = $response->withHeader('Vary', 'Origin');
        }

        if ($this->allowCredentials && $matchedOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    private function resolveOrigin(?string $origin): ?string
    {
        if (in_array('*', $this->allowedOrigins, true)) {
            return '*';
        }

        if ($origin === null || $origin === '') {
            return null;
        }

        foreach ($this->allowedOrigins as $allowed) {
            if ($allowed === $origin) {
                return $origin;
            }

            if (str_contains($allowed, '*') && $this->matchesWildcard($allowed, $origin)) {
                return $origin;
            }
        }

        return null;
    }

    private function matchesWildcard(string $pattern, string $origin): bool
    {
        $regex = '/^' . str_replace('\*', '[a-zA-Z0-9\-]+', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $origin);
    }
}
