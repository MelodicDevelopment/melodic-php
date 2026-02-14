<?php

declare(strict_types=1);

namespace Example\Services;

use Example\Models\UserModel;

interface UserServiceInterface
{
    /** @return UserModel[] */
    public function getAll(): array;

    public function getById(int $id): ?UserModel;

    public function create(string $username, string $email): int;

    public function delete(int $id): bool;
}
