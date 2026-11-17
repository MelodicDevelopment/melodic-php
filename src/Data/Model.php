<?php

declare(strict_types=1);

namespace Melodic\Data;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

class Model implements \JsonSerializable
{
    /** @var array<class-string, ReflectionClass<object>> Per-class reflection cache — binding and serialization are hot paths. */
    private static array $reflectors = [];

    /** @var array<string, true> Resolved property names that were sourced from fromArray input. */
    private array $_providedKeys = [];

    /**
     * @param class-string $class
     * @return ReflectionClass<object>
     */
    private static function reflector(string $class): ReflectionClass
    {
        return self::$reflectors[$class] ??= new ReflectionClass($class);
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): static
    {
        $reflector = self::reflector(static::class);
        /** @var static $instance */
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

                // #[Guarded] properties never bind from wire input (mass-
                // assignment defense); they can only be set programmatically.
                if ($property->getAttributes(Guarded::class) !== []) {
                    continue;
                }

                $coerced = self::coerceValue($property, $value, (string) $key);

                try {
                    $property->setValue($instance, $coerced);
                } catch (\TypeError) {
                    // Safety net for anything coerceValue passes through
                    // (union types, unknown classes): a wire value of the
                    // wrong shape is a client error (400), never a 500.
                    throw new ModelBindingException((string) $key, "Field '{$key}' has an invalid type.");
                }

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

        // Non-builtin types: backed enums and DateTime are coerced from wire
        // scalars (bad input → 400); other classes (nested models, ...) pass
        // through untouched.
        if (!$type->isBuiltin()) {
            return self::toObject($type->getName(), $value, $field);
        }

        return match ($type->getName()) {
            'int' => self::toInt($value, $field),
            'float' => self::toFloat($value, $field),
            'bool' => self::toBool($value, $field),
            'string' => self::toString($value, $field),
            'array' => self::toArrayValue($property, $value, $field),
            default => $value, // object, mixed, iterable — no coercion
        };
    }

    /**
     * @return array<mixed>
     */
    private static function toArrayValue(ReflectionProperty $property, mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new ModelBindingException($field, "Field '{$field}' must be an array.");
        }

        $listOf = $property->getAttributes(ListOf::class)[0] ?? null;

        if ($listOf === null) {
            return $value;
        }

        // #[ListOf(Foo::class)]: hydrate each element into a model.
        $class = $listOf->newInstance()->class;
        $items = [];

        foreach ($value as $index => $item) {
            $items[$index] = self::toObject($class, $item, $field . '.' . $index);
        }

        return $items;
    }

    private static function toObject(string $class, mixed $value, string $field): mixed
    {
        if ($value instanceof $class) {
            return $value;
        }

        if (is_subclass_of($class, \BackedEnum::class)) {
            return self::toEnum($class, $value, $field);
        }

        // Nested model: hydrate from a JSON object. Binding errors are
        // reported with a dotted path (e.g. "address.city").
        if (is_a($class, self::class, true)) {
            if (!is_array($value)) {
                throw new ModelBindingException($field, "Field '{$field}' must be an object.");
            }

            try {
                return $class::fromArray($value);
            } catch (ModelBindingException $e) {
                throw new ModelBindingException(
                    $field . '.' . $e->field,
                    str_replace("'{$e->field}'", "'{$field}.{$e->field}'", $e->getMessage()),
                );
            }
        }

        if (in_array($class, [\DateTimeImmutable::class, \DateTimeInterface::class, \DateTime::class], true)) {
            return self::toDateTime($class, $value, $field);
        }

        return $value;
    }

    /** @param class-string<\BackedEnum> $class */
    private static function toEnum(string $class, mixed $value, string $field): \BackedEnum
    {
        $backingType = (string) (new \ReflectionEnum($class))->getBackingType();

        if ($backingType === 'int' && is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            $value = (int) $value;
        }

        $case = ($backingType === 'int' && is_int($value)) || ($backingType === 'string' && is_string($value))
            ? $class::tryFrom($value)
            : null;

        if ($case === null) {
            throw new ModelBindingException($field, "Field '{$field}' is not one of the allowed values.");
        }

        return $case;
    }

    private static function toDateTime(string $class, mixed $value, string $field): \DateTimeInterface
    {
        if (!is_string($value) || trim($value) === '') {
            throw new ModelBindingException($field, "Field '{$field}' must be a date/time string.");
        }

        try {
            return $class === \DateTime::class ? new \DateTime($value) : new \DateTimeImmutable($value);
        } catch (\Exception) {
            throw new ModelBindingException($field, "Field '{$field}' must be a valid date/time.");
        }
    }

    private static function toInt(mixed $value, string $field): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            // An (int) cast silently clamps digits that overflow PHP_INT_MAX;
            // FILTER_VALIDATE_INT rejects them (it also rejects leading
            // zeros, so those are stripped first).
            $negative = $value[0] === '-';
            $digits = ltrim($negative ? substr($value, 1) : $value, '0');
            $number = filter_var(($negative ? '-' : '') . ($digits === '' ? '0' : $digits), FILTER_VALIDATE_INT);

            if ($number !== false) {
                return $number;
            }
        }

        if (is_float($value) && floor($value) === $value && abs($value) < (float) PHP_INT_MAX) {
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
        $reflector = self::reflector(static::class);
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
     * Booleans are converted to ints, backed enums to their backing value, and
     * date/time objects to "Y-m-d H:i:s" strings for PDO compatibility.
     *
     * @return array<string, mixed>
     */
    public function toPascalArray(): array
    {
        $reflector = self::reflector(static::class);
        $properties = $reflector->getProperties(ReflectionProperty::IS_PUBLIC);
        $result = [];

        foreach ($properties as $property) {
            if ($property->isInitialized($this)) {
                $result[$property->getName()] = self::toDatabaseValue($property->getValue($this));
            }
        }

        return $result;
    }

    /**
     * Return all properties that were provided to fromArray, including explicit nulls.
     * Used for partial updates where absent means "not provided" and null means
     * "clear this field" (per RFC 7396 JSON Merge Patch). Properties assigned
     * programmatically after fromArray are NOT tracked — fields_set reflects the
     * wire, not subsequent mutation. Values are converted for PDO the same way
     * as toPascalArray().
     *
     * @return array<string, mixed>
     */
    public function toUpdateArray(): array
    {
        $reflector = self::reflector(static::class);
        $result = [];

        foreach (array_keys($this->_providedKeys) as $propertyName) {
            $result[$propertyName] = self::toDatabaseValue($reflector->getProperty($propertyName)->getValue($this));
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

    /**
     * JSON form of the model: toArray() with date/time values rendered as
     * ISO-8601 strings (json_encode would otherwise emit PHP's internal
     * {date, timezone_type, timezone} object). Backed enums serialize to their
     * backing value natively.
     */
    public function jsonSerialize(): mixed
    {
        return array_map(
            static fn(mixed $value): mixed => $value instanceof \DateTimeInterface
                ? $value->format(\DateTimeInterface::ATOM)
                : $value,
            $this->toArray(),
        );
    }

    /**
     * Convert a property value to something PDO can bind: booleans become
     * ints, backed enums their backing value, and date/time objects a
     * "Y-m-d H:i:s" string.
     */
    private static function toDatabaseValue(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => (int) $value,
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }
}
