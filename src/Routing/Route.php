<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Route
{
    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public readonly HttpMethod $method,
        public readonly string $pattern,
        public readonly string $controller,
        public readonly string $action,
        public readonly array $middleware = [],
        public readonly array $attributes = [],
    ) {}

    /** @return array<string, string>|null */
    public function matches(HttpMethod $method, string $path): ?array
    {
        // RFC 9110 §9.3.2: any resource supporting GET must also support HEAD.
        // Normalize HEAD to GET for the comparison so HEAD requests match GET routes.
        $comparable = $method === HttpMethod::HEAD ? HttpMethod::GET : $method;

        if ($this->method !== $comparable) {
            return null;
        }

        return $this->matchesPath($path);
    }

    /**
     * Match the path alone, ignoring the HTTP method. Used to detect routes that
     * exist for a path under a different method (→ 405 instead of 404).
     *
     * @return array<string, string>|null Captured route params, or null on no match.
     */
    public function matchesPath(string $path): ?array
    {
        $regex = preg_replace(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '(?P<$1>[^/]+)',
            $this->pattern,
        );

        $regex = '#^' . $regex . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, fn(string $key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
    }
}
