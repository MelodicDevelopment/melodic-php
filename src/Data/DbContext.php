<?php

declare(strict_types=1);

namespace Melodic\Data;

use PDO;
use PDOStatement;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

class DbContext implements DbContextInterface
{
    private readonly PDO $pdo;

    /** @var array<class-string, array<string, ReflectionProperty>> */
    private array $propertyMaps = [];

    /** @param array<int, mixed> $options */
    public function __construct(
        PDO|string $dsn,
        ?string $username = null,
        ?string $password = null,
        array $options = [],
    ) {
        if ($dsn instanceof PDO) {
            $this->pdo = $dsn;
        } else {
            $this->pdo = new PDO($dsn, $username, $password, $options);
        }

        // Applied to injected PDO instances too: DbContext's error handling
        // (transaction rollback in particular) assumes ERRMODE_EXCEPTION, and
        // hydration assumes associative rows.
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @param array<string, mixed> $params */
    public function query(string $class, string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        $reflector = new ReflectionClass($class);

        return array_map(
            fn(array $row) => $this->hydrate($reflector, $row),
            $rows
        );
    }

    /** @param array<string, mixed> $params */
    public function queryFirst(string $class, string $sql, array $params = []): ?object
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate(new ReflectionClass($class), $row);
    }

    /**
     * Stream rows one at a time instead of loading the whole result set into
     * memory — for exports and large batch jobs.
     *
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $params
     * @return \Generator<int, T>
     */
    public function cursor(string $class, string $sql, array $params = []): \Generator
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $reflector = new ReflectionClass($class);

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            /** @var T $object */
            $object = $this->hydrate($reflector, $row);

            yield $object;
        }
    }

    /** @param array<string, mixed> $params */
    public function command(string $sql, array $params = []): int
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->rowCount();
    }

    /**
     * Fetch the first column of the first row. Returns false when there are no
     * rows — which `fetchColumn()` also returns for a legitimate false/null/0/''
     * value, so this cannot distinguish "no rows" from a falsy result. For a
     * COUNT(*) the value is reliable; for nullable columns, prefer queryFirst().
     *
     * @param array<string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->fetchColumn();
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            $savepoint = 'sp_' . bin2hex(random_bytes(4));
            $this->pdo->exec("SAVEPOINT {$savepoint}");

            try {
                $result = $callback($this);
                $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");

                return $result;
            } catch (Throwable $e) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                throw $e;
            }
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $params */
    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_map(self::normalizeParam(...), $params));

        return $statement;
    }

    /**
     * Make a parameter safe to bind. PDO binds `false` as an empty string
     * (rejected by strict MySQL for numeric columns) and cannot bind objects
     * at all, so booleans become ints, backed enums their backing value, and
     * date/time objects a "Y-m-d H:i:s" string.
     */
    private static function normalizeParam(mixed $value): mixed
    {
        return match (true) {
            is_bool($value) => (int) $value,
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
            default => $value,
        };
    }

    /**
     * @param ReflectionClass<object> $reflector
     * @param array<string, mixed> $row
     */
    private function hydrate(ReflectionClass $reflector, array $row): object
    {
        if ($reflector->getName() === 'stdClass') {
            return (object) $row;
        }

        $instance = $reflector->newInstanceWithoutConstructor();
        $properties = $this->propertyMap($reflector);

        foreach ($row as $column => $value) {
            $property = $properties[$column] ?? null;

            if ($property !== null) {
                $property->setValue($instance, $this->castValue($property, $value));
            }
        }

        return $instance;
    }

    /**
     * Column-name → property lookup, built once per class instead of doing
     * two reflection lookups per column per row.
     *
     * @param ReflectionClass<object> $reflector
     * @return array<string, ReflectionProperty>
     */
    private function propertyMap(ReflectionClass $reflector): array
    {
        $class = $reflector->getName();

        if (!isset($this->propertyMaps[$class])) {
            $map = [];

            for ($current = $reflector; $current !== false; $current = $current->getParentClass()) {
                foreach ($current->getProperties() as $property) {
                    if (!$property->isStatic()) {
                        $map[$property->getName()] ??= $property;
                    }
                }
            }

            $this->propertyMaps[$class] = $map;
        }

        return $this->propertyMaps[$class];
    }

    private function castValue(ReflectionProperty $property, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $type = $property->getType();

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        if (!$type->isBuiltin()) {
            return $this->castObject($type->getName(), $value);
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $this->castBool($value),
            'string' => (string) $value,
            default => $value,
        };
    }

    /**
     * Cast a database scalar to a declared object type: backed-enum columns are
     * rehydrated via from() (bad data is a server-side integrity error, so it
     * throws), and DateTime-typed columns are parsed from the driver's string
     * form. Any other class passes through untouched.
     */
    private function castObject(string $class, mixed $value): mixed
    {
        if (is_subclass_of($class, \BackedEnum::class)) {
            $backingType = (string) (new \ReflectionEnum($class))->getBackingType();

            return $class::from($backingType === 'int' ? (int) $value : (string) $value);
        }

        if ($class === \DateTimeImmutable::class || $class === \DateTimeInterface::class) {
            return new \DateTimeImmutable((string) $value);
        }

        if ($class === \DateTime::class) {
            return new \DateTime((string) $value);
        }

        return $value;
    }

    /**
     * Cast a database value to bool. A plain (bool) cast mishandles the string
     * forms drivers return — notably Postgres "f"/"false" and "0", all of which
     * are truthy strings — so those are normalized to false explicitly.
     */
    private function castBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return !in_array(strtolower(trim($value)), ['', '0', 'f', 'false', 'no', 'off'], true);
        }

        return (bool) $value;
    }
}
