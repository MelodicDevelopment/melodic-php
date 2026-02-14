<?php

declare(strict_types=1);

namespace Example\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class DeleteUserCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "DELETE FROM users WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, ['id' => $this->id]);
    }
}
