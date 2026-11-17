<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\AuthConfig;
use Melodic\Security\AuthLoginRendererInterface;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\JwtValidator;
use Melodic\Security\LocalAuthConfig;
use Melodic\Security\OidcProvider;
use Melodic\Security\SecurityException;
use Melodic\Security\SessionManager;
use Melodic\Security\WebAuthenticationMiddleware;
use Firebase\JWT\JWT;
use PHPUnit\Framework\TestCase;

class MemorySessionManager extends SessionManager
{
    /** @var array<string, mixed> */
    public array $data = [];
    public bool $destroyed = false;

    public function start(): void {}
    public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
    public function set(string $key, mixed $value): void { $this->data[$key] = $value; }
    public function has(string $key): bool { return array_key_exists($key, $this->data); }
    public function remove(string $key): void { unset($this->data[$key]); }
    public function destroy(): void { $this->data = []; $this->destroyed = true; }
    public function regenerate(bool $deleteOld = true): void {}
    public function isStarted(): bool { return true; }
}

class FlowPassThroughHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new Response(200, 'passthrough');
    }
}

class RecordingLoginRenderer implements AuthLoginRendererInterface
{
    public ?string $error = null;

    public function render(?string $error = null, ?string $csrfToken = null): string
    {
        $this->error = $error;

        return 'login';
    }
}

class ScriptedOidcProvider extends OidcProvider
{
    public int $fetches = 0;
    public bool $fail = false;
    /** @var array<string, array<string, mixed>> */
    public array $documents = [];

    protected function fetchJson(string $url): array
    {
        $this->fetches++;

        if ($this->fail) {
            throw new SecurityException('down');
        }

        return $this->documents[$url];
    }
}

class AuthFlowFixesTest extends TestCase
{
    private const KEY = 'a-signing-key-that-is-at-least-32-chars-long';

    /** @param array<string, mixed> $query @param array<string, mixed> $cookies */
    private function request(string $uri, array $query = [], array $cookies = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri],
            query: $query,
            body: [],
            headers: [],
            cookies: $cookies,
            files: [],
        );
    }

    public function testLoginErrorTextComesFromSessionNotTheUrl(): void
    {
        $session = new MemorySessionManager();
        $renderer = new RecordingLoginRenderer();
        $middleware = new AuthCallbackMiddleware(new AuthConfig(), new AuthProviderRegistry(), $session, $renderer);
        $next = new FlowPassThroughHandler();

        // Attacker-crafted link: the text in the URL is never displayed.
        $middleware->process($this->request('/auth/login?error=Call+555-0100', ['error' => 'Call 555-0100']), $next);
        $this->assertSame('Sign-in failed. Please try again.', $renderer->error);

        // Array-valued parameter no longer raises a TypeError.
        $middleware->process($this->request('/auth/login', ['error' => ['x']]), $next);
        $this->assertSame('Sign-in failed. Please try again.', $renderer->error);

        // A message stored by a failed login is shown once.
        $session->set('melodic_login_error', 'Invalid username or password.');
        $middleware->process($this->request('/auth/login', ['error' => '1']), $next);
        $this->assertSame('Invalid username or password.', $renderer->error);
        $this->assertFalse($session->has('melodic_login_error'));

        $middleware->process($this->request('/auth/login'), $next);
        $this->assertNull($renderer->error);
    }

    public function testApplicationSecurityExceptionIsNotSwallowedAsBadToken(): void
    {
        $local = new LocalAuthConfig(self::KEY);
        $token = JWT::encode([
            'iss' => $local->issuer, 'aud' => $local->audience, 'exp' => time() + 60, 'sub' => '1',
        ], self::KEY, 'HS256');

        $middleware = new WebAuthenticationMiddleware(
            new AuthConfig(),
            new JwtValidator(new AuthProviderRegistry(), $local),
            new MemorySessionManager(),
        );

        $throwing = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                throw new SecurityException('raised by the application');
            }
        };

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('raised by the application');
        $middleware->process($this->request('/page', [], ['melodic_auth' => $token]), $throwing);
    }

    public function testUnauthenticatedRedirectRemembersQueryString(): void
    {
        $session = new MemorySessionManager();
        $middleware = new WebAuthenticationMiddleware(
            new AuthConfig(),
            new JwtValidator(new AuthProviderRegistry(), new LocalAuthConfig(self::KEY)),
            $session,
        );

        $response = $middleware->process($this->request('/reports?year=2026'), new FlowPassThroughHandler());

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/reports?year=2026', $session->get('melodic_redirect_after_login'));
    }

    public function testOidcProviderServesStaleCacheWhenProviderIsDownAndThrottlesRefresh(): void
    {
        $dir = sys_get_temp_dir() . '/melodic_oidc_test_' . uniqid();
        $discoveryUrl = 'https://idp.test/.well-known/openid-configuration';
        $provider = new ScriptedOidcProvider($discoveryUrl, $dir, cacheTtl: 3600);
        $provider->documents = [
            $discoveryUrl => ['issuer' => 'https://idp.test', 'jwks_uri' => 'https://idp.test/jwks'],
            'https://idp.test/jwks' => ['keys' => [['kid' => 'one']]],
        ];

        $this->assertSame('one', $provider->getJwks()['keys'][0]['kid']);
        $this->assertSame(2, $provider->fetches);

        // cached
        $provider->getJwks();
        $this->assertSame(2, $provider->fetches);

        // forced refresh is throttled while the cache is fresh
        $provider->refreshJwks();
        $this->assertSame(2, $provider->fetches);

        // once older than the throttle window, a refresh refetches
        touch($dir . '/oidc_jwks.json', time() - 120);
        $provider->documents['https://idp.test/jwks'] = ['keys' => [['kid' => 'two']]];
        $this->assertSame('two', $provider->refreshJwks()['keys'][0]['kid']);
        $this->assertSame(3, $provider->fetches);

        // expired cache + provider down → last good copy is served
        touch($dir . '/oidc_jwks.json', time() - 7200);
        $provider->fail = true;
        $this->assertSame('two', $provider->getJwks()['keys'][0]['kid']);

        $this->assertSame('0700', substr(sprintf('%o', fileperms($dir)), -4));

        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }
}
