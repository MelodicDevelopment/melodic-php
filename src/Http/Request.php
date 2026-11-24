<?php

declare(strict_types=1);

namespace Melodic\Http;

class Request
{
    private readonly HttpMethod $method;
    private readonly string $path;
    /** @var array<string, mixed> */
    private readonly array $queryParams;
    /** @var array<string, mixed> */
    private readonly array $bodyParams;
    /** @var array<string, string> */
    private readonly array $headers;
    /** @var array<string, mixed> Not readonly: withAttribute() clones and sets. */
    private array $attributes;
    /** @var array<string, mixed> */
    private readonly array $server;
    /** @var array<string, mixed> */
    private readonly array $files;
    /** @var array<string, mixed> */
    private readonly array $cookies;
    private readonly string $rawBody;

    /**
     * @param array<string, mixed>|null $server
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>|null $body
     * @param array<string, string>|null $headers
     * @param array<string, mixed> $attributes
     * @param array<string, mixed>|null $cookies
     * @param array<string, mixed>|null $files
     */
    public function __construct(
        ?array $server = null,
        ?array $query = null,
        ?array $body = null,
        ?array $headers = null,
        array $attributes = [],
        ?string $rawBody = null,
        ?array $cookies = null,
        ?array $files = null,
    ) {
        $server = $server ?? $_SERVER;
        $this->server = $server;
        $this->method = HttpMethod::parse((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        $this->path = self::extractPath((string) ($server['REQUEST_URI'] ?? '/'));
        $this->files = $files ?? $_FILES;
        $this->queryParams = $query ?? $_GET;
        $this->bodyParams = $body ?? $_POST;
        $this->headers = $headers ?? self::extractHeaders($server);
        $this->attributes = $attributes;
        $this->rawBody = $rawBody ?? '';
        $this->cookies = $cookies ?? $_COOKIE;
    }

    public function method(): HttpMethod
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }

        return $this->queryParams[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        $params = $this->bodyParams;

        // Fall back to parsed JSON body from JsonBodyParserMiddleware
        if (empty($params) && isset($this->attributes['parsedBody']) && is_array($this->attributes['parsedBody'])) {
            $params = $this->attributes['parsedBody'];
        }

        if ($key === null) {
            return $params;
        }

        return $params[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $normalized = strtolower($name);

        foreach ($this->headers as $headerName => $headerValue) {
            if (strtolower($headerName) === $normalized) {
                return $headerValue;
            }
        }

        return null;
    }

    public function bearerToken(): ?string
    {
        $authorization = $this->header('Authorization');

        // The auth scheme is case-insensitive (RFC 9110 §11.1).
        if ($authorization === null || strncasecmp($authorization, 'Bearer ', 7) !== 0) {
            return null;
        }

        return trim(substr($authorization, 7));
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $request = clone $this;
        $request->attributes[$name] = $value;

        return $request;
    }

    /** @return array<string, mixed> */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * A $_SERVER value (or the whole array when $key is null).
     */
    public function server(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->server;
        }

        return $this->server[$key] ?? $default;
    }

    /**
     * The directly connected client address (REMOTE_ADDR). Behind a reverse
     * proxy or load balancer this is the proxy's address — forwarded headers
     * are deliberately not trusted here, because any client can send them.
     */
    public function ip(): ?string
    {
        $ip = $this->server['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    public function isSecure(): bool
    {
        $https = $this->server['HTTPS'] ?? '';

        return (is_string($https) && $https !== '' && strtolower($https) !== 'off')
            || (string) ($this->server['SERVER_PORT'] ?? '') === '443';
    }

    public function scheme(): string
    {
        return $this->isSecure() ? 'https' : 'http';
    }

    /**
     * Host name as sent by the client (Host header), falling back to the
     * server name. Client-controlled: never use it to build security-sensitive
     * URLs (password-reset links, redirects) without an allow-list.
     */
    public function host(): string
    {
        $host = $this->header('Host') ?? (string) ($this->server['SERVER_NAME'] ?? '');

        return strtolower($host);
    }

    /** The raw query string, without the leading "?". */
    public function queryString(): string
    {
        $uri = (string) ($this->server['REQUEST_URI'] ?? '');
        $position = strpos($uri, '?');

        if ($position !== false) {
            return substr($uri, $position + 1);
        }

        return (string) ($this->server['QUERY_STRING'] ?? '');
    }

    /** Path plus query string, e.g. "/users?page=2". */
    public function uri(): string
    {
        $query = $this->queryString();

        return $query === '' ? $this->path : $this->path . '?' . $query;
    }

    /**
     * An uploaded-file entry in PHP's $_FILES shape (name, type, tmp_name,
     * error, size), or all entries when $key is null.
     */
    public function file(?string $key = null): mixed
    {
        if ($key === null) {
            return $this->files;
        }

        return $this->files[$key] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('Content-Type') ?? ''), 'application/json');
    }

    /** Whether the client asked for JSON via the Accept header. */
    public function wantsJson(): bool
    {
        return str_contains(strtolower($this->header('Accept') ?? ''), 'application/json');
    }

    /**
     * The path component of a request target. Origin-form targets ("/a/b?x")
     * are split by hand: parse_url() would read "//host/path" as a
     * network-path reference and drop the first segment.
     */
    private static function extractPath(string $uri): string
    {
        if ($uri === '' || $uri[0] !== '/') {
            // absolute-form ("http://host/path") or garbage
            $path = parse_url($uri, PHP_URL_PATH);

            return is_string($path) && $path !== '' ? $path : '/';
        }

        $end = strcspn($uri, '?#');

        return substr($uri, 0, $end);
    }

    public static function capture(): self
    {
        return new self(
            rawBody: file_get_contents('php://input') ?: '',
        );
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @param array<string, mixed> $server
     * @return array<string, string>
     */
    private static function extractHeaders(array $server): array
    {
        $headers = [];

        foreach ($server as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $name = ucwords(strtolower($name), '-');
                $headers[$name] = $value;
            }
        }

        if (isset($server['CONTENT_TYPE'])) {
            $headers['Content-Type'] = $server['CONTENT_TYPE'];
        }

        if (isset($server['CONTENT_LENGTH'])) {
            $headers['Content-Length'] = $server['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
