<?php

declare(strict_types=1);

namespace Melodic\Http;

class Request
{
    private readonly HttpMethod $method;
    private readonly string $path;
    private readonly array $queryParams;
    private readonly array $bodyParams;
    private readonly array $headers;
    private readonly array $attributes;
    private readonly array $cookies;
    private readonly string $rawBody;

    public function __construct(
        ?array $server = null,
        ?array $query = null,
        ?array $body = null,
        ?array $headers = null,
        array $attributes = [],
        ?string $rawBody = null,
        ?array $cookies = null,
    ) {
        $server = $server ?? $_SERVER;
        $this->method = HttpMethod::parse($server['REQUEST_METHOD'] ?? 'GET');
        $this->path = parse_url($server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
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

        if ($authorization === null || !str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return substr($authorization, 7);
    }

    public function getAttribute(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function withAttribute(string $name, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$name] = $value;

        return new self(
            server: ['REQUEST_METHOD' => $this->method->value, 'REQUEST_URI' => $this->path],
            query: $this->queryParams,
            body: $this->bodyParams,
            headers: $this->headers,
            attributes: $attributes,
            rawBody: $this->rawBody,
            cookies: $this->cookies,
        );
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
