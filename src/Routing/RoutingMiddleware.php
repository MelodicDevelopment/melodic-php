<?php

declare(strict_types=1);

namespace Melodic\Routing;

use Melodic\Data\Model;
use Melodic\Data\ModelBindingException;
use Melodic\DI\Container;
use Melodic\Http\Exception\MethodNotAllowedException;
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
            // Path exists under other methods → 405 (with Allow header); otherwise
            // fall through to the next handler, which 404s.
            $allowed = $this->router->allowedMethodsForPath($request->path());

            if ($allowed !== []) {
                throw MethodNotAllowedException::forMethods($allowed);
            }

            return $handler->handle($request);
        }

        /** @var Route $route */
        $route = $result['route'];
        $params = $result['params'];

        foreach ($params as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        $request = $request->withAttribute('route', $route);
        $request = $request->withAttribute('route.attributes', $route->attributes);

        // Build a handler that invokes the controller action
        $controllerHandler = new class($this->container, $route, $params) implements RequestHandlerInterface {
            /** @param array<string, string> $params */
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
                        try
                        {
                            $args[] = $this->coerceRouteParam($param, $this->params[$name]);
                        }
                        catch (ModelBindingException $e)
                        {
                            // A non-numeric id for `show(int $id)` is a client
                            // error (400), not an uncaught TypeError (500).
                            return new JsonResponse([$e->field => [$e->getMessage()]], 400);
                        }

                        continue;
                    }

                    $type = $param->getType();

                    // No type hint or a builtin scalar with no matching route param.
                    if (!$type instanceof ReflectionNamedType || $type->isBuiltin())
                    {
                        if ($param->isDefaultValueAvailable())
                        {
                            $args[] = $param->getDefaultValue();
                            continue;
                        }

                        if ($type instanceof ReflectionNamedType && $type->allowsNull())
                        {
                            $args[] = null;
                            continue;
                        }

                        // Nothing can satisfy this parameter. Fail loudly with a
                        // clear message instead of an opaque ArgumentCountError.
                        throw new \RuntimeException(sprintf(
                            'Cannot resolve argument $%s for %s::%s(). Expected a matching route '
                            . 'parameter, a Model to bind from the request body, or a default value.',
                            $name,
                            $this->route->controller,
                            $this->route->action,
                        ));
                    }

                    $className = $type->getName();

                    // Model subclass → hydrate from the request body and validate.
                    if (is_subclass_of($className, Model::class))
                    {
                        try
                        {
                            /** @var Model $model */
                            $model = $className::fromArray($request->body());
                        }
                        catch (ModelBindingException $e)
                        {
                            // Malformed/uncoercible input is a client error, not a 500.
                            return new JsonResponse([$e->field => [$e->getMessage()]], 400);
                        }

                        /** @var Validator $validator */
                        $validator = $this->container->get(Validator::class);
                        $result = $validator->validate($model);

                        if (!$result->isValid)
                        {
                            return new JsonResponse($result->errors, 400);
                        }

                        $args[] = $model;
                        continue;
                    }

                    // Any other class → resolve it from the container so services
                    // can be injected straight into controller actions.
                    $args[] = $this->container->get($className);
                }

                return $args;
            }

            /**
             * Coerce a captured route param (always a string) to the action
             * parameter's declared type so `show(int $id)` works under
             * strict_types. Uncoercible input throws ModelBindingException,
             * which the caller turns into a 400.
             */
            private function coerceRouteParam(\ReflectionParameter $param, string $value): mixed
            {
                $type = $param->getType();

                if (!$type instanceof ReflectionNamedType)
                {
                    return $value;
                }

                if (!$type->isBuiltin())
                {
                    return $this->coerceEnumParam($type->getName(), $param->getName(), $value);
                }

                $name = $param->getName();

                return match ($type->getName()) {
                    'int' => preg_match('/^-?\d+$/', $value) === 1
                        ? (int) $value
                        : throw new ModelBindingException($name, "Route parameter '{$name}' must be an integer."),
                    'float' => is_numeric($value)
                        ? (float) $value
                        : throw new ModelBindingException($name, "Route parameter '{$name}' must be a number."),
                    'bool' => match (strtolower($value)) {
                        '1', 'true' => true,
                        '0', 'false' => false,
                        default => throw new ModelBindingException($name, "Route parameter '{$name}' must be a boolean."),
                    },
                    default => $value,
                };
            }

            private function coerceEnumParam(string $class, string $name, string $value): mixed
            {
                if (!is_subclass_of($class, \BackedEnum::class))
                {
                    return $value;
                }

                $backingType = (string) (new \ReflectionEnum($class))->getBackingType();
                $case = $backingType === 'int'
                    ? (preg_match('/^-?\d+$/', $value) === 1 ? $class::tryFrom((int) $value) : null)
                    : $class::tryFrom($value);

                return $case
                    ?? throw new ModelBindingException($name, "Route parameter '{$name}' is not one of the allowed values.");
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
