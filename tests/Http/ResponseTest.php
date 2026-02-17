<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testDefaultStatusCodeIs200(): void
    {
        $response = new Response();

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testDefaultBodyIsEmpty(): void
    {
        $response = new Response();

        $this->assertSame('', $response->getBody());
    }

    public function testDefaultHeadersAreEmpty(): void
    {
        $response = new Response();

        $this->assertSame([], $response->getHeaders());
    }

    public function testConstructorSetsStatusCode(): void
    {
        $response = new Response(statusCode: 404);

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testConstructorSetsBody(): void
    {
        $response = new Response(body: 'Hello, World!');

        $this->assertSame('Hello, World!', $response->getBody());
    }

    public function testConstructorSetsHeaders(): void
    {
        $response = new Response(headers: ['X-Custom' => 'value']);

        $this->assertSame(['X-Custom' => 'value'], $response->getHeaders());
    }

    public function testWithStatusReturnsNewInstance(): void
    {
        $original = new Response();
        $modified = $original->withStatus(404);

        $this->assertNotSame($original, $modified);
        $this->assertSame(200, $original->getStatusCode());
        $this->assertSame(404, $modified->getStatusCode());
    }

    public function testWithStatusPreservesBodyAndHeaders(): void
    {
        $original = new Response(body: 'content', headers: ['X-Test' => 'value']);
        $modified = $original->withStatus(201);

        $this->assertSame('content', $modified->getBody());
        $this->assertSame(['X-Test' => 'value'], $modified->getHeaders());
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        $original = new Response();
        $modified = $original->withHeader('X-Custom', 'value');

        $this->assertNotSame($original, $modified);
        $this->assertSame([], $original->getHeaders());
        $this->assertSame(['X-Custom' => 'value'], $modified->getHeaders());
    }

    public function testWithHeaderPreservesStatusAndBody(): void
    {
        $original = new Response(statusCode: 201, body: 'created');
        $modified = $original->withHeader('Location', '/items/1');

        $this->assertSame(201, $modified->getStatusCode());
        $this->assertSame('created', $modified->getBody());
    }

    public function testMultipleHeadersAccumulateCorrectly(): void
    {
        $response = new Response();
        $response = $response->withHeader('X-First', 'one');
        $response = $response->withHeader('X-Second', 'two');
        $response = $response->withHeader('X-Third', 'three');

        $this->assertSame([
            'X-First' => 'one',
            'X-Second' => 'two',
            'X-Third' => 'three',
        ], $response->getHeaders());
    }

    public function testWithHeaderOverwritesExistingHeader(): void
    {
        $response = new Response(headers: ['Content-Type' => 'text/plain']);
        $modified = $response->withHeader('Content-Type', 'application/json');

        $this->assertSame(['Content-Type' => 'application/json'], $modified->getHeaders());
    }

    public function testWithBodyReturnsNewInstance(): void
    {
        $original = new Response(body: 'original');
        $modified = $original->withBody('modified');

        $this->assertNotSame($original, $modified);
        $this->assertSame('original', $original->getBody());
        $this->assertSame('modified', $modified->getBody());
    }

    public function testWithBodyPreservesStatusAndHeaders(): void
    {
        $original = new Response(statusCode: 201, headers: ['X-Test' => 'value']);
        $modified = $original->withBody('new body');

        $this->assertSame(201, $modified->getStatusCode());
        $this->assertSame(['X-Test' => 'value'], $modified->getHeaders());
    }

    public function testWithCookieReturnsNewInstance(): void
    {
        $original = new Response();
        $modified = $original->withCookie('session', 'abc123');

        $this->assertNotSame($original, $modified);
    }

    public function testWithCookiePreservesStatusBodyAndHeaders(): void
    {
        $original = new Response(statusCode: 200, body: 'content', headers: ['X-Test' => 'yes']);
        $modified = $original->withCookie('token', 'xyz');

        $this->assertSame(200, $modified->getStatusCode());
        $this->assertSame('content', $modified->getBody());
        $this->assertSame(['X-Test' => 'yes'], $modified->getHeaders());
    }

    public function testImmutabilityChainPreservesAllState(): void
    {
        $response = new Response();
        $response = $response->withStatus(201);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response = $response->withBody('{"id":1}');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['Content-Type' => 'application/json'], $response->getHeaders());
        $this->assertSame('{"id":1}', $response->getBody());
    }
}
