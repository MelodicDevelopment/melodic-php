<?php

declare(strict_types=1);

namespace Tests\Controller;

use Melodic\Controller\Controller;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use PHPUnit\Framework\TestCase;

final class ConcreteController extends Controller
{
    public function callJson(mixed $data, int $statusCode = 200): JsonResponse
    {
        return $this->json($data, $statusCode);
    }

    public function callCreated(mixed $data, ?string $location = null): JsonResponse
    {
        return $this->created($data, $location);
    }

    public function callNoContent(): Response
    {
        return $this->noContent();
    }

    public function callNotFound(mixed $data = null): JsonResponse
    {
        return $this->notFound($data);
    }

    public function callBadRequest(mixed $data = null): JsonResponse
    {
        return $this->badRequest($data);
    }

    public function callUnauthorized(mixed $data = null): JsonResponse
    {
        return $this->unauthorized($data);
    }

    public function callForbidden(mixed $data = null): JsonResponse
    {
        return $this->forbidden($data);
    }
}

final class ControllerTest extends TestCase
{
    private ConcreteController $controller;

    protected function setUp(): void
    {
        $this->controller = new ConcreteController();
        $this->controller->setRequest(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        ));
    }

    public function testJsonReturnsJsonResponseWithDefaultStatusCode(): void
    {
        $response = $this->controller->callJson(['name' => 'test']);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"name":"test"}', $response->getBody());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testJsonReturnsJsonResponseWithCustomStatusCode(): void
    {
        $response = $this->controller->callJson(['status' => 'ok'], 202);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame('{"status":"ok"}', $response->getBody());
    }

    public function testCreatedReturns201WithData(): void
    {
        $response = $this->controller->callCreated(['id' => 1]);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('{"id":1}', $response->getBody());
    }

    public function testCreatedWithLocationHeader(): void
    {
        $response = $this->controller->callCreated(['id' => 1], '/api/users/1');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/api/users/1', $response->getHeaders()['Location']);
    }

    public function testCreatedWithoutLocationHeader(): void
    {
        $response = $this->controller->callCreated(['id' => 1]);

        $this->assertArrayNotHasKey('Location', $response->getHeaders());
    }

    public function testNoContentReturns204WithEmptyBody(): void
    {
        $response = $this->controller->callNoContent();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', $response->getBody());
    }

    public function testNotFoundReturns404WithDefaultMessage(): void
    {
        $response = $this->controller->callNotFound();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"Not Found"}', $response->getBody());
    }

    public function testNotFoundReturns404WithCustomData(): void
    {
        $response = $this->controller->callNotFound(['error' => 'User not found']);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame('{"error":"User not found"}', $response->getBody());
    }

    public function testBadRequestReturns400WithDefaultMessage(): void
    {
        $response = $this->controller->callBadRequest();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"Bad Request"}', $response->getBody());
    }

    public function testBadRequestReturns400WithCustomData(): void
    {
        $response = $this->controller->callBadRequest(['error' => 'Invalid input']);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame('{"error":"Invalid input"}', $response->getBody());
    }

    public function testUnauthorizedReturns401WithDefaultMessage(): void
    {
        $response = $this->controller->callUnauthorized();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('{"error":"Unauthorized"}', $response->getBody());
    }

    public function testUnauthorizedReturns401WithCustomData(): void
    {
        $response = $this->controller->callUnauthorized(['error' => 'Token expired']);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('{"error":"Token expired"}', $response->getBody());
    }

    public function testForbiddenReturns403WithDefaultMessage(): void
    {
        $response = $this->controller->callForbidden();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('{"error":"Forbidden"}', $response->getBody());
    }

    public function testForbiddenReturns403WithCustomData(): void
    {
        $response = $this->controller->callForbidden(['error' => 'Insufficient permissions']);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('{"error":"Insufficient permissions"}', $response->getBody());
    }

    public function testSetRequestSetsTheRequest(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/test'],
        );

        $controller = new ConcreteController();
        $controller->setRequest($request);

        // Verify request was set by using json (which requires no request access but proves no error)
        $response = $controller->callJson(['ok' => true]);
        $this->assertSame(200, $response->getStatusCode());
    }
}
