<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\DI\Container;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;

class RoutingMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $result = $this->router->match($request->method(), $request->path());

        if ($result === null) {
            return new JsonResponse(
                data: ['error' => 'Not Found'],
                statusCode: 404,
            );
        }

        /** @var Route $route */
        $route = $result['route'];
        $params = $result['params'];

        foreach ($params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        // Build a handler that invokes the controller action
        $controllerHandler = new class($this->container, $route, $params) implements RequestHandlerInterface {
            public function __construct(
                private readonly Container $container,
                private readonly Route $route,
                private readonly array $params,
            ) {}

            public function handle(Request $request): Response
            {
                $controller = $this->container->get($this->route->controller);
                $controller->setRequest($request);

                return $controller->{$this->route->action}(...$this->params);
            }
        };

        // If the route has middleware, build a mini-pipeline
        if (!empty($route->middleware)) {
            $pipeline = new Pipeline($controllerHandler);

            foreach ($route->middleware as $middlewareClass) {
                /** @var MiddlewareInterface $middleware */
                $middleware = $this->container->get($middlewareClass);
                $pipeline->pipe($middleware);
            }

            return $pipeline->handle($request);
        }

        return $controllerHandler->handle($request);
    }
}
