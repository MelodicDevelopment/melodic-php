<?php

declare(strict_types=1);

namespace Melodic\Service;

use Melodic\Data\DbContextInterface;

class Service
{
    public function __construct(
        protected readonly DbContextInterface $context,
        protected readonly ?DbContextInterface $readOnlyContext = null,
    ) {
    }

    public function __destruct()
    {
        // Cleanup/disposal hook — override in subclasses as needed.
    }

    protected function getContext(): DbContextInterface
    {
        return $this->context;
    }

    protected function getReadOnlyContext(): DbContextInterface
    {
        return $this->readOnlyContext ?? $this->context;
    }
}
