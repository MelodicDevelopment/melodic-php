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

    public function __construct(array $config = [])
    {
        $this->allowedOrigins = $config['allowedOrigins'] ?? ['*'];
        $this->allowedMethods = $config['allowedMethods'] ?? ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];
        $this->allowedHeaders = $config['allowedHeaders'] ?? ['Content-Type', 'Authorization'];
        $this->maxAge = $config['maxAge'] ?? 86400;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if ($request->method() === HttpMethod::OPTIONS) {
            return $this->addCorsHeaders(new Response(204));
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response);
    }

    private function addCorsHeaders(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', implode(', ', $this->allowedOrigins))
            ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
            ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
            ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
    }
}
