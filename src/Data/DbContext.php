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
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        }
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
        $statement->execute($params);

        return $statement;
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

        foreach ($row as $column => $value) {
            if ($reflector->hasProperty($column)) {
                $property = $reflector->getProperty($column);
                $property->setValue($instance, $this->castValue($property, $value));
            }
        }

        return $instance;
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

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => $this->castBool($value),
            'string' => (string) $value,
            default => $value,
        };
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
