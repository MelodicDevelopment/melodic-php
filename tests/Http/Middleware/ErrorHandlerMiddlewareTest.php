<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Melodic\Error\ExceptionHandler;
use Melodic\Http\Exception\NotFoundException;
use Melodic\Http\Middleware\ErrorHandlerMiddleware;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\NullLogger;
use PHPUnit\Framework\TestCase;

final class ErrorHandlerMiddlewareTest extends TestCase
{
    private function makeMiddleware(bool $debug = false): ErrorHandlerMiddleware
    {
        $handler = new ExceptionHandler(new NullLogger());
        $handler->setDebug($debug);

        return new ErrorHandlerMiddleware($handler);
    }

    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],
            body: [],
            headers: [],
        );
    }

    private function makeSuccessHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response(200, 'success');
            }
        };
    }

    private function makeThrowingHandler(\Throwable $exception): RequestHandlerInterface
    {
        return new class($exception) implements RequestHandlerInterface {
            public function __construct(private readonly \Throwable $exception) {}

            public function handle(Request $request): Response
            {
                throw $this->exception;
            }
        };
    }

    public function testPassesThroughWhenNoException(): void
    {
        $middleware = $this->makeMiddleware();
        $request = $this->makeRequest();
        $handler = $this->makeSuccessHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('success', $response->getBody());
    }

    public function testCatchesHttpExceptionAndReturnsErrorResponse(): void
    {
        $middleware = $this->makeMiddleware();
        $request = $this->makeRequest();
        $handler = $this->makeThrowingHandler(new NotFoundException('Resource not found'));

        $response = $middleware->process($request, $handler);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCatchesGenericExceptionAndReturns500(): void
    {
        $middleware = $this->makeMiddleware();
        $request = $this->makeRequest();
        $handler = $this->makeThrowingHandler(new \RuntimeException('Something went wrong'));

        $response = $middleware->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testDebugModeIncludesExceptionDetailsInJsonResponse(): void
    {
        $middleware = $this->makeMiddleware(debug: true);
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/test'],
            query: [],
            body: [],
            headers: ['Accept' => 'application/json'],
        );
        $handler = $this->makeThrowingHandler(new \RuntimeException('Debug error'));

        $response = $middleware->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('Debug error', $body['error']);
        $this->assertSame('RuntimeException', $body['exception']);
        $this->assertArrayHasKey('file', $body);
        $this->assertArrayHasKey('line', $body);
        $this->assertArrayHasKey('trace', $body);
    }

    public function testNonDebugModeHidesInternalErrorMessage(): void
    {
        $middleware = $this->makeMiddleware(debug: false);
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/test'],
            query: [],
            body: [],
            headers: ['Accept' => 'application/json'],
        );
        $handler = $this->makeThrowingHandler(new \RuntimeException('Secret details'));

        $response = $middleware->process($request, $handler);

        $this->assertSame(500, $response->getStatusCode());
        $body = json_decode($response->getBody(), true);
        $this->assertSame('An internal server error occurred.', $body['error']);
        $this->assertArrayNotHasKey('exception', $body);
    }

    public function testReturnsHtmlResponseForNonApiRequest(): void
    {
        $middleware = $this->makeMiddleware();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/page'],
            query: [],
            body: [],
            headers: [],
        );
        $handler = $this->makeThrowingHandler(new NotFoundException());

        $response = $middleware->process($request, $handler);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertStringContainsString('404', $response->getBody());
    }

    public function testRegisteredExceptionMapperIsInvoked(): void
    {
        $exceptionHandler = new ExceptionHandler(new NullLogger());
        $exceptionHandler->registerMapper(\RuntimeException::class, function (\Throwable $e) {
            return new Response(418, 'mapped: ' . $e->getMessage());
        });

        $middleware = new ErrorHandlerMiddleware($exceptionHandler);
        $request = $this->makeRequest();
        $handler = $this->makeThrowingHandler(new \RuntimeException('boom'));

        $response = $middleware->process($request, $handler);

        $this->assertSame(418, $response->getStatusCode());
        $this->assertSame('mapped: boom', $response->getBody());
    }
}
