<?php

declare(strict_types=1);

namespace Example\Services;

use Example\Commands\CreateUserCommand;
use Example\Commands\DeleteUserCommand;
use Example\Models\UserModel;
use Example\Queries\GetAllUsersQuery;
use Example\Queries\GetUserByIdQuery;
use Melodic\Service\Service;

class UserService extends Service implements UserServiceInterface
{
    /**
     * @return UserModel[]
     */
    public function getAll(): array
    {
        return (new GetAllUsersQuery())->execute($this->context);
    }

    public function getById(int $id): ?UserModel
    {
        return (new GetUserByIdQuery($id))->execute($this->context);
    }

    public function create(string $username, string $email): int
    {
        (new CreateUserCommand($username, $email))->execute($this->context);

        return $this->context->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $affected = (new DeleteUserCommand($id))->execute($this->context);

        return $affected > 0;
    }
}
