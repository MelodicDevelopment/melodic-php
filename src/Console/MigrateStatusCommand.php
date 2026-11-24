<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Data\Migration\Migrator;

class MigrateStatusCommand extends Command
{
    public function __construct(
        private readonly Migrator $migrator,
    ) {
        parent::__construct('migrate:status', 'Show which migrations have been applied');
    }

    public function execute(array $args): int
    {
        $status = $this->migrator->status();

        if ($status === []) {
            $this->writeln('No migrations found.');
            return 0;
        }

        $this->table(
            ['Migration', 'Applied', 'Applied at'],
            array_map(
                fn(array $m): array => [$m['name'], $m['applied'] ? 'yes' : 'no', $m['appliedAt'] ?? ''],
                $status,
            ),
        );

        return 0;
    }
}
