<?php

declare(strict_types=1);

namespace Tests\Controller;

use Melodic\Controller\ApiController;
use Melodic\Http\Request;
use Melodic\Security\User;
use Melodic\Security\UserContext;
use Melodic\Security\UserContextInterface;
use PHPUnit\Framework\TestCase;

final class ConcreteApiController extends ApiController
{
    public function callGetUserContext(): ?UserContextInterface
    {
        return $this->getUserContext();
    }
}

final class ApiControllerTest extends TestCase
{
    public function testGetUserContextReturnsNullWhenNotSet(): void
    {
        $controller = new ConcreteApiController();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );
        $controller->setRequest($request);

        $this->assertNull($controller->callGetUserContext());
    }

    public function testGetUserContextReturnsUserContextWhenSet(): void
    {
        $userContext = new UserContext(
            new User('1', 'test', 'test@test.com', ['admin']),
        );

        $controller = new ConcreteApiController();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            attributes: ['userContext' => $userContext],
        );
        $controller->setRequest($request);

        $result = $controller->callGetUserContext();

        $this->assertInstanceOf(UserContextInterface::class, $result);
        $this->assertTrue($result->isAuthenticated());
        $this->assertSame('test', $result->getUsername());
        $this->assertTrue($result->hasEntitlement('admin'));
    }

    public function testGetUserContextReturnsContextSetViaWithAttribute(): void
    {
        $userContext = new UserContext(
            new User('42', 'jane', 'jane@test.com', ['editor']),
        );

        $controller = new ConcreteApiController();
        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        );
        $request = $request->withAttribute('userContext', $userContext);
        $controller->setRequest($request);

        $result = $controller->callGetUserContext();

        $this->assertInstanceOf(UserContextInterface::class, $result);
        $this->assertSame('42', $result->getUser()->id);
        $this->assertSame('jane', $result->getUsername());
        $this->assertTrue($result->hasEntitlement('editor'));
        $this->assertFalse($result->hasEntitlement('admin'));
    }
}
