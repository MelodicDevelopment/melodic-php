<?php

declare(strict_types=1);

namespace Melodic\Security;

use Firebase\JWT\JWT;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class OAuth2AuthProvider implements AuthProviderInterface
{
    public function __construct(
        private readonly AuthProviderConfig $config,
        private readonly LocalAuthConfig $localAuthConfig,
        private readonly ClaimMapper $claimMapper,
    ) {
    }

    public function getName(): string
    {
        return $this->config->name;
    }

    public function getLabel(): string
    {
        return $this->config->label;
    }

    public function getType(): AuthProviderType
    {
        return AuthProviderType::OAuth2;
    }

    public function handleLogin(Request $request, SessionManager $session): Response
    {
        $state = OAuthClient::generateState();
        $session->set("melodic_oauth_state_{$this->config->name}", $state);

        $params = [
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => $this->config->scopes,
            'state' => $state,
        ];

        $authorizationUrl = $this->config->authorizeUrl . '?' . http_build_query($params);

        return new RedirectResponse($authorizationUrl);
    }

    public function handleCallback(Request $request, SessionManager $session): AuthResult
    {
        $code = $request->query('code');
        $state = $request->query('state');
        $error = $request->query('error');

        if ($error !== null) {
            $description = $request->query('error_description', $error);
            throw new SecurityException('OAuth error: ' . $description);
        }

        if ($code === null || $state === null) {
            throw new SecurityException('Missing authorization code or state parameter.');
        }

        $savedState = $session->get("melodic_oauth_state_{$this->config->name}");
        $session->remove("melodic_oauth_state_{$this->config->name}");

        if ($savedState === null || !hash_equals((string) $savedState, (string) $state)) {
            throw new SecurityException('Invalid OAuth state parameter.');
        }

        $accessToken = $this->exchangeCode((string) $code);
        $rawClaims = $this->fetchUserInfo($accessToken);
        $claims = $this->claimMapper->map($rawClaims);
        $claims['provider'] = $this->config->name;

        $token = $this->issueLocalJwt($claims);

        return new AuthResult(
            token: $token,
            claims: $claims,
            providerName: $this->config->name,
        );
    }

    private function exchangeCode(string $code): string
    {
        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->redirectUri,
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
        ];

        $postData = http_build_query($postFields);

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

        $response = file_get_contents($this->config->tokenUrl, false, $context);

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

        return $decoded['access_token']
            ?? throw new SecurityException('No access token received from authorization server.');
    }

    private function fetchUserInfo(string $accessToken): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Authorization: Bearer {$accessToken}\r\nAccept: application/json\r\n",
                'timeout' => 10,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = file_get_contents($this->config->userInfoUrl, false, $context);

        if ($response === false) {
            throw new SecurityException('Failed to fetch user info from provider.');
        }

        $decoded = json_decode($response, true);

        if (!is_array($decoded)) {
            throw new SecurityException('Invalid user info response from provider.');
        }

        return $decoded;
    }

    private function issueLocalJwt(array $claims): string
    {
        $now = time();

        $payload = [
            'iss' => $this->localAuthConfig->issuer,
            'aud' => $this->localAuthConfig->audience,
            'iat' => $now,
            'exp' => $now + $this->localAuthConfig->tokenLifetime,
            'sub' => $claims['sub'] ?? '',
            'username' => $claims['username'] ?? '',
            'email' => $claims['email'] ?? '',
            'entitlements' => $claims['entitlements'] ?? [],
            'provider' => $claims['provider'] ?? $this->config->name,
        ];

        return JWT::encode($payload, $this->localAuthConfig->signingKey, $this->localAuthConfig->algorithm);
    }
}
