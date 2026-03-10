<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Data\Model;
use Melodic\DI\Container;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Validation\Validator;
use ReflectionMethod;
use ReflectionNamedType;

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
            return $handler->handle($request);
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

                $args = $this->resolveActionArguments($request);

                if ($args instanceof Response)
                {
                    return $args;
                }

                return $controller->{$this->route->action}(...$args);
            }

            /**
             * @return array<int, mixed>|Response
             */
            private function resolveActionArguments(Request $request): array|Response
            {
                $method = new ReflectionMethod($this->route->controller, $this->route->action);
                $args = [];

                foreach ($method->getParameters() as $param)
                {
                    $name = $param->getName();

                    // Route params matched by name take priority
                    if (array_key_exists($name, $this->params))
                    {
                        $args[] = $this->params[$name];
                        continue;
                    }

                    // Check if the parameter type is a concrete Model subclass
                    $type = $param->getType();

                    if (!$type instanceof ReflectionNamedType || $type->isBuiltin())
                    {
                        if ($param->isDefaultValueAvailable())
                        {
                            $args[] = $param->getDefaultValue();
                        }
                        continue;
                    }

                    $className = $type->getName();

                    if (!is_subclass_of($className, Model::class))
                    {
                        continue;
                    }

                    // Hydrate the model from the request body
                    /** @var Model $model */
                    $model = $className::fromArray($request->body());

                    // Validate the model
                    /** @var Validator $validator */
                    $validator = $this->container->get(Validator::class);
                    $result = $validator->validate($model);

                    if (!$result->isValid)
                    {
                        return new JsonResponse($result->errors, 400);
                    }

                    $args[] = $model;
                }

                return $args;
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
