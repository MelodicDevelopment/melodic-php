<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Routing\Router;

class RouteListCommand extends Command
{
    public function __construct(
        private readonly Router $router,
    ) {
        parent::__construct('route:list', 'List all registered routes');
    }

    public function execute(array $args): int
    {
        $routes = $this->router->getRoutes();

        if (count($routes) === 0) {
            $this->writeln('No routes registered.');
            return 0;
        }

        $headers = ['Method', 'Path', 'Controller', 'Action'];
        $rows = [];

        foreach ($routes as $route) {
            $rows[] = [
                $route->method->value,
                $route->pattern,
                $route->controller,
                $route->action,
            ];
        }

        $this->table($headers, $rows);

        return 0;
    }
}
