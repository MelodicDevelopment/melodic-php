<?php

declare(strict_types=1);

namespace Tests\Controller;

use Melodic\Controller\MvcController;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Security\User;
use Melodic\Security\UserContext;
use Melodic\Security\UserContextInterface;
use Melodic\View\ViewBag;
use Melodic\View\ViewEngine;
use PHPUnit\Framework\TestCase;

final class ConcreteMvcController extends MvcController
{
    public function callView(string $template, array $data = []): Response
    {
        return $this->view($template, $data);
    }

    public function callGetUserContext(): ?UserContextInterface
    {
        return $this->getUserContext();
    }
}

final class MvcControllerTest extends TestCase
{
    private string $viewsPath;

    protected function setUp(): void
    {
        $this->viewsPath = sys_get_temp_dir() . '/melodic_mvc_test_' . uniqid();
        mkdir($this->viewsPath, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->viewsPath);
    }

    public function testViewReturnsHtmlResponse(): void
    {
        file_put_contents($this->viewsPath . '/hello.phtml', '<h1>Hello World</h1>');

        $controller = $this->createController();

        $response = $controller->callView('hello');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('<h1>Hello World</h1>', $response->getBody());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
    }

    public function testViewPassesDataToTemplate(): void
    {
        file_put_contents($this->viewsPath . '/greeting.phtml', '<p><?= $name ?></p>');

        $controller = $this->createController();

        $response = $controller->callView('greeting', ['name' => 'Alice']);

        $this->assertSame('<p>Alice</p>', $response->getBody());
    }

    public function testViewWithLayout(): void
    {
        mkdir($this->viewsPath . '/layouts', 0777, true);
        file_put_contents(
            $this->viewsPath . '/layouts/main.phtml',
            '<html><?= $this->renderBody() ?></html>',
        );
        file_put_contents($this->viewsPath . '/page.phtml', '<p>Content</p>');

        $controller = $this->createController();
        $controller->setLayout('layouts/main');

        $response = $controller->callView('page');

        $this->assertSame('<html><p>Content</p></html>', $response->getBody());
    }

    public function testViewBagReturnsViewBagInstance(): void
    {
        $controller = $this->createController();

        $viewBag = $controller->viewBag();

        $this->assertInstanceOf(ViewBag::class, $viewBag);
    }

    public function testViewBagIsAccessibleInTemplate(): void
    {
        file_put_contents(
            $this->viewsPath . '/with_bag.phtml',
            '<p><?= $viewBag->title ?></p>',
        );

        $controller = $this->createController();
        $controller->viewBag()->title = 'My Title';

        $response = $controller->callView('with_bag');

        $this->assertSame('<p>My Title</p>', $response->getBody());
    }

    public function testSetLayoutSetsLayoutForRendering(): void
    {
        mkdir($this->viewsPath . '/layouts', 0777, true);
        file_put_contents(
            $this->viewsPath . '/layouts/custom.phtml',
            '<div><?= $this->renderBody() ?></div>',
        );
        file_put_contents($this->viewsPath . '/inner.phtml', 'Inner');

        $controller = $this->createController();
        $controller->setLayout('layouts/custom');

        $response = $controller->callView('inner');

        $this->assertSame('<div>Inner</div>', $response->getBody());
    }

    public function testGetUserContextReturnsNullWhenNotSet(): void
    {
        $controller = $this->createController();

        $this->assertNull($controller->callGetUserContext());
    }

    public function testGetUserContextReturnsUserContextWhenSet(): void
    {
        $userContext = new UserContext(
            new User('1', 'test', 'test@test.com', ['admin']),
        );

        $request = new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
            attributes: ['userContext' => $userContext],
        );

        $viewEngine = new ViewEngine($this->viewsPath);
        $controller = new ConcreteMvcController($viewEngine);
        $controller->setRequest($request);

        $result = $controller->callGetUserContext();

        $this->assertInstanceOf(UserContextInterface::class, $result);
        $this->assertTrue($result->isAuthenticated());
        $this->assertSame('test', $result->getUsername());
    }

    public function testViewBagReturnsSameInstance(): void
    {
        $controller = $this->createController();

        $bag1 = $controller->viewBag();
        $bag2 = $controller->viewBag();

        $this->assertSame($bag1, $bag2);
    }

    private function createController(): ConcreteMvcController
    {
        $viewEngine = new ViewEngine($this->viewsPath);
        $controller = new ConcreteMvcController($viewEngine);
        $controller->setRequest(new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
        ));

        return $controller;
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath)) {
                $this->removeDirectory($fullPath);
            } else {
                unlink($fullPath);
            }
        }

        rmdir($path);
    }
}
