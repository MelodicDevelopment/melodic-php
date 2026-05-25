<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Route
{
    public function __construct(
        public readonly HttpMethod $method,
        public readonly string $pattern,
        public readonly string $controller,
        public readonly string $action,
        public readonly array $middleware = [],
        public readonly array $attributes = [],
    ) {}

    public function matches(HttpMethod $method, string $path): ?array
    {
        // RFC 9110 §9.3.2: any resource supporting GET must also support HEAD.
        // Normalize HEAD to GET for the comparison so HEAD requests match GET routes.
        $comparable = $method === HttpMethod::HEAD ? HttpMethod::GET : $method;

        if ($this->method !== $comparable) {
            return null;
        }

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
