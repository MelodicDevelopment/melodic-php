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
        public readonly ?string $name = null,
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
        $regex = $this->compiledRegex();

        if (!preg_match($regex, $path, $matches)) {
            // "/users/" is the same resource as "/users": retry without the
            // trailing slash (an exact match above still wins, so a pattern
            // registered with a trailing slash keeps working).
            if ($path === '/' || !str_ends_with($path, '/') || !preg_match($regex, rtrim($path, '/'), $matches)) {
                return null;
            }
        }

        $params = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                // The path is matched undecoded (so an encoded "/" cannot
                // cross a segment boundary); decode the captured values.
                $params[$key] = rawurldecode($value);
            }
        }

        return $params;
    }

    /**
     * Compile the pattern to a regex once: {param} tokens become named capture
     * groups and literal segments are preg_quote()d so metacharacters match
     * literally (previously "/v1.0/users" treated the dot as a wildcard).
     */
    private function compiledRegex(): string
    {
        if ($this->compiledRegex === null) {
            // {name} matches one path segment; {name:regex} constrains it
            // (e.g. {id:\d+}). The constraint may not contain braces.
            $regex = preg_replace_callback(
                '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^{}]+))?\}|([^{]+)/',
                fn(array $m) => $m[3] !== null
                    ? preg_quote($m[3], '#')
                    : '(?P<' . $m[1] . '>' . ($m[2] !== null ? str_replace('#', '\\#', $m[2]) : '[^/]+') . ')',
                $this->pattern,
                flags: PREG_UNMATCHED_AS_NULL,
            );

            $this->compiledRegex = '#^' . $regex . '$#';
        }

        return $this->compiledRegex;
    }
}
