<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Route
{
    /** One level of nested braces, so quantifiers such as \d{4} are allowed. */
    public const PARAM_TOKEN = '\{([a-zA-Z_][a-zA-Z0-9_]*)(?::((?:[^{}]|\{[^{}]*\})+))?\}';

    private ?string $compiledRegex = null;

    /** @var array<string, string> Per-parameter constraint regex, filled by compiledRegex(). */
    private array $constraints = [];

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
            if (!is_string($key)) {
                continue;
            }

            // The path is matched undecoded (so an encoded "/" cannot cross
            // a segment boundary); decode the captured values.
            $decoded = rawurldecode($value);

            // The decoded value is what the controller receives, so it must
            // satisfy the constraint too. Otherwise "%2E%2E%2F" would slip
            // "../" past {name} (one segment) or {name:[^/.]+}.
            if ($decoded !== $value
                && preg_match('#^(?:' . ($this->constraints[$key] ?? '[^/]+') . ')$#', $decoded) !== 1
            ) {
                return null;
            }

            $params[$key] = $decoded;
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
            // (e.g. {id:\d+} or {year:\d{4}}).
            $regex = preg_replace_callback(
                '/' . self::PARAM_TOKEN . '|([^{]+)/',
                function (array $m): string {
                    if ($m[3] !== null) {
                        return preg_quote($m[3], '#');
                    }

                    $constraint = $m[2] !== null ? str_replace('#', '\\#', $m[2]) : '[^/]+';
                    $this->constraints[$m[1]] = $constraint;

                    return '(?P<' . $m[1] . '>' . $constraint . ')';
                },
                $this->pattern,
                flags: PREG_UNMATCHED_AS_NULL,
            );

            $this->compiledRegex = '#^' . $regex . '$#';
        }

        return $this->compiledRegex;
    }
}
