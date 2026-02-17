<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Http\HttpMethod;
use Melodic\Http\Request;
use PHPUnit\Framework\TestCase;

final class RequestTest extends TestCase
{
    public function testMethodParsedFromServerArray(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/']);

        $this->assertSame(HttpMethod::POST, $request->method());
    }

    public function testMethodDefaultsToGetWhenNotSet(): void
    {
        $request = new Request(server: ['REQUEST_URI' => '/']);

        $this->assertSame(HttpMethod::GET, $request->method());
    }

    public function testPathParsedFromServerArray(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/users/42']);

        $this->assertSame('/users/42', $request->path());
    }

    public function testPathDefaultsToSlashWhenNotSet(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET']);

        $this->assertSame('/', $request->path());
    }

    public function testPathStripsQueryString(): void
    {
        $request = new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/search?q=hello']);

        $this->assertSame('/search', $request->path());
    }

    public function testQueryReturnsAllParamsWhenNoKeyGiven(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            query: ['page' => '2', 'limit' => '10'],
        );

        $this->assertSame(['page' => '2', 'limit' => '10'], $request->query());
    }

    public function testQueryReturnsSpecificParam(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            query: ['page' => '2'],
        );

        $this->assertSame('2', $request->query('page'));
    }

    public function testQueryReturnsDefaultWhenKeyNotFound(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            query: [],
        );

        $this->assertSame('1', $request->query('page', '1'));
    }

    public function testQueryReturnsNullForMissingKeyWithNoDefault(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            query: [],
        );

        $this->assertNull($request->query('missing'));
    }

    public function testBodyReturnsAllParamsWhenNoKeyGiven(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            body: ['name' => 'Alice', 'email' => 'alice@example.com'],
        );

        $this->assertSame(['name' => 'Alice', 'email' => 'alice@example.com'], $request->body());
    }

    public function testBodyReturnsSpecificParam(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            body: ['name' => 'Alice'],
        );

        $this->assertSame('Alice', $request->body('name'));
    }

    public function testBodyReturnsDefaultWhenKeyNotFound(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            body: [],
        );

        $this->assertSame('unknown', $request->body('name', 'unknown'));
    }

    public function testBodyFallsBackToParsedBodyAttribute(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            body: [],
            attributes: ['parsedBody' => ['name' => 'Bob']],
        );

        $this->assertSame('Bob', $request->body('name'));
    }

    public function testBodyDoesNotFallBackWhenBodyParamsExist(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            body: ['name' => 'Alice'],
            attributes: ['parsedBody' => ['name' => 'Bob']],
        );

        $this->assertSame('Alice', $request->body('name'));
    }

    public function testHeaderReturnsCaseInsensitive(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['Content-Type' => 'application/json'],
        );

        $this->assertSame('application/json', $request->header('content-type'));
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('application/json', $request->header('CONTENT-TYPE'));
    }

    public function testHeaderReturnsNullForMissingHeader(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
        );

        $this->assertNull($request->header('X-Custom'));
    }

    public function testHeadersExtractedFromServerArray(): void
    {
        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/',
                'HTTP_ACCEPT' => 'text/html',
                'HTTP_X_CUSTOM_HEADER' => 'custom-value',
                'CONTENT_TYPE' => 'application/json',
                'CONTENT_LENGTH' => '42',
            ],
        );

        $this->assertSame('text/html', $request->header('Accept'));
        $this->assertSame('custom-value', $request->header('X-Custom-Header'));
        $this->assertSame('application/json', $request->header('Content-Type'));
        $this->assertSame('42', $request->header('Content-Length'));
    }

    public function testBearerTokenExtractedFromAuthorizationHeader(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['Authorization' => 'Bearer my-jwt-token'],
        );

        $this->assertSame('my-jwt-token', $request->bearerToken());
    }

    public function testBearerTokenReturnsNullWhenNoAuthorizationHeader(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: [],
        );

        $this->assertNull($request->bearerToken());
    }

    public function testBearerTokenReturnsNullForNonBearerAuth(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            headers: ['Authorization' => 'Basic dXNlcjpwYXNz'],
        );

        $this->assertNull($request->bearerToken());
    }

    public function testWithAttributeReturnsNewInstance(): void
    {
        $original = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );

        $modified = $original->withAttribute('key', 'value');

        $this->assertNotSame($original, $modified);
        $this->assertNull($original->getAttribute('key'));
        $this->assertSame('value', $modified->getAttribute('key'));
    }

    public function testWithAttributePreservesExistingAttributes(): void
    {
        $original = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            attributes: ['existing' => 'data'],
        );

        $modified = $original->withAttribute('new', 'value');

        $this->assertSame('data', $modified->getAttribute('existing'));
        $this->assertSame('value', $modified->getAttribute('new'));
    }

    public function testWithAttributePreservesMethodAndPath(): void
    {
        $original = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/api/users'],
            query: ['page' => '1'],
            body: ['name' => 'Alice'],
        );

        $modified = $original->withAttribute('key', 'value');

        $this->assertSame(HttpMethod::POST, $modified->method());
        $this->assertSame('/api/users', $modified->path());
        $this->assertSame('1', $modified->query('page'));
        $this->assertSame('Alice', $modified->body('name'));
    }

    public function testGetAttributeReturnsDefaultWhenNotSet(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );

        $this->assertSame('default', $request->getAttribute('missing', 'default'));
    }

    public function testGetAttributeReturnsNullByDefault(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );

        $this->assertNull($request->getAttribute('missing'));
    }

    public function testRawBodyReturnsProvidedRawBody(): void
    {
        $json = '{"name":"Alice"}';
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/'],
            rawBody: $json,
        );

        $this->assertSame($json, $request->rawBody());
    }

    public function testRawBodyDefaultsToEmptyString(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );

        $this->assertSame('', $request->rawBody());
    }

    public function testCookieReturnsValue(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            cookies: ['session_id' => 'abc123'],
        );

        $this->assertSame('abc123', $request->cookie('session_id'));
    }

    public function testCookieReturnsDefaultWhenNotSet(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            cookies: [],
        );

        $this->assertSame('none', $request->cookie('session_id', 'none'));
    }

    public function testAllHttpMethodsCanBeParsed(): void
    {
        $methods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'];

        foreach ($methods as $method) {
            $request = new Request(
                server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => '/'],
            );

            $this->assertSame(HttpMethod::from($method), $request->method());
        }
    }

    public function testInvalidHttpMethodThrowsValueError(): void
    {
        $this->expectException(\ValueError::class);

        new Request(server: ['REQUEST_METHOD' => 'INVALID', 'REQUEST_URI' => '/']);
    }

    public function testMethodParsingIsCaseInsensitive(): void
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'post', 'REQUEST_URI' => '/'],
        );

        $this->assertSame(HttpMethod::POST, $request->method());
    }
}
