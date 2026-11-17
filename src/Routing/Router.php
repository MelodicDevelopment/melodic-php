<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Http\HttpMethod;

class Router
{
    /** @var Route[] */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $namedRoutes = [];

    /** @var array{prefix: string, middleware: string[], attributes: array<string, mixed>}[] */
    private array $groupStack = [];

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function get(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null): self
    {
        return $this->addRoute(HttpMethod::GET, $path, $controller, $action, $middleware, $attributes, $name);
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function post(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null): self
    {
        return $this->addRoute(HttpMethod::POST, $path, $controller, $action, $middleware, $attributes, $name);
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function put(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null): self
    {
        return $this->addRoute(HttpMethod::PUT, $path, $controller, $action, $middleware, $attributes, $name);
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function delete(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null): self
    {
        return $this->addRoute(HttpMethod::DELETE, $path, $controller, $action, $middleware, $attributes, $name);
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function patch(string $path, string $controller, string $action, array $middleware = [], array $attributes = [], ?string $name = null): self
    {
        return $this->addRoute(HttpMethod::PATCH, $path, $controller, $action, $middleware, $attributes, $name);
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function group(string $prefix, callable $callback, array $middleware = [], array $attributes = []): self
    {
        $this->groupStack[] = [
            'prefix' => $prefix,
            'middleware' => $middleware,
            'attributes' => $attributes,
        ];

        $callback($this);

        array_pop($this->groupStack);

        return $this;
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    public function apiResource(string $path, string $controller, array $middleware = [], array $attributes = []): self
    {
        $this->get($path, $controller, 'index', $middleware, $attributes);
        $this->get($path . '/{id}', $controller, 'show', $middleware, $attributes);
        $this->post($path, $controller, 'store', $middleware, $attributes);
        $this->put($path . '/{id}', $controller, 'update', $middleware, $attributes);
        $this->delete($path . '/{id}', $controller, 'destroy', $middleware, $attributes);
        // PATCH shares the update action (registered last so existing route
        // order is unchanged); use Model::toUpdateArray() for partial updates.
        $this->patch($path . '/{id}', $controller, 'update', $middleware, $attributes);

        return $this;
    }

    /**
     * @return array{route: Route, params: array<string, string>}|null
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
     * Methods registered for a path, regardless of HTTP method. An empty result
     * means the path is unknown (→ 404); a non-empty result with no match for the
     * requested method means the method is not allowed (→ 405).
     *
     * @return string[]
     */
    public function allowedMethodsForPath(string $path): array
    {
        $methods = [];

        foreach ($this->routes as $route) {
            if ($route->matchesPath($path) !== null) {
                $methods[$route->method->value] = true;
            }
        }

        $allowed = array_keys($methods);

        // Any GET resource also answers HEAD (see Route::matches).
        if (in_array(HttpMethod::GET->value, $allowed, true) && !in_array(HttpMethod::HEAD->value, $allowed, true)) {
            $allowed[] = HttpMethod::HEAD->value;
        }

        return $allowed;
    }

    /**
     * Build the path for a named route, e.g. url('users.show', ['id' => 5])
     * → "/users/5". Parameters not present in the pattern are appended as a
     * query string.
     *
     * @param array<string, scalar|null> $params
     */
    public function url(string $name, array $params = []): string
    {
        $route = $this->namedRoutes[$name]
            ?? throw new \InvalidArgumentException("No route is named '{$name}'.");

        $path = preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::[^{}]+)?\}/',
            function (array $m) use (&$params, $name): string {
                if (!array_key_exists($m[1], $params)) {
                    throw new \InvalidArgumentException("Route '{$name}' requires parameter '{$m[1]}'.");
                }

                $value = rawurlencode((string) $params[$m[1]]);
                unset($params[$m[1]]);

                return $value;
            },
            $route->pattern,
        );

        return $params === [] ? (string) $path : $path . '?' . http_build_query($params);
    }

    /**
     * @return Route[]
     */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    /**
     * @param string[] $middleware
     * @param array<string, mixed> $attributes
     */
    private function addRoute(
        HttpMethod $method,
        string $path,
        string $controller,
        string $action,
        array $middleware,
        array $attributes,
        ?string $name = null,
    ): self {
        $prefix = $this->getCurrentPrefix();
        $groupMiddleware = $this->getCurrentMiddleware();
        $groupAttributes = $this->getCurrentAttributes();

        $this->routes[] = $route = new Route(
            method: $method,
            pattern: $prefix . $path,
            controller: $controller,
            action: $action,
            middleware: array_merge($groupMiddleware, $middleware),
            attributes: array_replace_recursive($groupAttributes, $attributes),
            name: $name,
        );

        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
        }

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

    /** @return string[] */
    private function getCurrentMiddleware(): array
    {
        $middleware = [];

        foreach ($this->groupStack as $group) {
            $middleware = array_merge($middleware, $group['middleware']);
        }

        return $middleware;
    }

    /** @return array<string, mixed> */
    private function getCurrentAttributes(): array
    {
        $attributes = [];

        foreach ($this->groupStack as $group) {
            $attributes = array_replace_recursive($attributes, $group['attributes']);
        }

        return $attributes;
    }
}
