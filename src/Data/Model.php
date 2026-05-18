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
            // Try the key as-is (PascalCase from DB), then ucfirst (camelCase from frontend),
            // then strtoupper for acronym properties like $EIN/$URL/$SSN sent as lowercase JSON.
            $propertyName = match (true) {
                $reflector->hasProperty($key) => $key,
                $reflector->hasProperty(ucfirst($key)) => ucfirst($key),
                $reflector->hasProperty(strtoupper($key)) => strtoupper($key),
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
                $name = $property->getName();
                // All-caps acronym properties ($EIN, $URL) serialize fully lowercase to match
                // JSON convention; mixed/PascalCase keeps lcfirst so $UserName -> "userName".
                $key = ctype_upper($name) ? strtolower($name) : lcfirst($name);
                $result[$key] = $property->getValue($this);
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
