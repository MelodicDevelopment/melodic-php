<?php

declare(strict_types=1);

namespace Tests\Core;

use Melodic\Cache\ArrayCache;
use Melodic\Core\Configuration;
use Melodic\Data\DbContext;
use Melodic\Data\Migration\Migrator;
use Melodic\Data\Model;
use Melodic\Data\ModelBindingException;
use Melodic\Http\HttpMethod;
use Melodic\Http\Middleware\RateLimitMiddleware;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Routing\Router;
use Melodic\Security\AuthorizationMiddleware;
use Melodic\Security\AuthProviderConfig;
use Melodic\Security\AuthProviderType;
use Melodic\Security\ClaimMapper;
use Melodic\Security\LocalAuthConfig;
use Melodic\Security\OAuth2AuthProvider;
use Melodic\Security\OAuthClient;
use Melodic\Security\OidcProvider;
use Melodic\Security\SecurityException;
use Melodic\Security\SessionManager;
use Melodic\Security\User;
use Melodic\Security\UserContext;
use Melodic\Validation\Validator;
use PDO;
use PHPUnit\Framework\TestCase;

enum ReviewRole: string
{
    case Admin = 'admin';
}

class ReviewAddress extends Model
{
    public ?string $Street = null;
}

class ReviewOrder extends Model
{
    public ?ReviewAddress $Address;
}

class ReviewTypoModel extends Model
{
    #[Requierd]
    public ?string $Name = null;
}

class CountingOidcProvider extends OidcProvider
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

/**
 * Regression tests for the findings of the 4.1.0 pre-release review.
 */
