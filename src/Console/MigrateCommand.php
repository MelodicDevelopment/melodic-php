<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Data\Migration\Migrator;

class MigrateCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct('migrate', 'Apply pending database migrations');
    }

    public function execute(array $args): int
    {
        try {
            $applied = $this->migrator->migrate();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($applied === []) {
            $this->writeln('Nothing to migrate.');
            return 0;
        }

        foreach ($applied as $name) {
            $this->writeln("  Migrated  {$name}");
        }

        return 0;
    }
}
