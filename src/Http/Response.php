<?php

declare(strict_types=1);

namespace Melodic\Http;

class Response
{
    private array $cookies = [];

    public function __construct(
        private readonly int $statusCode = 200,
        private readonly string $body = '',
        private readonly array $headers = [],
    ) {}

    public function withStatus(int $code): self
    {
        $response = new self($code, $this->body, $this->headers);
        $response->cookies = $this->cookies;

        return $response;
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        $response = new self($this->statusCode, $this->body, $headers);
        $response->cookies = $this->cookies;

        return $response;
    }

    public function withBody(string $body): self
    {
        $response = new self($this->statusCode, $body, $this->headers);
        $response->cookies = $this->cookies;

        return $response;
    }

    public function withCookie(string $name, string $value, array $options = []): self
    {
        $response = new self($this->statusCode, $this->body, $this->headers);
        $response->cookies = $this->cookies;
        $response->cookies[$name] = [
            'value' => $value,
            'expires' => $options['expires'] ?? 0,
            'path' => $options['path'] ?? '/',
            'domain' => $options['domain'] ?? '',
            'secure' => $options['secure'] ?? false,
            'httponly' => $options['httponly'] ?? true,
            'samesite' => $options['samesite'] ?? 'Lax',
        ];

        return $response;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function send(): void
    {
        http_response_code($this->statusCode);

        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        foreach ($this->cookies as $name => $cookie) {
            setcookie($name, $cookie['value'], [
                'expires' => $cookie['expires'],
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'],
            ]);
        }

        echo $this->body;
    }
}
