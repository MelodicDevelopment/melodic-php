<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\HttpMethod;
use Melodic\Http\RedirectResponse;
use Melodic\Http\Request;
use Melodic\Security\AuthProviderConfig;
use Melodic\Security\AuthProviderType;
use Melodic\Security\OidcAuthProvider;
use Melodic\Security\OidcProvider;
use Melodic\Security\SecurityException;
use Melodic\Security\SessionManager;
use PHPUnit\Framework\TestCase;

/**
 * OidcProvider stub returning fixed endpoints/issuer so the login redirect and
 * callback validation can be exercised without any network/discovery round-trip.
 */
class FakeEndpointsOidcProvider extends OidcProvider
{
    public function __construct()
    {
        parent::__construct('https://unused.test/.well-known/openid-configuration', sys_get_temp_dir() . '/unused');
    }

    public function getAuthorizationEndpoint(): string
    {
        return 'https://idp.test/authorize';
    }

    public function getTokenEndpoint(): string
    {
        return 'https://idp.test/token';
    }

    public function getIssuer(): string
    {
        return 'https://issuer.test';
    }

    public function getJwks(): array
    {
        return ['keys' => []];
    }
}

/**
 * In-memory SessionManager so the state/PKCE round-trip can be asserted without
 * touching $_SESSION or starting a real PHP session.
 */
class ArraySessionManager extends SessionManager
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): void
    {
    }

    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }
}

/**
 * OidcAuthProvider with the network token-exchange stubbed out so the callback
 * path can run end-to-end: nonce verification, id_token requirement, claims.
 */
class StubExchangeOidcAuthProvider extends OidcAuthProvider
{
    /** @var array<string, mixed> */
    public array $tokenResponse = [];

    /** @var array<string, mixed> */
    public array $tokenClaims = [];

    protected function exchangeCode(string $code, string $codeVerifier): array
    {
        return $this->tokenResponse;
    }

    public function validateToken(string $token): array
    {
        return $this->tokenClaims;
    }
}

class OidcAuthProviderFlowTest extends TestCase
{
    private const PROVIDER = 'test';
    private const CLIENT_ID = 'client-xyz';
    private const REDIRECT = 'https://app.test/auth/callback/test';

    private OidcAuthProvider $provider;
    private ArraySessionManager $session;

    protected function setUp(): void
    {
        $config = new AuthProviderConfig(
            name: self::PROVIDER,
            type: AuthProviderType::Oidc,
            clientId: self::CLIENT_ID,
            redirectUri: self::REDIRECT,
        );

        $this->provider = new OidcAuthProvider(
            $config,
            sys_get_temp_dir() . '/unused',
            new FakeEndpointsOidcProvider(),
        );

        $this->session = new ArraySessionManager();
    }

