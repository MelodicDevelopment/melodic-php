<?php

declare(strict_types=1);

namespace Melodic\Security;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

class OidcAuthProvider implements AuthProviderInterface
{
    private readonly OidcProvider $oidcProvider;

    public function __construct(
        private readonly AuthProviderConfig $config,
        string $cacheDir,
    ) {
        $providerCacheDir = rtrim($cacheDir, '/') . '/' . $this->config->name;
        $this->oidcProvider = new OidcProvider($this->config->discoveryUrl, $providerCacheDir);
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
        return AuthProviderType::Oidc;
    }

    public function getOidcProvider(): OidcProvider
    {
        return $this->oidcProvider;
    }

    public function handleLogin(Request $request, SessionManager $session): Response
    {
        $state = OAuthClient::generateState();
        $codeVerifier = OAuthClient::generateCodeVerifier();

        $session->set("melodic_oauth_state_{$this->config->name}", $state);
        $session->set("melodic_oauth_verifier_{$this->config->name}", $codeVerifier);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $params = [
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => $this->config->scopes ?: 'openid profile email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];

        if ($this->config->audience !== '') {
            $params['audience'] = $this->config->audience;
        }

        $authorizationUrl = $this->oidcProvider->getAuthorizationEndpoint() . '?' . http_build_query($params);

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

        $tokenResponse = $this->exchangeCode((string) $code, (string) $codeVerifier);

        $token = $tokenResponse['id_token'] ?? $tokenResponse['access_token'] ?? null;

        if ($token === null) {
            throw new SecurityException('No token received from authorization server.');
        }

        $claims = $this->validateToken($token);
        $claims['provider'] = $this->config->name;

        return new AuthResult(
            token: $token,
            claims: $claims,
            providerName: $this->config->name,
        );
    }

    public function validateToken(string $token): array
    {
        try {
            $jwks = $this->oidcProvider->getJwks();
            $keys = JWK::parseKeySet($jwks);
            $claims = (array) JWT::decode($token, $keys);
        } catch (\Exception $e) {
            throw new SecurityException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        if ($this->config->audience !== '') {
            $tokenAudience = $claims['aud'] ?? null;

            if (is_array($tokenAudience)) {
                if (!in_array($this->config->audience, $tokenAudience, true)) {
                    throw new SecurityException('Invalid token audience.');
                }
            } elseif ($tokenAudience !== $this->config->audience) {
                throw new SecurityException('Invalid token audience.');
            }
        }

        return $claims;
    }

    private function exchangeCode(string $code, string $codeVerifier): array
    {
        $tokenEndpoint = $this->oidcProvider->getTokenEndpoint();

        $postFields = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->config->redirectUri,
            'client_id' => $this->config->clientId,
            'code_verifier' => $codeVerifier,
        ];

        if ($this->config->clientSecret !== '') {
            $postFields['client_secret'] = $this->config->clientSecret;
        }

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
}
