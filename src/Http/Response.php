<?php

declare(strict_types=1);

namespace Melodic\Http;

class Response
{
    private array $cookies = [];

    public function __construct(
        private int $statusCode = 200,
        private string $body = '',
        private array $headers = [],
    ) {}

    public function withStatus(int $code): static
    {
        $response = clone $this;
        $response->statusCode = $code;

        return $response;
    }

    public function withHeader(string $name, string $value): static
    {
        $response = clone $this;
        $response->headers[$name] = $value;

        return $response;
    }

    public function withBody(string $body): static
    {
        $response = clone $this;
        $response->body = $body;

        return $response;
    }

    public function withCookie(string $name, string $value, array $options = []): static
    {
        $response = clone $this;
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
