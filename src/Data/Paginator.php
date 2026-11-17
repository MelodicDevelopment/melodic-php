<?php

declare(strict_types=1);

namespace Melodic\Data;

/**
 * Paginates a plain SELECT. Use inside a Query object:
 *
 *     return Paginator::paginate($context, User::class, $this->sql, $params, $page, $perPage);
 *
 * The SQL must not already contain LIMIT/OFFSET and should have an ORDER BY
 * (pages are only stable with a deterministic order). Runs two statements:
 * a COUNT(*) over the query and the query itself with LIMIT/OFFSET — the
 * syntax shared by MySQL, MariaDB, PostgreSQL and SQLite.
 */
final class Paginator
{
    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $params
     * @param int $maxPerPage Upper bound on $perPage, so a client-supplied
     *                        page size cannot request the whole table.
     * @return PaginatedResult<T>
     */
    public static function paginate(
        DbContextInterface $context,
        string $class,
        string $sql,
        array $params = [],
        int $page = 1,
        int $perPage = 25,
        int $maxPerPage = 100,
    ): PaginatedResult {
        $page = max(1, $page);
        $perPage = max(1, min($perPage, max(1, $maxPerPage)));
        $sql = rtrim(trim($sql), ';');

        $total = (int) $context->scalar("SELECT COUNT(*) FROM ({$sql}) AS melodic_page_count", $params);

        // Integers are inlined rather than bound: both are clamped ints (no
        // injection surface), and a bound LIMIT is sent as a quoted string
        // by PDO's emulated prepares, which MySQL rejects.
        $offset = ($page - 1) * $perPage;
        $items = $total > 0 && $offset < $total
            ? $context->query($class, "{$sql} LIMIT {$perPage} OFFSET {$offset}", $params)
            : [];

        return new PaginatedResult($items, $total, $page, $perPage);
    }
}
