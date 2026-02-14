<?php

declare(strict_types=1);

namespace Melodic\Data;

interface DbContextInterface
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T[]
     */
    public function query(string $class, string $sql, array $params = []): array;

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T|null
     */
    public function queryFirst(string $class, string $sql, array $params = []): ?object;

    public function command(string $sql, array $params = []): int;

    public function scalar(string $sql, array $params = []): mixed;

    public function transaction(callable $callback): mixed;

    public function lastInsertId(): int;
}
