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

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
