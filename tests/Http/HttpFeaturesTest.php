<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Cache\ArrayCache;
use Melodic\Http\HttpMethod;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\RateLimitMiddleware;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Middleware\SecurityHeadersMiddleware;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Routing\Router;
use PHPUnit\Framework\TestCase;

class HttpFeaturesTest extends TestCase
{
    private function handler(?Response $response = null): RequestHandlerInterface
    {
        return new class($response ?? new Response(200, 'ok')) implements RequestHandlerInterface {
            public function __construct(private readonly Response $response) {}

            public function handle(Request $request): Response
            {
                return $this->response;
            }
        };
    }

    /**
     * @param array<string, mixed> $server
     * @param array<string, string> $headers
     */
    private function request(array $server = [], array $headers = []): Request
    {
        return new Request(
            server: $server + ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            query: [],
            body: [],
            headers: $headers,
            cookies: [],
            files: [],
        );
    }

    public function testPathParsingKeepsLeadingDoubleSlashSegments(): void
    {
        $this->assertSame('//evil/items', $this->request(['REQUEST_URI' => '//evil/items?x=1'])->path());
        $this->assertSame('/a/b', $this->request(['REQUEST_URI' => '/a/b?x=1#frag'])->path());
        $this->assertSame('/a', $this->request(['REQUEST_URI' => 'http://host/a?x'])->path());
    }

    public function testServerDerivedAccessorsSurviveWithAttribute(): void
    {
        $request = $this->request([
            'REQUEST_URI' => '/users?page=2',
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTPS' => 'on',
        ], ['Host' => 'Example.com'])->withAttribute('a', 1);

        $this->assertSame('203.0.113.9', $request->ip());
        $this->assertTrue($request->isSecure());
        $this->assertSame('https', $request->scheme());
        $this->assertSame('example.com', $request->host());
        $this->assertSame('page=2', $request->queryString());
        $this->assertSame('/users?page=2', $request->uri());
        $this->assertSame(1, $request->getAttribute('a'));
    }

    public function testWithAttributeDoesNotMutateOriginal(): void
    {
        $original = $this->request();
        $original->withAttribute('a', 1);

        $this->assertNull($original->getAttribute('a'));
    }

    public function testBearerSchemeIsCaseInsensitive(): void
    {
        $this->assertSame('abc', $this->request([], ['Authorization' => 'bearer abc'])->bearerToken());
        $this->assertNull($this->request([], ['Authorization' => 'Basic abc'])->bearerToken());
    }

    public function testRouteConstraintsNamesAndDecoding(): void
    {
        $router = new Router();
        $router->get('/users/{id:\d+}', 'C', 'show', name: 'users.show');
        $router->get('/files/{name}', 'C', 'file');

        $this->assertSame(['id' => '42'], $router->match(HttpMethod::GET, '/users/42')['params']);
        $this->assertNull($router->match(HttpMethod::GET, '/users/abc'));
        $this->assertSame(['name' => 'a b/c'], $router->match(HttpMethod::GET, '/files/a%20b%2Fc')['params']);
        $this->assertSame('/users/7?tab=posts', $router->url('users.show', ['id' => 7, 'tab' => 'posts']));

        $this->expectException(\InvalidArgumentException::class);
        $router->url('users.show');
    }

    public function testApiResourceRegistersPatch(): void
    {
        $router = new Router();
        $router->apiResource('/users', 'C');

        $match = $router->match(HttpMethod::PATCH, '/users/5');
        $this->assertSame('update', $match['route']->action);
    }

    public function testCorsAppendsToExistingVaryAndSetsItForDisallowedOrigins(): void
    {
        $cors = new CorsMiddleware(['allowedOrigins' => ['https://app.test']]);
        $inner = $this->handler(new Response(200, 'ok', ['Vary' => 'Accept-Encoding']));

        $allowed = $cors->process($this->request([], ['Origin' => 'https://app.test']), $inner);
        $this->assertSame('Accept-Encoding, Origin', $allowed->getHeader('Vary'));
        $this->assertSame('https://app.test', $allowed->getHeader('Access-Control-Allow-Origin'));

        $denied = $cors->process($this->request([], ['Origin' => 'https://evil.test']), $inner);
        $this->assertSame('Accept-Encoding, Origin', $denied->getHeader('Vary'));
        $this->assertNull($denied->getHeader('Access-Control-Allow-Origin'));
    }

    public function testRateLimitBlocksAfterMaxAttempts(): void
    {
        $limiter = new RateLimitMiddleware(new ArrayCache(), 2, 60, paths: ['/auth']);
        $request = $this->request(['REQUEST_URI' => '/auth/callback/local', 'REMOTE_ADDR' => '198.51.100.1']);

        $first = $limiter->process($request, $this->handler());
        $this->assertSame(200, $first->getStatusCode());
        $this->assertSame('1', $first->getHeader('X-RateLimit-Remaining'));
        $this->assertSame(200, $limiter->process($request, $this->handler())->getStatusCode());

        $blocked = $limiter->process($request, $this->handler());
        $this->assertSame(429, $blocked->getStatusCode());
        $this->assertNotNull($blocked->getHeader('Retry-After'));

        // other clients and other paths are unaffected
        $other = $this->request(['REQUEST_URI' => '/auth/callback/local', 'REMOTE_ADDR' => '198.51.100.2']);
        $this->assertSame(200, $limiter->process($other, $this->handler())->getStatusCode());
        $elsewhere = $this->request(['REQUEST_URI' => '/home', 'REMOTE_ADDR' => '198.51.100.1']);
        $this->assertSame(200, $limiter->process($elsewhere, $this->handler())->getStatusCode());
    }

    public function testSecurityHeadersAreAddedWithoutOverwriting(): void
    {
        $middleware = new SecurityHeadersMiddleware([
            'hsts' => 'max-age=31536000',
            'contentSecurityPolicy' => "default-src 'self'",
            'referrerPolicy' => null,
        ]);
        $inner = $this->handler(new Response(200, 'ok', ['X-Frame-Options' => 'DENY']));

        $plain = $middleware->process($this->request(), $inner);
        $this->assertSame('nosniff', $plain->getHeader('X-Content-Type-Options'));
        $this->assertSame('DENY', $plain->getHeader('X-Frame-Options'));
        $this->assertSame("default-src 'self'", $plain->getHeader('Content-Security-Policy'));
        $this->assertNull($plain->getHeader('Referrer-Policy'));
        $this->assertNull($plain->getHeader('Strict-Transport-Security'));

        $secure = $middleware->process($this->request(['HTTPS' => 'on']), $inner);
        $this->assertSame('max-age=31536000', $secure->getHeader('Strict-Transport-Security'));
    }
}
