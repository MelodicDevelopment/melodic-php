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

    // -------------------------------------------------------
    // Custom exception mappers
    // -------------------------------------------------------

    public function testRegisteredMapperReturnsCustomResponse(): void
    {
        $this->handler->registerMapper(\RuntimeException::class, function (\Throwable $e) {
            return new JsonResponse(['code' => 'custom', 'message' => $e->getMessage()], 402);
        });

        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $response = $this->handler->handle(new \RuntimeException('limit exceeded'), $request);

        $this->assertSame(402, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('custom', $data['code']);
        $this->assertSame('limit exceeded', $data['message']);
    }

    public function testMapperReceivesThrowableAndRequest(): void
    {
        $captured = null;
        $this->handler->registerMapper(\RuntimeException::class, function (\Throwable $e, Request $r) use (&$captured) {
            $captured = ['exception' => $e, 'request' => $r];
            return new JsonResponse(['ok' => true], 200);
        });

        $request = $this->makeRequest(uri: '/api/widgets', headers: ['Accept' => 'application/json']);
        $exception = new \RuntimeException('boom');

        $this->handler->handle($exception, $request);

        $this->assertSame($exception, $captured['exception']);
        $this->assertSame($request, $captured['request']);
    }

    public function testMapperDispatchesViaIsAForSubclasses(): void
    {
        $this->handler->registerMapper(\RuntimeException::class, function (\Throwable $e) {
            return new JsonResponse(['mapped' => 'parent'], 418);
        });

        // \LogicException is NOT a subclass of \RuntimeException — should fall through
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);
        $logicResponse = $this->handler->handle(new \LogicException('logic'), $request);
        $this->assertSame(500, $logicResponse->getStatusCode());

        // \OutOfBoundsException IS a subclass of \RuntimeException — should match
        $subResponse = $this->handler->handle(new \OutOfBoundsException('out'), $request);
        $this->assertSame(418, $subResponse->getStatusCode());
        $data = json_decode($subResponse->getBody(), true);
        $this->assertSame('parent', $data['mapped']);
    }

    public function testFirstRegisteredMatchingMapperWins(): void
    {
        $this->handler->registerMapper(\RuntimeException::class, function () {
            return new JsonResponse(['mapped' => 'first'], 418);
        });
        $this->handler->registerMapper(\OutOfBoundsException::class, function () {
            return new JsonResponse(['mapped' => 'second'], 422);
        });

        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);
        $response = $this->handler->handle(new \OutOfBoundsException('out'), $request);

        $this->assertSame(418, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('first', $data['mapped']);
    }

    public function testMapperFallthroughPreservesDefaultBehavior(): void
    {
        $this->handler->registerMapper(\OutOfBoundsException::class, function () {
            return new JsonResponse(['mapped' => true], 418);
        });

        // Throw a \RuntimeException — no mapper for it
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);
        $response = $this->handler->handle(new \RuntimeException('fail'), $request);

        $this->assertSame(500, $response->getStatusCode());
    }

    public function testNoMappersBehavesIdenticallyToDefault(): void
    {
        $request = $this->makeRequest(headers: ['Accept' => 'application/json']);

        $http = $this->handler->handle(new HttpException(404, 'gone'), $request);
        $this->assertSame(404, $http->getStatusCode());

        $sec = $this->handler->handle(new SecurityException('denied'), $request);
        $this->assertSame(401, $sec->getStatusCode());

        $json = $this->handler->handle(new \JsonException('bad'), $request);
        $this->assertSame(400, $json->getStatusCode());

        $generic = $this->handler->handle(new \RuntimeException('boom'), $request);
        $this->assertSame(500, $generic->getStatusCode());
    }
}
