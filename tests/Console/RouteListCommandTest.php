<?php

declare(strict_types=1);

namespace Tests\Console;

use Melodic\Console\RouteListCommand;
use Melodic\Routing\Router;
use PHPUnit\Framework\TestCase;

final class RouteListCommandTest extends TestCase
{
    public function testGetNameReturnsRouteList(): void
    {
        $command = new RouteListCommand(new Router());

        $this->assertSame('route:list', $command->getName());
    }

    public function testGetDescriptionReturnsListAllRegisteredRoutes(): void
    {
        $command = new RouteListCommand(new Router());

        $this->assertSame('List all registered routes', $command->getDescription());
    }

    public function testExecuteWithNoRoutesPrintsNoRoutesMessage(): void
    {
        $command = new RouteListCommand(new Router());

        ob_start();
        $exitCode = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertSame("No routes registered.\n", $output);
    }

    public function testExecuteWithRoutesPrintsTable(): void
    {
        $router = new Router();
        $router->get('/users', 'UserController', 'index');
        $router->post('/users', 'UserController', 'store');

        $command = new RouteListCommand($router);

        ob_start();
        $exitCode = $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Method', $output);
        $this->assertStringContainsString('Path', $output);
        $this->assertStringContainsString('Controller', $output);
        $this->assertStringContainsString('Action', $output);
        $this->assertStringContainsString('GET', $output);
        $this->assertStringContainsString('/users', $output);
        $this->assertStringContainsString('UserController', $output);
        $this->assertStringContainsString('index', $output);
        $this->assertStringContainsString('POST', $output);
        $this->assertStringContainsString('store', $output);
    }
}
