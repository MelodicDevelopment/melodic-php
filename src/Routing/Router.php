<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array{prefix: string, middleware: array}[] */
    private array $groupStack = [];

    public function get(string $path, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute(HttpMethod::GET, $path, $controller, $action, $middleware);
    }

    public function post(string $path, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute(HttpMethod::POST, $path, $controller, $action, $middleware);
    }

    public function put(string $path, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute(HttpMethod::PUT, $path, $controller, $action, $middleware);
    }

    public function delete(string $path, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute(HttpMethod::DELETE, $path, $controller, $action, $middleware);
    }

    public function patch(string $path, string $controller, string $action, array $middleware = []): self
    {
        return $this->addRoute(HttpMethod::PATCH, $path, $controller, $action, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): self
    {
        $this->groupStack[] = [
            'prefix' => $prefix,
            'middleware' => $middleware,
        ];

        $callback($this);

        array_pop($this->groupStack);

        return $this;
    }

    public function apiResource(string $path, string $controller, array $middleware = []): self
    {
        $this->get($path, $controller, 'index', $middleware);
        $this->get($path . '/{id}', $controller, 'show', $middleware);
        $this->post($path, $controller, 'store', $middleware);
        $this->put($path . '/{id}', $controller, 'update', $middleware);
        $this->delete($path . '/{id}', $controller, 'destroy', $middleware);

        return $this;
    }

    /**
     * @return array{route: Route, params: array}|null
     */
    public function match(HttpMethod $method, string $path): ?array
    {
        foreach ($this->routes as $route) {
            $params = $route->matches($method, $path);

            if ($params !== null) {
                return ['route' => $route, 'params' => $params];
            }
        }

        return null;
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    private function addRoute(
        HttpMethod $method,
        string $path,
        string $controller,
        string $action,
        array $middleware,
    ): self {
        $prefix = $this->getCurrentPrefix();
        $groupMiddleware = $this->getCurrentMiddleware();

        $this->routes[] = new Route(
            method: $method,
            pattern: $prefix . $path,
            controller: $controller,
            action: $action,
            middleware: array_merge($groupMiddleware, $middleware),
        );

        return $this;
    }

    private function getCurrentPrefix(): string
    {
        $prefix = '';

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
        }

        return $prefix;
    }

    private function getCurrentMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }

        return $middleware;
    }
}
