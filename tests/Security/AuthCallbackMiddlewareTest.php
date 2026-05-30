<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Exception\HttpException;
use Melodic\Http\Exception\MethodNotAllowedException;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\AuthConfig;
use Melodic\Security\AuthLoginRendererInterface;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\SessionManager;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class StubLoginRenderer implements AuthLoginRendererInterface
{
    public function render(?string $error = null, ?string $csrfToken = null): string
    {
        return '<form></form>';
    }
}

class StubPassThroughHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new Response(200, 'passthrough');
    }
}

class AuthCallbackMiddlewareTest extends TestCase
{
    private function middleware(): AuthCallbackMiddleware
    {
        return new AuthCallbackMiddleware(
            new AuthConfig(),
            new AuthProviderRegistry(),
            new SessionManager(),
            new StubLoginRenderer(),
        );
    }

    private function request(string $method, string $uri, array $body = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            body: $body,
        );
    }

    public function testLogoutRejectsNonPost(): void
    {
        // GET logout must not be honored — the method check happens before any
        // session interaction, so no isolation is needed here.
        $this->expectException(MethodNotAllowedException::class);

        $this->middleware()->process(
            $this->request('GET', '/auth/logout'),
            new StubPassThroughHandler(),
        );
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testLogoutPostWithoutValidCsrfIsForbidden(): void
    {
        try {
            $this->middleware()->process(
                $this->request('POST', '/auth/logout', ['csrf_token' => 'bogus']),
                new StubPassThroughHandler(),
            );
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }
}