class ReleaseReviewFixesTest extends TestCase
{
    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response(200, 'ok');
            }
        };
    }

    /** @param array<string, mixed> $attributes */
    private function authorize(array $attributes, string ...$userEntitlements): int
    {
        $request = (new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/x']))
            ->withAttribute('userContext', new UserContext(new User('1', 'u', 'u@example.com', $userEntitlements)))
            ->withAttribute('route.attributes', $attributes);

        return (new AuthorizationMiddleware())->process($request, $this->handler())->getStatusCode();
    }

    public function testDecodedRouteParamMustSatisfyItsConstraint(): void
    {
        $router = new Router();
        $router->get('/docs/{name}', 'C', 'doc');
        $router->get('/safe/{name:[^/.]+}', 'C', 'safe');
        $router->get('/tree/{path:.+}', 'C', 'tree');

        // An encoded slash cannot smuggle "../" into a one-segment parameter.
        $this->assertNull($router->match(HttpMethod::GET, '/docs/..%2F..%2Fetc%2Fpasswd'));
        $this->assertNull($router->match(HttpMethod::GET, '/safe/%2E%2E%2Fsecret'));
        $this->assertNull($router->match(HttpMethod::GET, '/safe/a%2Eb'));

        // Ordinary encoded characters still decode.
        $this->assertSame(['name' => 'John Doe'], $router->match(HttpMethod::GET, '/docs/John%20Doe')['params']);

        // A constraint that allows slashes opts in to them.
        $this->assertSame(['path' => 'a/b c'], $router->match(HttpMethod::GET, '/tree/a%2Fb%20c')['params']);
    }

    public function testRouteConstraintMayUseBraceQuantifier(): void
    {
        $router = new Router();
        $router->get('/y/{year:\d{4}}/{slug}', 'C', 'archive', name: 'archive');

        $this->assertSame(['year' => '2024', 'slug' => 'hi'], $router->match(HttpMethod::GET, '/y/2024/hi')['params']);
        $this->assertNull($router->match(HttpMethod::GET, '/y/24/hi'));
        $this->assertSame('/y/2024/hi', $router->url('archive', ['year' => 2024, 'slug' => 'hi']));
    }

    public function testRouteAttributeListsReplaceGroupLists(): void
    {
        $router = new Router();
        $router->group('/admin', function (Router $r): void {
            $r->get('/a', 'C', 'a');
            $r->get('/b', 'C', 'b', attributes: ['entitlements' => ['editor'], 'meta' => ['x' => 2]]);
            $r->get('/c', 'C', 'c', attributes: ['entitlements' => []]);
        }, attributes: ['entitlements' => ['admin', 'manager'], 'meta' => ['x' => 1, 'y' => 1]]);

        [$a, $b, $c] = $router->getRoutes();

        $this->assertSame(['admin', 'manager'], $a->attributes['entitlements']);
        $this->assertSame(['editor'], $b->attributes['entitlements']);
        $this->assertSame(['x' => 2, 'y' => 1], $b->attributes['meta']);
        // An empty list never removes the group's requirement.
        $this->assertSame(['admin', 'manager'], $c->attributes['entitlements']);
    }

    public function testExplicitPatchRouteTakesPrecedenceOverTheApiResourceOne(): void
    {
        $router = new Router();
        $router->group('/api', function (Router $r): void {
            $r->apiResource('/users', 'A');
            $r->apiResource('/users', 'B');
            $r->patch('/users/{userId:\d+}', 'C', 'patch');
            $r->apiResource('/posts', 'P');
            $r->patch('/posts/{action:publish|archive}', 'P', 'toggle');
        });

        // The app's own PATCH route wins although it was registered later.
        $this->assertSame('patch', $router->match(HttpMethod::PATCH, '/api/users/5')['route']->action);
        // Values it does not match still reach the generated route.
        $this->assertSame('update', $router->match(HttpMethod::PATCH, '/api/users/abc')['route']->action);
        $this->assertSame('toggle', $router->match(HttpMethod::PATCH, '/api/posts/publish')['route']->action);
        $this->assertSame('update', $router->match(HttpMethod::PATCH, '/api/posts/5')['route']->action);

        // Nothing else is displaced: every generated route is still there.
        $this->assertSame('update', $router->match(HttpMethod::PUT, '/api/users/5')['route']->action);
        $this->assertSame('A', $router->match(HttpMethod::DELETE, '/api/users/5')['route']->controller);
        $this->assertCount(20, $router->getRoutes());
        $this->assertContains('PATCH', $router->allowedMethodsForPath('/api/users/5'));
    }

    public function testNullCannotSwitchOffAGroupEntitlementList(): void
    {
        $router = new Router();
        $router->group('/admin', function (Router $r): void {
            $r->get('/x', 'C', 'x', attributes: ['entitlements' => null]);
        }, attributes: ['entitlements' => ['admin']]);

        $this->assertSame(['admin'], $router->getRoutes()[0]->attributes['entitlements']);
    }

    public function testListWhereANestedModelIsExpectedIsABindingError(): void
    {
        try {
            ReviewOrder::fromArray(['Address' => ['x']]);
            $this->fail('Expected a binding error');
        } catch (ModelBindingException $e) {
            $this->assertSame('Address', $e->field);
        }

        // A top-level list names no properties; it must not raise a TypeError.
        $this->assertFalse(isset(ReviewOrder::fromArray(['a', 'b'])->Address));
        $this->assertSame('Main St', ReviewOrder::fromArray(['Address' => ['Street' => 'Main St']])->Address?->Street);
    }

    public function testUnresolvableValidationAttributeIsReported(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Requierd');
        (new Validator())->validate(new ReviewTypoModel());
    }

    public function testEntitlementsAcceptBackedEnumsAndRejectOtherTypes(): void
    {
        $this->assertSame(403, $this->authorize(['entitlements' => [ReviewRole::Admin]], 'viewer'));
        $this->assertSame(200, $this->authorize(['entitlements' => [ReviewRole::Admin]], 'admin'));
        $this->assertSame(403, $this->authorize(['entitlements' => ReviewRole::Admin], 'viewer'));
        $this->assertSame(200, $this->authorize(['entitlements' => 'admin'], 'admin'));

        $this->expectException(\InvalidArgumentException::class);
        $this->authorize(['entitlements' => [1, 2]], 'viewer');
    }

    public function testConfigPlaceholdersNeverResolveFromRequestHeaders(): void
    {
        $file = sys_get_temp_dir() . '/melodic_review_' . uniqid() . '.json';
        file_put_contents($file, json_encode([
            'timeout' => '${HTTP_TIMEOUT:-30}',
            'proxy' => '${HTTP_PROXY}',
            'server' => '${MELODIC_T_SERVER_ONLY:-safe}',
            'real' => '${MELODIC_T_REAL}',
        ]));
        putenv('HTTP_TIMEOUT=99999');
        putenv('MELODIC_T_REAL=yes');
        $_SERVER['HTTP_PROXY'] = 'http://evil.test';
        $_SERVER['MELODIC_T_SERVER_ONLY'] = 'from-request';

        try {
            $config = new Configuration();
            $config->loadFile($file);
        } finally {
            putenv('HTTP_TIMEOUT');
            putenv('MELODIC_T_REAL');
            unset($_SERVER['HTTP_PROXY'], $_SERVER['MELODIC_T_SERVER_ONLY']);
            unlink($file);
        }

        $this->assertSame('30', $config->get('timeout'));
        $this->assertSame('${HTTP_PROXY}', $config->get('proxy'));
        $this->assertSame('safe', $config->get('server'));
        $this->assertSame('yes', $config->get('real'));
    }

    public function testRateLimitersWithDifferentSettingsKeepSeparateCounters(): void
    {
        $cache = new ArrayCache();
        $global = new RateLimitMiddleware($cache, 1000, 3600);
        $posts = new RateLimitMiddleware($cache, 2, 60, methods: ['POST']);

        $get = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '198.51.100.9']);
        $post = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/', 'REMOTE_ADDR' => '198.51.100.9']);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $global->process($get, $this->handler())->getStatusCode());
        }

        $first = $posts->process($post, $this->handler());
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame('1', $first->getHeader('X-RateLimit-Remaining'));
        $this->assertSame(200, $posts->process($post, $this->handler())->getStatusCode());
        $this->assertSame(429, $posts->process($post, $this->handler())->getStatusCode());
        $this->assertSame(200, $global->process($get, $this->handler())->getStatusCode());
    }

    public function testFailedJwksRefreshIsThrottledAndLeavesAFreshCacheAlone(): void
    {
        $dir = sys_get_temp_dir() . '/melodic_oidc_review_' . uniqid();
        $discoveryUrl = 'https://idp.test/.well-known/openid-configuration';
        $provider = new CountingOidcProvider($discoveryUrl, $dir, cacheTtl: 3600);
        $provider->documents = [
            $discoveryUrl => ['issuer' => 'https://idp.test', 'jwks_uri' => 'https://idp.test/jwks'],
            'https://idp.test/jwks' => ['keys' => [['kid' => 'one']]],
        ];
        $jwksFile = $dir . '/oidc_jwks.json';

        try {
            $provider->getJwks();
            $this->assertSame(2, $provider->fetches);

            // Ten minutes old: inside the TTL, outside the refresh throttle.
            touch($jwksFile, time() - 600);
            $provider->fail = true;

            $this->assertSame('one', $provider->refreshJwks()['keys'][0]['kid']);
            $this->assertSame(3, $provider->fetches);

            // Further unknown-kid tokens do not hit the failing provider again.
            $provider->refreshJwks();
            $provider->refreshJwks();
            $this->assertSame(3, $provider->fetches);

            // The failed refresh did not re-date the still-valid cache entry.
            clearstatcache();
            $this->assertEqualsWithDelta(time() - 600, filemtime($jwksFile), 5);
            $provider->getJwks();
            $this->assertSame(3, $provider->fetches);
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            @rmdir($dir);
        }
    }

    public function testRollbackRefusesAnEmptyDownSection(): void
    {
        $dir = sys_get_temp_dir() . '/melodic_review_migrations_' . uniqid();
        mkdir($dir);
        // What make:migration writes, with only the "up" part filled in.
        file_put_contents($dir . '/20260101000000_create_users.sql', "-- Migration: create_users\n\nCREATE TABLE users (Id INTEGER);\n\n-- @down\n\n");

        $db = new DbContext(new PDO('sqlite::memory:'));
        $migrator = new Migrator($db, $dir);

        try {
            $migrator->migrate();

            try {
                $migrator->rollback();
                $this->fail('Expected rollback with an empty @down section to fail');
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('@down', $e->getMessage());
            }

            // Still recorded as applied, and the table is still there.
            $this->assertTrue($migrator->status()[0]['applied']);
            $this->assertSame(0, (int) $db->scalar('SELECT COUNT(*) FROM users'));
        } finally {
            array_map('unlink', glob($dir . '/*') ?: []);
            rmdir($dir);
        }
    }

    public function testProviderCallbackErrorNeverEchoesAttackerText(): void
    {
        $this->assertSame('Sign-in failed (access_denied). Please try again.', OAuthClient::callbackErrorMessage('access_denied'));
        $this->assertSame('Sign-in failed. Please try again.', OAuthClient::callbackErrorMessage('Account locked. Call 555-0100'));

        $provider = new OAuth2AuthProvider(
            new AuthProviderConfig(
                name: 'acme',
                type: AuthProviderType::OAuth2,
                authorizeUrl: 'https://idp.example.com/authorize',
                tokenUrl: 'https://idp.example.com/token',
                userInfoUrl: 'https://idp.example.com/userinfo',
                clientId: 'client-123',
                clientSecret: 'secret',
                redirectUri: 'https://app.example.com/auth/callback',
                scopes: 'profile email',
            ),
            new LocalAuthConfig(signingKey: 'a-sufficiently-long-signing-secret-32+'),
            new ClaimMapper(),
        );

        foreach (['x', ['x']] as $error) {
            $request = new Request(
                server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/auth/callback/acme'],
                query: ['error' => $error, 'error_description' => 'Account locked. Call 555-0100'],
            );

            try {
                $provider->handleCallback($request, new SessionManager());
                $this->fail('Expected the provider error to be rejected');
            } catch (SecurityException $e) {
                $this->assertSame('Sign-in failed. Please try again.', $e->getMessage());
            }
        }
    }
}
