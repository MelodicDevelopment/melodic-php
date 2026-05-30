<?php

declare(strict_types=1);

namespace Melodic\Security;

class OAuthClient
{
    public function __construct(
        private readonly OidcProvider $provider,
        private readonly AuthProviderConfig $config,
    ) {
    }

    public function getAuthorizationUrl(string $state, string $codeVerifier): string
    {
        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);

        return $this->provider->getAuthorizationEndpoint() . '?' . $params;
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->redirectUri,
            'client_id' => $this->config->clientId,
            'code_verifier' => $codeVerifier,
        ]);

        return self::requestJson(
            'POST',
            $this->provider->getTokenEndpoint(),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            $postData,
        );
    }

    public static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(96)), '+/', '-_'), '=');
    }

    public static function generateState(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Perform a TLS-verified JSON HTTP request and return the decoded body.
     * Throws on transport failure, a non-JSON body, or a non-2xx status — the
     * latter so an error page or partial body is never mistaken for success.
     *
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    public static function requestJson(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $headerLines = "Accept: application/json\r\n";

        foreach ($headers as $name => $value) {
            $headerLines .= "{$name}: {$value}\r\n";
        }

        $http = [
            'method' => $method,
            'header' => $headerLines,
            'timeout' => 10,
            // Capture the body on 4xx/5xx instead of returning false, so we can
            // surface the provider's error description.
            'ignore_errors' => true,
        ];

        if ($body !== null) {
            $http['content'] = $body;
        }

        $context = stream_context_create([
            'http' => $http,
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            throw new SecurityException("HTTP request to {$url} failed.");
        }

        // $http_response_header is populated by the HTTP stream wrapper in this
        // scope once a response has been read (guaranteed here since we returned
        // above on a transport failure).
        $status = self::statusFromHeaders($http_response_header);
        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new SecurityException("Invalid JSON response from {$url}.");
        }

        if (($status === null || $status >= 400) || isset($decoded['error'])) {
            $reason = $decoded['error_description'] ?? $decoded['error'] ?? 'HTTP ' . ($status ?? 'unknown');
            throw new SecurityException("Request to {$url} failed: {$reason}");
        }

        return $decoded;
    }

    /**
     * Parse the numeric status from a stream wrapper's response header lines.
     * The last status line wins so redirects report the final response.
     *
     * @param string[] $headers
     */
    private static function statusFromHeaders(array $headers): ?int
    {
        $status = null;

        foreach ($headers as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $matches) === 1) {
                $status = (int) $matches[1];
            }
        }

        return $status;
    }
}
