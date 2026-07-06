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
        ?OidcProvider $oidcProvider = null,
    ) {
        $providerCacheDir = rtrim($cacheDir, '/') . '/' . $this->config->name;
        $this->oidcProvider = $oidcProvider ?? new OidcProvider($this->config->discoveryUrl, $providerCacheDir);
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
        // OIDC nonce binds the id_token to this login attempt: a captured or
        // replayed id_token carries the wrong (or no) nonce and is rejected.
        $nonce = OAuthClient::generateState();

        $session->set("melodic_oauth_state_{$this->config->name}", $state);
        $session->set("melodic_oauth_verifier_{$this->config->name}", $codeVerifier);
        $session->set("melodic_oauth_nonce_{$this->config->name}", $nonce);

        $codeChallenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        $params = [
            'response_type' => 'code',
            'client_id' => $this->config->clientId,
            'redirect_uri' => $this->config->redirectUri,
            'scope' => $this->config->scopes ?: 'openid profile email',
            'state' => $state,
            'nonce' => $nonce,
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
        $savedNonce = $session->get("melodic_oauth_nonce_{$this->config->name}");

        $session->remove("melodic_oauth_state_{$this->config->name}");
        $session->remove("melodic_oauth_verifier_{$this->config->name}");
        $session->remove("melodic_oauth_nonce_{$this->config->name}");

        if ($savedState === null || !hash_equals((string) $savedState, (string) $state)) {
            throw new SecurityException('Invalid OAuth state parameter.');
        }

        if ($codeVerifier === null) {
            throw new SecurityException('Missing PKCE code verifier.');
        }

        $tokenResponse = $this->exchangeCode((string) $code, (string) $codeVerifier);

        // Only the id_token is acceptable here: it is what validateToken()'s
        // issuer/audience/nonce checks are specified against. An access_token
        // may be an opaque string or a JWT minted for a different audience —
        // treating it as proof of identity invites token-confusion.
        $token = $tokenResponse['id_token'] ?? null;

        if ($token === null) {
            throw new SecurityException('No id_token received from authorization server.');
        }

        $claims = $this->validateToken($token);

        // Bind the id_token to the login attempt that requested it.
        $tokenNonce = $claims['nonce'] ?? null;

        if (
            $savedNonce === null
            || !is_string($tokenNonce)
            || !hash_equals((string) $savedNonce, $tokenNonce)
        ) {
            throw new SecurityException('Invalid token nonce.');
        }

        $claims['provider'] = $this->config->name;

        return new AuthResult(
            token: $token,
            claims: $claims,
            providerName: $this->config->name,
        );
    }

    /** @return array<string, mixed> */
    public function validateToken(string $token): array
    {
        try {
            $jwks = $this->oidcProvider->getJwks();
            // Pass a default algorithm: some providers (notably Microsoft Entra)
            // omit the per-key "alg" in their JWKS, which parseKeySet otherwise
            // rejects. Binding such keys to the configured alg also prevents
            // algorithm-confusion — JWT::decode still checks the token header alg
            // against the key.
            $keys = JWK::parseKeySet($jwks, $this->config->signingAlg);
            $claims = (array) JWT::decode($token, $keys);
        } catch (\Exception $e) {
            throw new SecurityException('Invalid token: ' . $e->getMessage(), 0, $e);
        }

        // Require an expiry. firebase/php-jwt only rejects expired tokens when
        // `exp` is present, so a token that omits it would otherwise never expire.
        if (!isset($claims['exp'])) {
            throw new SecurityException('Token is missing the required exp claim.');
        }

        // Bind the token to this provider's issuer. Without this, any token whose
        // signature verifies against this JWKS would be accepted regardless of
        // which issuer minted it (cross-provider / cross-tenant token confusion).
        $expectedIssuer = $this->oidcProvider->getIssuer();
        $tokenIssuer = $claims['iss'] ?? null;

        if ($tokenIssuer !== $expectedIssuer) {
            throw new SecurityException('Invalid token issuer.');
        }

        // Validate audience. Default to the configured client_id when no explicit
        // audience is set, so the audience check is never silently skipped.
        $expectedAudience = $this->config->audience !== ''
            ? $this->config->audience
            : $this->config->clientId;

        $tokenAudience = $claims['aud'] ?? null;

        if (is_array($tokenAudience)) {
            if (!in_array($expectedAudience, $tokenAudience, true)) {
                throw new SecurityException('Invalid token audience.');
            }
        } elseif ($tokenAudience !== $expectedAudience) {
            throw new SecurityException('Invalid token audience.');
        }

        return $claims;
    }

    /**
     * Exchange the authorization code for tokens. Protected so tests can stub
     * the network round-trip and drive the full callback path.
     *
     * @return array<string, mixed>
     */
    protected function exchangeCode(string $code, string $codeVerifier): array
    {
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

        return OAuthClient::requestJson(
            'POST',
            $this->oidcProvider->getTokenEndpoint(),
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            http_build_query($postFields),
        );
    }
}
