<?php

declare(strict_types=1);

namespace Melodic\DI;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: string|callable, singleton: bool}> */
    private array $bindings = [];
    /** @var array<string, mixed> */
    private array $instances = [];
    /** @var array<string, bool> */
    private array $resolving = [];

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->bindings[$id])) {
            $binding = $this->bindings[$id];
            $instance = $this->build($binding['concrete']);

            if ($binding['singleton']) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        }

        return $this->resolve($id);
    }

    /**
     * Whether the container can attempt to resolve the id. This answers
     * "resolvable", not "registered": it returns true for any existing class
     * (auto-wiring may still fail later if that class has unresolvable
     * constructor parameters), in addition to explicit bindings/instances.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]) || isset($this->instances[$id]) || class_exists($id);
    }

    public function bind(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => false,
        ];

        unset($this->instances[$abstract]);
    }

    public function singleton(string $abstract, string|callable $concrete): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete,
            'singleton' => true,
        ];

        // Drop any previously cached instance so re-registering a singleton
        // actually takes effect (matches bind()'s behavior).
        unset($this->instances[$abstract]);

        // singleton(Interface, Concrete) also aliases the concrete class to the
        // shared binding — otherwise a direct Concrete type-hint would quietly
        // auto-wire a *second* instance behind the singleton's back. An explicit
        // pre-existing binding for the concrete class is never overridden.
        if (is_string($concrete) && $concrete !== $abstract && !isset($this->bindings[$concrete])) {
            $this->bindings[$concrete] = [
                'concrete' => fn(ContainerInterface $c) => $c->get($abstract),
                'singleton' => false,
            ];

            unset($this->instances[$concrete]);
        }
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    private function build(string|callable $concrete): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        return $this->resolve($concrete);
    }

    private function resolve(string $class): object
    {
        if (isset($this->resolving[$class])) {
            $chain = array_keys($this->resolving);
            $chain[] = $class;
            throw new CircularDependencyException(
                "Circular dependency detected: " . implode(' -> ', $chain)
            );
        }

        if (!class_exists($class)) {
            throw new ContainerException(
                "Unable to resolve '{$class}': class does not exist and no binding was registered."
            );
        }

        $this->resolving[$class] = true;

        try {
            $reflector = new ReflectionClass($class);

            if (!$reflector->isInstantiable()) {
                throw new ContainerException(
                    "Unable to resolve '{$class}': class is not instantiable."
                );
            }

            $constructor = $reflector->getConstructor();

            if ($constructor === null) {
                return new $class();
            }

            $parameters = $constructor->getParameters();
            $dependencies = array_map(
                fn(ReflectionParameter $param) => $this->resolveParameter($param, $class),
                $parameters
            );

            return $reflector->newInstanceArgs($dependencies);
        } finally {
            unset($this->resolving[$class]);
        }
    }

    private function resolveParameter(ReflectionParameter $param, string $forClass): mixed
    {
        $type = $param->getType();

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();

            try {
                return $this->get($typeName);
            } catch (CircularDependencyException $e) {
                // A cycle is a structural bug — silently substituting the
                // default value would mask it.
                throw $e;
            } catch (ContainerException $e) {
                // Genuinely unresolvable (unbound/unknown) → the declared
                // default is the intended fallback. Exceptions from user code
                // (factories, constructors) are not ContainerExceptions and
                // propagate instead of silently becoming the default.
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }

                throw $e;
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new ContainerException(
            "Unable to resolve parameter '\${$param->getName()}' "
            . "in class '{$forClass}': no type hint and no default value."
        );
    }
}
