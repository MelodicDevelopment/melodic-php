<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Cache\CacheInterface;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

/**
 * Fixed-window rate limiter backed by the cache.
 *
 *     // 10 login attempts per minute per client
 *     $app->addMiddleware(new RateLimitMiddleware($cache, 10, 60, paths: ['/auth/callback']));
 *
 * Clients are identified by Request::ip() (REMOTE_ADDR) unless a $keyResolver
 * is supplied — behind a reverse proxy, pass one that reads the forwarded
 * address your proxy sets. Counting is best-effort (the cache read/write is
 * not atomic), which is fine for abuse throttling but not for exact quotas.
 */
class RateLimitMiddleware implements MiddlewareInterface
{
    private readonly ?\Closure $keyResolver;

    /**
     * @param string[] $paths Path prefixes to limit; empty limits every path.
     * @param string[] $methods HTTP methods to limit; empty limits every method.
     * @param (callable(Request): ?string)|null $keyResolver Client key; null result skips limiting.
     */
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxAttempts = 60,
        private readonly int $windowSeconds = 60,
        private readonly array $paths = [],
        private readonly array $methods = [],
        ?callable $keyResolver = null,
    ) {
        $this->keyResolver = $keyResolver !== null ? $keyResolver(...) : null;
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        if (!$this->applies($request)) {
            return $handler->handle($request);
        }

        $client = $this->keyResolver !== null ? ($this->keyResolver)($request) : $request->ip();

        if ($client === null || $client === '') {
            return $handler->handle($request);
        }

        // Every setting is part of the key, so two limiters over the same
        // paths (say a global one and a stricter POST-only one) keep
        // separate counters and windows.
        $key = 'ratelimit:' . sha1(implode('|', [
            implode(',', $this->paths),
            implode(',', array_map('strtoupper', $this->methods)),
            $this->maxAttempts,
            $this->windowSeconds,
            $client,
        ]));
        $now = time();
        $entry = $this->cache->get($key);

        if (!is_array($entry) || !isset($entry['count'], $entry['reset']) || $entry['reset'] <= $now) {
            $entry = ['count' => 0, 'reset' => $now + $this->windowSeconds];
        }

        $entry['count']++;
        $retryAfter = max(1, $entry['reset'] - $now);
        $this->cache->set($key, $entry, $retryAfter);

        $limitHeaders = [
            'X-RateLimit-Limit' => (string) $this->maxAttempts,
            'X-RateLimit-Remaining' => (string) max(0, $this->maxAttempts - $entry['count']),
            'X-RateLimit-Reset' => (string) $entry['reset'],
        ];

        if ($entry['count'] > $this->maxAttempts) {
            $limitHeaders['Retry-After'] = (string) $retryAfter;

            return $request->wantsJson() || $request->isJson() || $request->path() === '/api' || str_starts_with($request->path(), '/api/')
                ? new JsonResponse(['error' => 'Too many requests.'], 429, $limitHeaders)
                : new Response(429, 'Too many requests. Please try again later.', $limitHeaders + [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                ]);
        }

        $response = $handler->handle($request);

        foreach ($limitHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    private function applies(Request $request): bool
    {
        if ($this->methods !== []
            && !in_array($request->method()->value, array_map('strtoupper', $this->methods), true)
        ) {
            return false;
        }

        if ($this->paths === []) {
            return true;
        }

        $path = $request->path();

        foreach ($this->paths as $prefix) {
            $prefix = rtrim($prefix, '/');

            if ($prefix === '' || $path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
