<?php

declare(strict_types=1);

namespace Tests\Error;

use Melodic\Error\ExceptionHandler;
use Melodic\Http\Exception\HttpException;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\NullLogger;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

class ExceptionHandlerTest extends TestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ExceptionHandler(new NullLogger());
    }

    private function makeRequest(
        string $method = 'GET',
        string $uri = '/',
        array $headers = [],
    ): Request {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],
            body: [],
            headers: $headers,
        );
    }

    // -------------------------------------------------------
    // JSON vs HTML detection
    // -------------------------------------------------------

    public function testReturnsJsonWhenAcceptHeaderContainsApplicationJson(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testReturnsJsonWhenContentTypeHeaderContainsApplicationJson(): void
    {
        $request = $this->makeRequest(headers: ['Content-Type' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testReturnsJsonWhenPathStartsWithApi(): void
    {
        $request = $this->makeRequest(uri: '/api/users');

        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
    }

    public function testReturnsHtmlForNonApiNonJsonRequest(): void
    {
        $request = $this->makeRequest(uri: '/home');

        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertNotInstanceOf(JsonResponse::class, $response);
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
    }

    // -------------------------------------------------------
    // Debug vs production mode
    // -------------------------------------------------------

    public function testProductionModeHidesExceptionDetailsInJson(): void
    {
        $this->handler->setDebug(false);
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('secret info'), $request);
        $data = json_decode($response->getBody(), true);

        $this->assertSame('An internal server error occurred.', $data['error']);
        $this->assertArrayNotHasKey('exception', $data);
        $this->assertArrayNotHasKey('file', $data);
        $this->assertArrayNotHasKey('line', $data);
        $this->assertArrayNotHasKey('trace', $data);
    }

    public function testDebugModeIncludesExceptionDetailsInJson(): void
    {
        $this->handler->setDebug(true);
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('debug info'), $request);
        $data = json_decode($response->getBody(), true);

        $this->assertSame('debug info', $data['error']);
        $this->assertSame('RuntimeException', $data['exception']);
        $this->assertArrayHasKey('file', $data);
        $this->assertArrayHasKey('line', $data);
        $this->assertArrayHasKey('trace', $data);
    }

    public function testProductionModeHidesExceptionDetailsInHtml(): void
    {
        $this->handler->setDebug(false);
        $request = $this->makeRequest(uri: '/home');

        $response = $this->handler->handle(new \RuntimeException('secret info'), $request);
        $body = $response->getBody();

        $this->assertStringContainsString('An internal server error occurred.', $body);
        $this->assertStringNotContainsString('secret info', $body);
        $this->assertStringNotContainsString('RuntimeException', $body);
    }

    public function testDebugModeIncludesExceptionDetailsInHtml(): void
    {
        $this->handler->setDebug(true);
        $request = $this->makeRequest(uri: '/home');

        $response = $this->handler->handle(new \RuntimeException('debug info'), $request);
        $body = $response->getBody();

        $this->assertStringContainsString('debug info', $body);
        $this->assertStringContainsString('RuntimeException', $body);
    }

    // -------------------------------------------------------
    // Status code mapping
    // -------------------------------------------------------

    public function testHttpExceptionMapsToItsStatusCode(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new HttpException(422, 'Invalid'), $request);

        $this->assertSame(422, $response->getStatusCode());
    }

    public function testSecurityExceptionMapsTo401(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new SecurityException('Denied'), $request);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testJsonExceptionMapsTo400(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \JsonException('Bad JSON'), $request);

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testGenericRuntimeExceptionMapsTo500(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testGenericExceptionMapsTo500(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \Exception('fail'), $request);

        $this->assertSame(500, $response->getStatusCode());
    }

    // -------------------------------------------------------
    // HttpException static factory methods
    // -------------------------------------------------------

    public function testHttpExceptionNotFoundFactory(): void
    {
        $exception = HttpException::notFound();

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testHttpExceptionForbiddenFactory(): void
    {
        $exception = HttpException::forbidden();

        $this->assertSame(403, $exception->getStatusCode());
        $this->assertSame('Forbidden', $exception->getMessage());
    }

    public function testHttpExceptionBadRequestFactory(): void
    {
        $exception = HttpException::badRequest();

        $this->assertSame(400, $exception->getStatusCode());
        $this->assertSame('Bad Request', $exception->getMessage());
    }

    public function testHttpExceptionMethodNotAllowedFactory(): void
    {
        $exception = HttpException::methodNotAllowed();

        $this->assertSame(405, $exception->getStatusCode());
        $this->assertSame('Method Not Allowed', $exception->getMessage());
    }

    public function testHttpExceptionFactoryWithCustomMessage(): void
    {
        $exception = HttpException::notFound('User not found');

        $this->assertSame(404, $exception->getStatusCode());
        $this->assertSame('User not found', $exception->getMessage());
    }

    // -------------------------------------------------------
    // Client error messages are shown in production mode
    // -------------------------------------------------------

    public function testClientErrorMessageShownInProductionMode(): void
    {
        $this->handler->setDebug(false);
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(HttpException::notFound('Resource missing'), $request);
        $data = json_decode($response->getBody(), true);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('Resource missing', $data['error']);
    }

    public function testHtmlStatusCodeIsCorrectForHttpException(): void
    {
        $request = $this->makeRequest(uri: '/home');

        $response = $this->handler->handle(HttpException::notFound(), $request);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('404', $response->getBody());
    }
}