    /** @param array<string, mixed> $query */
    private function callbackRequest(array $query): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/auth/callback/test'],
            query: $query,
        );
    }

    public function testLoginStoresStateAndVerifierAndBindsPkceChallenge(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/auth/login/test'],
        );

        $response = $this->provider->handleLogin($request, $this->session);

        $this->assertInstanceOf(RedirectResponse::class, $response);

        // The one-time state and PKCE verifier must be persisted server-side so the
        // callback can validate them.
        $state = $this->session->get('melodic_oauth_state_test');
        $verifier = $this->session->get('melodic_oauth_verifier_test');
        $this->assertIsString($state);
        $this->assertIsString($verifier);
        $this->assertNotSame('', $state);
        $this->assertNotSame('', $verifier);

        $location = $response->getHeaders()['Location'] ?? '';
        $this->assertStringStartsWith('https://idp.test/authorize?', $location);

        parse_str((string) parse_url($location, PHP_URL_QUERY), $params);

        $this->assertSame('code', $params['response_type']);
        $this->assertSame(self::CLIENT_ID, $params['client_id']);
        $this->assertSame(self::REDIRECT, $params['redirect_uri']);
        $this->assertSame('S256', $params['code_challenge_method']);
        $this->assertSame($state, $params['state']);

        // The challenge sent to the IdP must be the S256 transform of the verifier
        // we stored — this is the heart of PKCE; a mismatch silently breaks it.
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', (string) $verifier, true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $params['code_challenge']);
    }

    public function testCallbackThrowsOnProviderError(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('access_denied');

        $this->provider->handleCallback(
            $this->callbackRequest(['error' => 'access_denied', 'error_description' => 'access_denied']),
            $this->session,
        );
    }

    public function testCallbackThrowsWhenCodeMissing(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Missing authorization code');

        $this->provider->handleCallback(
            $this->callbackRequest(['state' => 'abc']),
            $this->session,
        );
    }

    public function testCallbackThrowsWhenStateMissing(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Missing authorization code');

        $this->provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code']),
            $this->session,
        );
    }

    public function testCallbackThrowsOnStateMismatch(): void
    {
        $this->session->set('melodic_oauth_state_test', 'the-real-state');
        $this->session->set('melodic_oauth_verifier_test', 'the-verifier');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('state');

        $this->provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'forged-state']),
            $this->session,
        );
    }

    public function testCallbackThrowsWhenVerifierMissing(): void
    {
        // State matches, but the PKCE verifier is absent from the session.
        $this->session->set('melodic_oauth_state_test', 'matching-state');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('PKCE code verifier');

        $this->provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'matching-state']),
            $this->session,
        );
    }

    public function testCallbackClearsOneTimeSessionValuesOnFailure(): void
    {
        $this->session->set('melodic_oauth_state_test', 'the-real-state');
        $this->session->set('melodic_oauth_verifier_test', 'the-verifier');

        try {
            $this->provider->handleCallback(
                $this->callbackRequest(['code' => 'auth-code', 'state' => 'forged-state']),
                $this->session,
            );
            $this->fail('Expected SecurityException was not thrown.');
        } catch (SecurityException) {
            // expected
        }

        // State and verifier are single-use: they must be cleared even when the
        // callback fails, so a replayed callback cannot reuse them.
        $this->assertFalse($this->session->has('melodic_oauth_state_test'));
        $this->assertFalse($this->session->has('melodic_oauth_verifier_test'));
        $this->assertFalse($this->session->has('melodic_oauth_nonce_test'));
    }

    public function testLoginStoresAndSendsNonce(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/auth/login/test'],
        );

        $response = $this->provider->handleLogin($request, $this->session);

        $nonce = $this->session->get('melodic_oauth_nonce_test');
        $this->assertIsString($nonce);
        $this->assertNotSame('', $nonce);

        parse_str((string) parse_url($response->getHeaders()['Location'], PHP_URL_QUERY), $params);
        $this->assertSame($nonce, $params['nonce']);
    }

    private function stubbedProvider(): StubExchangeOidcAuthProvider
    {
        return new StubExchangeOidcAuthProvider(
            new AuthProviderConfig(
                name: self::PROVIDER,
                type: AuthProviderType::Oidc,
                clientId: self::CLIENT_ID,
                redirectUri: self::REDIRECT,
            ),
            sys_get_temp_dir() . '/unused',
            new FakeEndpointsOidcProvider(),
        );
    }

    /** Prime the session as a real handleLogin would. */
    private function primeSession(string $state, string $nonce): void
    {
        $this->session->set('melodic_oauth_state_test', $state);
        $this->session->set('melodic_oauth_verifier_test', 'the-verifier');
        $this->session->set('melodic_oauth_nonce_test', $nonce);
    }

    public function testCallbackRejectsResponseWithoutIdToken(): void
    {
        $provider = $this->stubbedProvider();
        $provider->tokenResponse = ['access_token' => 'opaque-access-token'];
        $this->primeSession('the-state', 'the-nonce');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('No id_token');

        $provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'the-state']),
            $this->session,
        );
    }

    public function testCallbackRejectsIdTokenWithWrongNonce(): void
    {
        $provider = $this->stubbedProvider();
        $provider->tokenResponse = ['id_token' => 'jwt'];
        $provider->tokenClaims = ['sub' => 'user-1', 'nonce' => 'replayed-nonce'];
        $this->primeSession('the-state', 'the-nonce');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('nonce');

        $provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'the-state']),
            $this->session,
        );
    }

    public function testCallbackRejectsIdTokenWithoutNonce(): void
    {
        $provider = $this->stubbedProvider();
        $provider->tokenResponse = ['id_token' => 'jwt'];
        $provider->tokenClaims = ['sub' => 'user-1'];
        $this->primeSession('the-state', 'the-nonce');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('nonce');

        $provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'the-state']),
            $this->session,
        );
    }

    public function testCallbackSucceedsWithMatchingNonce(): void
    {
        $provider = $this->stubbedProvider();
        $provider->tokenResponse = ['id_token' => 'jwt'];
        $provider->tokenClaims = ['sub' => 'user-1', 'nonce' => 'the-nonce'];
        $this->primeSession('the-state', 'the-nonce');

        $result = $provider->handleCallback(
            $this->callbackRequest(['code' => 'auth-code', 'state' => 'the-state']),
            $this->session,
        );

        $this->assertSame('jwt', $result->token);
        $this->assertSame('user-1', $result->claims['sub']);
        $this->assertSame('test', $result->claims['provider']);
    }
}
