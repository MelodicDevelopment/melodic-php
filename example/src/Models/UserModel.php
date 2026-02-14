<?php

declare(strict_types=1);

namespace Example\Models;

use Melodic\Data\Model;

class UserModel extends Model
{
    public int $id;
    public string $username;
    public string $email;
    public string $createdAt;
}
