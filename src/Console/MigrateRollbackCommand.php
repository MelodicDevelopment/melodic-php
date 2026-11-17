<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Data\Migration\Migrator;

class MigrateRollbackCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct('migrate:rollback', 'Roll back the last migration (--steps=N for more)');
    }

    public function execute(array $args): int
    {
        $steps = 1;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--steps=')) {
                $steps = max(1, (int) substr($arg, 8));
            }
        }

        try {
            $rolledBack = $this->migrator->rollback($steps);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if ($rolledBack === []) {
            $this->writeln('Nothing to roll back.');
            return 0;
        }

        foreach ($rolledBack as $name) {
            $this->writeln("  Rolled back  {$name}");
        }

        return 0;
    }
}
