<?php

declare(strict_types=1);

namespace Melodic\Data;

interface QueryInterface
{
    public function getSql(): string;

    public function execute(DbContextInterface $context): mixed;
}
