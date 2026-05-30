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
        $codeVerifier = OAuthClient::generateCodeVerifier();

        $session->set("melodic_oauth_state_{$this->config->name}", $state);
        $session->set("melodic_oauth_verifier_{$this->config->name}", $codeVerifier);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $params = [
            // response_type=code is required by the OAuth 2.0 authorization-code
            // flow; without it providers reject or mishandle the authorize request.
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => $this->config->scopes,
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
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
        $codeVerifier = $session->get("melodic_oauth_verifier_{$this->config->name}");

        $session->remove("melodic_oauth_state_{$this->config->name}");
        $session->remove("melodic_oauth_verifier_{$this->config->name}");

        if ($savedState === null || !hash_equals((string) $savedState, (string) $state)) {
            throw new SecurityException('Invalid OAuth state parameter.');
        }

        if ($codeVerifier === null) {
            throw new SecurityException('Missing PKCE code verifier.');
        }

        $accessToken = $this->exchangeCode((string) $code, (string) $codeVerifier);
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

    private function exchangeCode(string $code, string $codeVerifier): string
    {
        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->redirectUri,
            'client_id' => $this->config->clientId,
            'client_secret' => $this->config->clientSecret,
            'code_verifier' => $codeVerifier,
        ];

        $decoded = OAuthClient::requestJson(
            'POST',
            $this->config->tokenUrl,
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($postFields),
        );

        return $decoded['access_token']
            ?? throw new SecurityException('No access token received from authorization server.');
    }

    private function fetchUserInfo(string $accessToken): array
    {
        return OAuthClient::requestJson(
            'GET',
            $this->config->userInfoUrl,
            ['Authorization' => "Bearer {$accessToken}"],
        );
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
