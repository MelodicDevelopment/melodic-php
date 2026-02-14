<?php

declare(strict_types=1);

namespace Melodic\Data;

use ReflectionClass;
use ReflectionProperty;

class Model
{
    public static function fromArray(array $data): static
    {
        $reflector = new ReflectionClass(static::class);
        $instance = $reflector->newInstanceWithoutConstructor();

        foreach ($data as $key => $value) {
            if ($reflector->hasProperty($key)) {
                $property = $reflector->getProperty($key);
                $property->setAccessible(true);
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
                $result[$property->getName()] = $property->getValue($this);
            }
        }

        return $result;
    }
}
