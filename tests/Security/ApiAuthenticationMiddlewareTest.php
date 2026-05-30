<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\AuthConfig;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\JwtValidator;
use Melodic\Security\LocalAuthConfig;
use PHPUnit\Framework\TestCase;

class ApiAuthPassThroughHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new Response(200, 'ok');
    }
}

class ApiAuthenticationMiddlewareTest extends TestCase
{
    private function middleware(): ApiAuthenticationMiddleware
    {
        $validator = new JwtValidator(
            new AuthProviderRegistry(),
            new LocalAuthConfig(signingKey: 'a-sufficiently-long-signing-secret-32+'),
        );

        return new ApiAuthenticationMiddleware(new AuthConfig(), $validator);
    }

    private function request(array $headers = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/api/things'],
            headers: $headers,
        );
    }

    public function testMissingTokenReturns401(): void
    {
        $response = $this->middleware()->process($this->request(), new ApiAuthPassThroughHandler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testInvalidTokenReturnsGenericMessageWithoutLeakingDetail(): void
    {
        $response = $this->middleware()->process(
            $this->request(['Authorization' => 'Bearer not-a-real-jwt']),
            new ApiAuthPassThroughHandler(),
        );

        $this->assertSame(401, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        // Exactly the generic message — no internal validator detail.
        $this->assertSame('Authentication failed.', $data['error']);
    }
}
