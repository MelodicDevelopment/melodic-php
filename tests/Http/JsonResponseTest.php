<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testBodyIsJsonEncoded(): void
    {
        $response = new JsonResponse(['key' => 'value']);

        $this->assertSame('{"key":"value"}', $response->getBody());
    }

    public function testContentTypeHeaderIsApplicationJson(): void
    {
        $response = new JsonResponse([]);

        $headers = $response->getHeaders();
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function testDefaultStatusCodeIs200(): void
    {
        $response = new JsonResponse([]);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testStatusCodeCanBeSet(): void
    {
        $response = new JsonResponse(['created' => true], statusCode: 201);

        $this->assertSame(201, $response->getStatusCode());
    }

    public function testHandlesAssociativeArray(): void
    {
        $data = ['name' => 'Alice', 'age' => 30];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getBody(), true);
        $this->assertSame($data, $decoded);
    }

    public function testHandlesIndexedArray(): void
    {
        $data = [1, 2, 3];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getBody(), true);
        $this->assertSame($data, $decoded);
    }

    public function testHandlesNestedData(): void
    {
        $data = [
            'user' => [
                'name' => 'Alice',
                'address' => [
                    'city' => 'Portland',
                    'state' => 'OR',
                ],
                'tags' => ['admin', 'user'],
            ],
        ];
        $response = new JsonResponse($data);

        $decoded = json_decode($response->getBody(), true);
        $this->assertSame($data, $decoded);
    }

    public function testHandlesStdClassObject(): void
    {
        $data = new \stdClass();
        $data->name = 'Alice';
        $data->active = true;

        $response = new JsonResponse($data);

        $decoded = json_decode($response->getBody(), true);
        $this->assertSame(['name' => 'Alice', 'active' => true], $decoded);
    }

    public function testHandlesNullData(): void
    {
        $response = new JsonResponse(null);

        $this->assertSame('null', $response->getBody());
    }

    public function testHandlesScalarString(): void
    {
        $response = new JsonResponse('hello');

        $this->assertSame('"hello"', $response->getBody());
    }

    public function testHandlesScalarInteger(): void
    {
        $response = new JsonResponse(42);

        $this->assertSame('42', $response->getBody());
    }

    public function testHandlesBooleanData(): void
    {
        $response = new JsonResponse(true);

        $this->assertSame('true', $response->getBody());
    }

    public function testHandlesEmptyArray(): void
    {
        $response = new JsonResponse([]);

        $this->assertSame('[]', $response->getBody());
    }

    public function testAdditionalHeadersArePreserved(): void
    {
        $response = new JsonResponse(
            ['data' => 'test'],
            headers: ['X-Request-Id' => 'abc-123'],
        );

        $headers = $response->getHeaders();
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame('abc-123', $headers['X-Request-Id']);
    }

    public function testContentTypeOverridesProvidedHeader(): void
    {
        $response = new JsonResponse(
            ['data' => 'test'],
            headers: ['Content-Type' => 'text/plain'],
        );

        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    public function testExtendsResponse(): void
    {
        $response = new JsonResponse([]);

        $this->assertInstanceOf(\Melodic\Http\Response::class, $response);
    }
}
