<?php

declare(strict_types=1);

namespace Melodic\Console;


class MakeMigrationCommand extends Command
{
    public function __construct(
        private readonly ?string $directory = null,
    ) {
        parent::__construct('make:migration', 'Create a new timestamped SQL migration file');
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null || preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
            $this->error('Usage: make:migration <name>  (letters, digits, dashes, underscores — e.g. create_users)');
            return 1;
        }

        $directory = $this->directory ?? getcwd() . '/migrations';

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            $this->error("Unable to create directory: {$directory}");
            return 1;
        }

        $file = date('YmdHis') . '_' . strtolower(str_replace('-', '_', $name)) . '.sql';
        $path = rtrim($directory, '/') . '/' . $file;

        if (file_exists($path)) {
            $this->error("Migration already exists: {$file}");
            return 1;
        }

        file_put_contents($path, "-- Migration: {$name}\n\n\n-- @down\n\n");
        $this->writeln("Created {$file}");

        return 0;
    }
}
