<?php

declare(strict_types=1);

namespace Melodic\DI;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

class Container implements ContainerInterface
{
    private array $bindings = [];
    private array $instances = [];
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
            throw new RuntimeException(
                "Circular dependency detected: " . implode(' -> ', $chain)
            );
        }

        if (!class_exists($class)) {
            throw new RuntimeException(
                "Unable to resolve '{$class}': class does not exist and no binding was registered."
            );
        }

        $this->resolving[$class] = true;

        try {
            $reflector = new ReflectionClass($class);

            if (!$reflector->isInstantiable()) {
                throw new RuntimeException(
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
            } catch (RuntimeException $e) {
                if ($param->isDefaultValueAvailable()) {
                    return $param->getDefaultValue();
                }

                throw $e;
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new RuntimeException(
            "Unable to resolve parameter '\${$param->getName()}' "
            . "in class '{$forClass}': no type hint and no default value."
        );
    }
}
