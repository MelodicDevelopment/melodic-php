<?php

declare(strict_types=1);

namespace Melodic\DI;

/**
 * Extends PSR-11, so the container can be handed to any library that accepts
 * a Psr\Container\ContainerInterface.
 */
interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    public function get(string $id): mixed;

    public function has(string $id): bool;

    public function bind(string $abstract, string|callable $concrete): void;

    public function singleton(string $abstract, string|callable $concrete): void;
}
