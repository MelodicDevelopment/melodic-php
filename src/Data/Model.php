<?php

declare(strict_types=1);

namespace Melodic\Data;

use ReflectionClass;
use ReflectionProperty;

class Model implements \JsonSerializable
{
    public static function fromArray(array $data): static
    {
        $reflector = new ReflectionClass(static::class);
        $instance = $reflector->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            // Try the key as-is (PascalCase from DB), then ucfirst (camelCase from frontend)
            $propertyName = match (true) {
                $reflector->hasProperty($key) => $key,
                $reflector->hasProperty(ucfirst($key)) => ucfirst($key),
                default => null,
            };

            if ($propertyName !== null) {
                $property = $reflector->getProperty($propertyName);
                $property->setValue($instance, $value);
            }
        }

        // Initialize any remaining nullable properties that weren't in the input
        foreach ($reflector->getProperties(ReflectionProperty::IS_PUBLIC) as $prop) {
            if (!$prop->isInitialized($instance) && $prop->getType()?->allowsNull()) {
                $prop->setValue($instance, null);
            }
        }

        return $instance;
    }

    public function toArray(): array
    {
        $reflector = new ReflectionClass($this);
        $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            if ($property->isInitialized($this)) {
                $result[lcfirst($property->getName())] = $property->getValue($this);
            }
        }

        return $result;
    }

    /**
     * Return all initialized properties with PascalCase keys.
     * Booleans are converted to ints for PDO compatibility.
     */
    public function toPascalArray(): array
    {
        $reflector = new ReflectionClass($this);
        $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            if ($property->isInitialized($this)) {
                $value = $property->getValue($this);
                $result[$property->getName()] = is_bool($value) ? (int) $value : $value;
            }
        }

        return $result;
    }

    /**
     * Return only non-null properties with PascalCase keys.
     * Used for partial updates where null means "not provided".
     * Booleans are converted to ints for PDO compatibility.
     */
    public function toUpdateArray(): array
    {
        $reflector = new ReflectionClass($this);
        $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            if ($property->isInitialized($this)) {
                $value = $property->getValue($this);

                if ($value !== null) {
                    $result[$property->getName()] = is_bool($value) ? (int) $value : $value;
                }
            }
        }

        return $result;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
