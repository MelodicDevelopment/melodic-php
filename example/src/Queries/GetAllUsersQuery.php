<?php

declare(strict_types=1);

namespace Example\Queries;

use Example\Models\UserModel;
use Melodic\Data\DbContextInterface;
use Melodic\Data\QueryInterface;

class GetAllUsersQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct()
    {
        $this->sql = "SELECT id, username, email, created_at AS createdAt FROM users ORDER BY id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return UserModel[]
     */
    public function execute(DbContextInterface $context): array
    {
        return $context->query(UserModel::class, $this->sql);
    }
}
