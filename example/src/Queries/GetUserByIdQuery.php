<?php

declare(strict_types=1);

namespace Example\Queries;

use Example\Models\UserModel;
use Melodic\Data\DbContextInterface;
use Melodic\Data\QueryInterface;

class GetUserByIdQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "SELECT id, username, email, created_at AS createdAt FROM users WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): ?UserModel
    {
        return $context->queryFirst(UserModel::class, $this->sql, ['id' => $this->id]);
    }
}
