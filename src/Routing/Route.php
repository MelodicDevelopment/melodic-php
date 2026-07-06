<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Route
{
    private ?string $compiledRegex = null;

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
        if (!preg_match($this->compiledRegex(), $path, $matches)) {
            return null;
        }

        return array_filter($matches, fn(string $key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);
    }

    /**
     * Compile the pattern to a regex once: {param} tokens become named capture
     * groups and literal segments are preg_quote()d so metacharacters match
     * literally (previously "/v1.0/users" treated the dot as a wildcard).
     */
    private function compiledRegex(): string
    {
        if ($this->compiledRegex === null) {
            $regex = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}|([^{]+)/',
                fn(array $m) => isset($m[2])
                    ? preg_quote($m[2], '#')
                    : '(?P<' . $m[1] . '>[^/]+)',
                $this->pattern,
            );

            $this->compiledRegex = '#^' . $regex . '$#';
        }

        return $this->compiledRegex;
    }
}
