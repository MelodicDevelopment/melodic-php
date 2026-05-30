<?php

declare(strict_types=1);

namespace Melodic\Http;

class Response
{
    /** @var array<string, array<string, mixed>> */
    private array $cookies = [];

    /** @param array<string, string> $headers */
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

    /** @param array<string, mixed> $options */
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

    /** @return array<string, string> */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Send the response to the client.
     *
     * @param bool $includeBody When false, headers and cookies are sent but the
     *                          body is suppressed — required for HEAD requests
     *                          (RFC 9110 §9.3.2), which must mirror GET's headers
     *                          without a message body.
     */
    public function send(bool $includeBody = true): void
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

        if ($includeBody) {
            echo $this->body;
        }
    }
}
