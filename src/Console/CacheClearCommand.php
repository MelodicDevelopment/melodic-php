<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Cache\CacheInterface;

class CacheClearCommand extends Command
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
        parent::__construct('cache:clear', 'Clear the application cache');
    }

    public function execute(array $args): int
    {
        if ($this->cache->clear()) {
            $this->writeln('Cache cleared successfully.');
            return 0;
        }

        $this->error('Failed to clear cache.');
        return 1;
    }
}
