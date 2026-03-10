<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use PHPUnit\Framework\TestCase;

final class CorsMiddlewareTest extends TestCase
{
    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],
            body: [],
            headers: [],
        );
    }

    private function makeHandler(int $statusCode = 200, string $body = 'OK'): RequestHandlerInterface
    {
        return new class($statusCode, $body) implements RequestHandlerInterface {
            public function __construct(
                private readonly int $statusCode,
                private readonly string $body,
            ) {}

            public function handle(Request $request): Response
            {
                return new Response($this->statusCode, $this->body);
            }
        };
    }

    public function testDefaultConfigSetsExpectedValues(): void
    {
        $middleware = new CorsMiddleware();
        $request = $this->makeRequest();
        $handler = $this->makeHandler();

        $response = $middleware->process($request, $handler);

        $headers = $response->getHeaders();
        $this->assertSame('*', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST, PUT, DELETE, PATCH, OPTIONS', $headers['Access-Control-Allow-Methods']);
        $this->assertSame('Content-Type, Authorization', $headers['Access-Control-Allow-Headers']);
        $this->assertSame('86400', $headers['Access-Control-Max-Age']);
    }

    public function testCustomConfigOverridesDefaults(): void
    {
        $middleware = new CorsMiddleware([
            'allowedOrigins' => ['https://example.com'],
            'allowedMethods' => ['GET', 'POST'],
            'allowedHeaders' => ['X-Custom-Header'],
            'maxAge' => 3600,
        ]);
        $request = $this->makeRequest();
        $handler = $this->makeHandler();

        $response = $middleware->process($request, $handler);

        $headers = $response->getHeaders();
        $this->assertSame('https://example.com', $headers['Access-Control-Allow-Origin']);
        $this->assertSame('GET, POST', $headers['Access-Control-Allow-Methods']);
        $this->assertSame('X-Custom-Header', $headers['Access-Control-Allow-Headers']);
        $this->assertSame('3600', $headers['Access-Control-Max-Age']);
    }

    public function testOptionsRequestReturnsPreflight204(): void
    {
        $middleware = new CorsMiddleware();
        $request = $this->makeRequest('OPTIONS');
        $handler = $this->makeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Methods', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Allow-Headers', $response->getHeaders());
        $this->assertArrayHasKey('Access-Control-Max-Age', $response->getHeaders());
    }

    public function testOptionsRequestDoesNotCallHandler(): void
    {
        $middleware = new CorsMiddleware();
        $request = $this->makeRequest('OPTIONS');

        $called = false;
        $handler = new class($called) implements RequestHandlerInterface {
            public function __construct(private bool &$called) {}

            public function handle(Request $request): Response
            {
                $this->called = true;
                return new Response();
            }
        };

        $middleware->process($request, $handler);

        $this->assertFalse($called);
    }

    public function testRegularRequestPassesThroughToHandler(): void
    {
        $middleware = new CorsMiddleware();
        $request = $this->makeRequest('GET');
        $handler = $this->makeHandler(200, 'handler body');

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('handler body', $response->getBody());
        $this->assertArrayHasKey('Access-Control-Allow-Origin', $response->getHeaders());
    }

    public function testMultipleOriginsJoinedWithComma(): void
    {
        $middleware = new CorsMiddleware([
            'allowedOrigins' => ['https://a.com', 'https://b.com'],
        ]);
        $request = $this->makeRequest();
        $handler = $this->makeHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame('https://a.com, https://b.com', $response->getHeaders()['Access-Control-Allow-Origin']);
    }
}
