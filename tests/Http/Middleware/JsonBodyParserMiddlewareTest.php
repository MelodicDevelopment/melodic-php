<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Melodic\Http\Exception\BadRequestException;
use Melodic\Http\Middleware\JsonBodyParserMiddleware;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use PHPUnit\Framework\TestCase;

final class JsonBodyParserMiddlewareTest extends TestCase
{
    private JsonBodyParserMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new JsonBodyParserMiddleware();
    }

    private function makeHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public ?Request $receivedRequest = null;

            public function handle(Request $request): Response
            {
                $this->receivedRequest = $request;
                return new Response();
            }
        };
    }

    public function testParsesValidJsonBody(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'application/json'],
            rawBody: '{"name":"John","age":30}',
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $parsedBody = $handler->receivedRequest->getAttribute('parsedBody');
        $this->assertSame(['name' => 'John', 'age' => 30], $parsedBody);
    }

    public function testThrowsBadRequestOnInvalidJson(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'application/json'],
            rawBody: '{invalid json}',
        );
        $handler = $this->makeHandler();

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Invalid JSON in request body');

        $this->middleware->process($request, $handler);
    }

    public function testPassesThroughWhenNotJsonContentType(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'text/html'],
            rawBody: '{"name":"John"}',
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $this->assertNull($handler->receivedRequest->getAttribute('parsedBody'));
    }

    public function testPassesThroughWhenNoContentTypeHeader(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $this->assertNull($handler->receivedRequest->getAttribute('parsedBody'));
    }

    public function testSkipsParsedBodyWhenRawBodyIsEmpty(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'application/json'],
            rawBody: '',
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $this->assertNull($handler->receivedRequest->getAttribute('parsedBody'));
    }

    public function testHandlesJsonContentTypeWithCharset(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'application/json; charset=utf-8'],
            rawBody: '{"key":"value"}',
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $parsedBody = $handler->receivedRequest->getAttribute('parsedBody');
        $this->assertSame(['key' => 'value'], $parsedBody);
    }

    public function testHandlesJsonContentTypeCaseInsensitive(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'Application/JSON'],
            rawBody: '{"key":"value"}',
        );
        $handler = $this->makeHandler();

        $this->middleware->process($request, $handler);

        $parsedBody = $handler->receivedRequest->getAttribute('parsedBody');
        $this->assertSame(['key' => 'value'], $parsedBody);
    }
}
