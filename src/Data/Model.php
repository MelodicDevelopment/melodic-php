<?php

declare(strict_types=1);

namespace Melodic\Data;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class Model implements \JsonSerializable
{
    /** @var array<string, true> Resolved property names that were sourced from fromArray input. */
    private array $_providedKeys = [];

    /** @param array<string, mixed> $data */
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
                $property->setValue($instance, self::coerceValue($property, $value, $key));
                $instance->_providedKeys[$propertyName] = true;
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

    /**
     * Coerce a raw input value to a property's declared scalar type so that
     * well-formed-but-loosely-typed client input (e.g. "5" for an int) binds
     * cleanly under strict_types. Values that cannot be coerced raise a
     * ModelBindingException, which the routing layer turns into a 400.
     */
    private static function coerceValue(ReflectionProperty $property, mixed $value, string $field): mixed
    {
        $type = $property->getType();

        // Union/intersection/no type hint: pass through untouched.
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        if ($value === null) {
            if ($type->allowsNull()) {
                return null;
            }

            throw new ModelBindingException($field, "Field '{$field}' may not be null.");
        }

        // Non-builtin types (nested models, enums, DateTime, ...) are left as-is.
        if (!$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => self::toInt($value, $field),
            'float' => self::toFloat($value, $field),
            'bool' => self::toBool($value, $field),
            'string' => self::toString($value, $field),
            default => $value, // array, object, mixed, iterable — no coercion
        };
    }

    private static function toInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (is_float($value) && floor($value) === $value) {
            return (int) $value;
        }

        throw new ModelBindingException($field, "Field '{$field}' must be an integer.");
    }

    private static function toFloat(mixed $value, string $field): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw new ModelBindingException($field, "Field '{$field}' must be a number.");
    }

    private static function toBool(mixed $value, string $field): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === 1 || $value === 0) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', ''], true)) {
                return false;
            }
        }

        throw new ModelBindingException($field, "Field '{$field}' must be a boolean.");
    }

    private static function toString(mixed $value, string $field): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw new ModelBindingException($field, "Field '{$field}' must be a string.");
    }

    /** @return array<string, mixed> */
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
     *
     * @return array<string, mixed>
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
     * Return all properties that were provided to fromArray, including explicit nulls.
     * Used for partial updates where absent means "not provided" and null means
     * "clear this field" (per RFC 7396 JSON Merge Patch). Properties assigned
     * programmatically after fromArray are NOT tracked — fields_set reflects the
     * wire, not subsequent mutation. Booleans are converted to ints for PDO
     * compatibility.
     *
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        $reflector = new ReflectionClass($this);
        $result = [];

        foreach (array_keys($this->_providedKeys) as $propertyName) {
            $value = $reflector->getProperty($propertyName)->getValue($this);
            $result[$propertyName] = is_bool($value) ? (int) $value : $value;
        }

        return $result;
    }

    /**
     * Returns whether the given property was sourced from fromArray input.
     * Pass the resolved property name (e.g. "EIN"), not the raw input key.
     */
    public function wasProvided(string $key): bool
    {
        return isset($this->_providedKeys[$key]);
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
