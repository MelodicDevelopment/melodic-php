<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\RefreshToken;
use Melodic\Security\RefreshTokenConfig;
use Melodic\Security\RefreshTokenMiddleware;
use Melodic\Security\RefreshTokenRepositoryInterface;
use Melodic\Security\RefreshTokenService;
use PHPUnit\Framework\TestCase;

final class SpyLogger extends \Melodic\Log\NullLogger
{
    /** @var array<int, array{message: string, context: array<string, mixed>}> */
    public array $warnings = [];

    /** @param array<string, mixed> $context */
    public function warning(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }
}

final class RefreshTokenMiddlewareTest extends TestCase
{
    private FakeRefreshTokenRepository $repository;
    private RefreshTokenConfig $config;
    private RefreshTokenMiddleware $middleware;

    protected function setUp(): void
    {
        $this->repository = new FakeRefreshTokenRepository();
        $this->config = new RefreshTokenConfig(cookieName: 'test_refresh');
        $service = new RefreshTokenService($this->repository, $this->config);
        $this->middleware = new RefreshTokenMiddleware($service, $this->config);
    }

    public function testMissingCookieReturns401(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: [],
        );

        $handler = $this->createPassthroughHandler();
        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Refresh token required', $response->getBody());
    }

    public function testInvalidTokenReturns401WithGenericMessage(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: ['test_refresh' => 'invalid-token-value'],
        );

        $handler = $this->createPassthroughHandler();
        $response = $this->middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertStringContainsString('Authentication failed.', $response->getBody());
        $this->assertStringNotContainsString('Invalid refresh token', $response->getBody());
    }

    public function testReuseAndInvalidTokenAreIndistinguishableToClient(): void
    {
        $service = new RefreshTokenService($this->repository, $this->config);
        $result = $service->create(42);
        $stolen = $result['token'];

        // Rotate the token so presenting the original again counts as reuse.
        $service->rotate($service->validate($stolen));

        $handler = $this->createPassthroughHandler();

        $reuseResponse = $this->middleware->process(new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: ['test_refresh' => $stolen],
        ), $handler);

        $invalidResponse = $this->middleware->process(new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: ['test_refresh' => 'invalid-token-value'],
        ), $handler);

        $this->assertSame(401, $reuseResponse->getStatusCode());
        $this->assertSame(401, $invalidResponse->getStatusCode());
        $this->assertSame($invalidResponse->getBody(), $reuseResponse->getBody());
    }

    public function testRejectionReasonIsLoggedServerSide(): void
    {
        $logger = new SpyLogger();
        $service = new RefreshTokenService($this->repository, $this->config);
        $middleware = new RefreshTokenMiddleware($service, $this->config, $logger);

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: ['test_refresh' => 'invalid-token-value'],
        );

        $middleware->process($request, $this->createPassthroughHandler());

        $this->assertCount(1, $logger->warnings);
        $this->assertStringContainsString('Invalid refresh token', (string) $logger->warnings[0]['context']['reason']);
    }

    public function testValidTokenSetsAttributesAndPassesToHandler(): void
    {
        $service = new RefreshTokenService($this->repository, $this->config);
        $result = $service->create(42);

        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/auth/refresh'],
            cookies: ['test_refresh' => $result['token']],
        );

        $capturedRequest = null;
        $handler = new class ($capturedRequest) implements RequestHandlerInterface {
            public function __construct(private ?Request &$captured)
            {
            }

            public function handle(Request $request): Response
            {
                $this->captured = $request;

                return new Response(200, 'ok');
            }
        };

        $response = $this->middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($capturedRequest);
        $this->assertInstanceOf(RefreshToken::class, $capturedRequest->getAttribute('refreshToken'));
        $this->assertSame(42, $capturedRequest->getAttribute('refreshTokenUserId'));
    }

    private function createPassthroughHandler(): RequestHandlerInterface
    {
        return new class () implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response(200, 'ok');
            }
        };
    }
}
