<?php

declare(strict_types=1);

namespace Melodic\DI;

interface ContainerInterface
{
    public function get(string $id): mixed;

    public function has(string $id): bool;

    public function bind(string $abstract, string|callable $concrete): void;

    public function singleton(string $abstract, string|callable $concrete): void;
}
