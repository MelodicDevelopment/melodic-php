<?php

declare(strict_types=1);

namespace Example\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class CreateUserCommand implements CommandInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly string $username,
        private readonly string $email,
    ) {
        $this->sql = "INSERT INTO users (username, email, created_at) VALUES (:username, :email, :createdAt)";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, [
            'username' => $this->username,
            'email' => $this->email,
            'createdAt' => date('Y-m-d H:i:s'),
        ]);
    }
}
