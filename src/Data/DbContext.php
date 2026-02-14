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

    public function query(string $class, string $sql, array $params = []): array
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return array_map(
            fn(array $row) => $this->hydrate($class, $row),
            $rows
        );
    }

    public function queryFirst(string $class, string $sql, array $params = []): ?object
    {
        $statement = $this->prepareAndExecute($sql, $params);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrate($class, $row);
    }

    public function command(string $sql, array $params = []): int
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->rowCount();
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $statement = $this->prepareAndExecute($sql, $params);

        return $statement->fetchColumn();
    }

    public function transaction(callable $callback): mixed
    {
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

    private function prepareAndExecute(string $sql, array $params): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    private function hydrate(string $class, array $row): object
    {
        $reflector = new ReflectionClass($class);
        $instance = $reflector->newInstanceWithoutConstructor();

        foreach ($row as $column => $value) {
            if ($reflector->hasProperty($column)) {
                $property = $reflector->getProperty($column);
                $property->setAccessible(true);
                $property->setValue($instance, $value);
            }
        }

        return $instance;
    }
}
