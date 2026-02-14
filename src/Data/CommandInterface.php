<?php

declare(strict_types=1);

namespace Melodic\Data;

interface CommandInterface
{
    public function getSql(): string;

    public function execute(DbContextInterface $context): int;
}
