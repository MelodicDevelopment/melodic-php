<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\HttpMethod;
use Melodic\Http\Request;
use Melodic\Http\Response;

class CorsMiddleware implements MiddlewareInterface, ResponseDecoratorInterface
{
    /** @var string[] */
    private readonly array $allowedOrigins;
    /** @var string[] */
    private readonly array $allowedMethods;
    /** @var string[] */
    private readonly array $allowedHeaders;
    private readonly int $maxAge;
    private readonly bool $allowCredentials;

    /** @param array<string, mixed> $config */
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
            return $this->addCorsHeaders(new Response(204), $request, preflight: true);
        }

        $response = $handler->handle($request);

        return $this->addCorsHeaders($response, $request, preflight: false);
    }

    public function decorate(Request $request, Response $response): Response
    {
        return $this->addCorsHeaders($response, $request, preflight: false);
    }

    private function addCorsHeaders(Response $response, Request $request, bool $preflight): Response
    {
        $origin = $request->header('Origin');
        $matchedOrigin = $this->resolveOrigin($origin);

        // The response differs per Origin whenever origins are restricted —
        // including the "not allowed" case — so shared caches must key on it.
        if ($matchedOrigin !== '*') {
            $response = $this->appendVary($response, 'Origin');
        }

        if ($matchedOrigin === null) {
            return $response;
        }

        $response = $response->withHeader('Access-Control-Allow-Origin', $matchedOrigin);

        // Allow-Methods / Allow-Headers / Max-Age only have meaning on a
        // preflight response — browsers ignore them on actual responses, so
        // emitting them there is just header noise.
        if ($preflight) {
            $response = $response
                ->withHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods))
                ->withHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders))
                ->withHeader('Access-Control-Max-Age', (string) $this->maxAge);
        }

        if ($this->allowCredentials && $matchedOrigin !== '*') {
            $response = $response->withHeader('Access-Control-Allow-Credentials', 'true');
        }

        return $response;
    }

    /** Add a token to the Vary header without clobbering an existing value. */
    private function appendVary(Response $response, string $token): Response
    {
        $existing = '';

        foreach ($response->getHeaders() as $name => $value) {
            if (strtolower($name) === 'vary') {
                $existing = $value;
                $response = $response->withoutHeader($name);
            }
        }

        $tokens = array_filter(array_map('trim', explode(',', $existing)), fn(string $t) => $t !== '');

        if (!in_array('*', $tokens, true) && !in_array(strtolower($token), array_map('strtolower', $tokens), true)) {
            $tokens[] = $token;
        }

        return $response->withHeader('Vary', implode(', ', $tokens));
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
        // `*` matches one or more DNS label characters including dots, so
        // `https://*.example.com` matches sub-subdomains (a.b.example.com), not
        // just a single label. The literal suffix stays anchored by `^...$`.
        $regex = '/^' . str_replace('\*', '[a-zA-Z0-9.\-]+', preg_quote($pattern, '/')) . '$/';

        return (bool) preg_match($regex, $origin);
    }
}
