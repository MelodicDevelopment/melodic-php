<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\AuthorizationMiddleware;
use Melodic\Security\User;
use Melodic\Security\UserContext;
use PHPUnit\Framework\TestCase;

final class AuthorizationMiddlewareTest extends TestCase
{
    private function createRequest(?UserContext $userContext = null): Request
    {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/test'],
        );

        if ($userContext !== null) {
            $request = $request->withAttribute('userContext', $userContext);
        }

        return $request;
    }

    private function createHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            public bool $handled = false;

            public function handle(Request $request): Response
            {
                $this->handled = true;
                return new Response(200, body: 'OK');
            }
        };
    }

    public function testReturns401WhenNoUserContext(): void
    {
        $middleware = new AuthorizationMiddleware();
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertFalse($handler->handled);
    }

    public function testReturns401WhenUnauthenticatedUserContext(): void
    {
        $middleware = new AuthorizationMiddleware();
        $request = $this->createRequest(UserContext::anonymous());
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testPassesThroughWhenAuthenticatedAndNoRequiredEntitlements(): void
    {
        $middleware = new AuthorizationMiddleware();
        $user = new User('1', 'alice', 'alice@example.com');
        $context = new UserContext($user);
        $request = $this->createRequest($context);
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handler->handled);
    }

    public function testReturns403WhenMissingRequiredEntitlements(): void
    {
        $middleware = new AuthorizationMiddleware(['admin', 'superadmin']);
        $user = new User('1', 'alice', 'alice@example.com', ['viewer']);
        $context = new UserContext($user);
        $request = $this->createRequest($context);
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertFalse($handler->handled);
    }

    public function testPassesThroughWhenUserHasAnyRequiredEntitlement(): void
    {
        $middleware = new AuthorizationMiddleware(['admin', 'editor']);
        $user = new User('1', 'alice', 'alice@example.com', ['editor']);
        $context = new UserContext($user);
        $request = $this->createRequest($context);
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handler->handled);
    }

    public function testAllowsUnauthenticatedWhenRequireAuthenticationIsFalse(): void
    {
        $middleware = new AuthorizationMiddleware(requireAuthentication: false);
        $request = $this->createRequest();
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($handler->handled);
    }

    public function testRequireAuthenticationFalseStillChecksEntitlements(): void
    {
        $middleware = new AuthorizationMiddleware(['admin'], requireAuthentication: false);
        $user = new User('1', 'alice', 'alice@example.com', ['viewer']);
        $context = new UserContext($user);
        $request = $this->createRequest($context);
        $handler = $this->createHandler();

        $response = $middleware->process($request, $handler);

        $this->assertSame(403, $response->getStatusCode());
    }
}
