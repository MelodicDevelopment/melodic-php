<?php

declare(strict_types=1);

namespace Melodic\Data;

/**
 * One page of query results plus the numbers a client needs to page through
 * the rest. JSON shape: {items, total, page, perPage, pageCount}.
 *
 * @template T of object
 */
final class PaginatedResult implements \JsonSerializable
{
    /** @param T[] $items */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    public function pageCount(): int
    {
        return $this->perPage > 0 ? (int) ceil($this->total / $this->perPage) : 0;
    }

    public function hasMore(): bool
    {
        return $this->page < $this->pageCount();
    }

    /** @return array{items: T[], total: int, page: int, perPage: int, pageCount: int} */
    public function toArray(): array
    {
        return [
            'items' => $this->items,
            'total' => $this->total,
            'page' => $this->page,
            'perPage' => $this->perPage,
            'pageCount' => $this->pageCount(),
        ];
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
