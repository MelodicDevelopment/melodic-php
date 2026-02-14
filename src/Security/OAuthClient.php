<?php

declare(strict_types=1);

namespace Melodic\Security;

class OAuthClient
{
    public function __construct(
        private readonly OidcProvider $provider,
        private readonly AuthConfig $config,
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

    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $tokenEndpoint = $this->provider->getTokenEndpoint();

        $postData = http_build_query([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->redirectUri,
            'client_id' => $this->config->clientId,
            'code_verifier' => $codeVerifier,
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                'content' => $postData,
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = file_get_contents($tokenEndpoint, false, $context);

        if ($response === false) {
            throw new SecurityException('Failed to exchange authorization code for tokens.');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new SecurityException('Invalid token response from authorization server.');
        }

        if (isset($decoded['error'])) {
            throw new SecurityException('Token exchange failed: ' . ($decoded['error_description'] ?? $decoded['error']));
        }

        return $decoded;
    }

    public static function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(96)), '+/', '-_'), '=');
    }

    public static function generateState(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
