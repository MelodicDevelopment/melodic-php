<?php

declare(strict_types=1);

namespace Melodic\Data\Migration;

use Melodic\Data\DbContextInterface;
use RuntimeException;

/**
 * Plain-SQL schema migrations — no DSL, no magic.
 *
 * Each migration is a `.sql` file in the migrations directory, applied in
 * file-name order (name them with a timestamp prefix, as `make:migration`
 * does: `20260115093000_create_users.sql`). An optional `-- @down` line
 * separates the forward SQL from the SQL that undoes it:
 *
 *     CREATE TABLE users (Id INTEGER PRIMARY KEY, Email VARCHAR(255) NOT NULL);
 *
 *     -- @down
 *     DROP TABLE users;
 *
 * Statements are split on a `;` at the end of a line. Applied migrations are
 * recorded in a tracking table (default `melodic_migrations`). Note that most
 * databases auto-commit DDL, so a migration that fails part-way is not rolled
 * back: keep each migration small.
 */
class Migrator
{
    private const DOWN_MARKER = '/^--\s*@down\s*$/mi';

    public function __construct(
        private readonly DbContextInterface $context,
        private readonly string $directory,
        private readonly string $table = 'melodic_migrations',
    ) {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $this->table) !== 1) {
            throw new \InvalidArgumentException("Invalid migrations table name '{$this->table}'.");
        }
    }

    /**
     * Apply every migration that has not run yet, oldest first.
     *
     * @return string[] Names of the migrations applied by this call.
     */
    public function migrate(): array
    {
        $applied = [];

        foreach ($this->status() as $migration) {
            if ($migration['applied']) {
                continue;
            }

            $name = $migration['name'];
            $this->run($this->sections($name)['up'], $name);
            $this->context->command(
                "INSERT INTO {$this->table} (name, applied_at) VALUES (:name, :applied_at)",
                ['name' => $name, 'applied_at' => date('Y-m-d H:i:s')],
            );

            $applied[] = $name;
        }

        return $applied;
    }

    /**
     * Undo the most recently applied migrations, newest first.
     *
     * @return string[] Names of the migrations rolled back.
     */
    public function rollback(int $steps = 1): array
    {
        $appliedNames = array_keys($this->appliedMigrations());
        rsort($appliedNames);

        $rolledBack = [];

        foreach (array_slice($appliedNames, 0, max(0, $steps)) as $name) {
            $down = $this->sections($name)['down'];

            // An empty section (the make:migration stub, never filled in) is
            // the same as none: "rolling back" would undo nothing and still
            // forget that the migration ran.
            if ($down === null || self::statements($down) === []) {
                throw new RuntimeException("Migration '{$name}' has no \"-- @down\" section and cannot be rolled back.");
            }

            $this->run($down, $name);
            $this->context->command("DELETE FROM {$this->table} WHERE name = :name", ['name' => $name]);

            $rolledBack[] = $name;
        }

        return $rolledBack;
    }

    /**
     * Every known migration (on disk or recorded as applied), in order.
     *
     * @return list<array{name: string, applied: bool, appliedAt: ?string}>
     */
    public function status(): array
    {
        $applied = $this->appliedMigrations();
        $names = array_unique(array_merge($this->availableMigrations(), array_keys($applied)));
        sort($names);

        return array_map(
            fn(string $name): array => [
                'name' => $name,
                'applied' => isset($applied[$name]),
                'appliedAt' => $applied[$name] ?? null,
            ],
            $names,
        );
    }

    /** @return string[] Migration names (file names without ".sql"), sorted. */
    private function availableMigrations(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.sql');

        if ($files === false) {
            return [];
        }

        $names = array_map(fn(string $file): string => basename($file, '.sql'), $files);
        sort($names);

        return $names;
    }

    /** @return array<string, string> name → applied_at */
    private function appliedMigrations(): array
    {
        $this->context->command(
            "CREATE TABLE IF NOT EXISTS {$this->table} ("
            . 'name VARCHAR(255) NOT NULL PRIMARY KEY, applied_at VARCHAR(32) NOT NULL)'
        );

        $applied = [];

        foreach ($this->context->query(\stdClass::class, "SELECT name, applied_at FROM {$this->table}") as $row) {
            $applied[(string) $row->name] = (string) $row->applied_at;
        }

        return $applied;
    }

    /** @return array{up: string, down: ?string} */
    private function sections(string $name): array
    {
        $path = rtrim($this->directory, '/') . '/' . $name . '.sql';
        $contents = is_file($path) ? file_get_contents($path) : false;

        if ($contents === false) {
            throw new RuntimeException("Migration file not found: {$path}");
        }

        $parts = preg_split(self::DOWN_MARKER, $contents, 2);

        return [
            'up' => $parts[0] ?? '',
            'down' => isset($parts[1]) ? $parts[1] : null,
        ];
    }

    /**
     * The executable statements in a section, comment-only chunks dropped.
     *
     * @return list<string>
     */
    private static function statements(string $sql): array
    {
        $statements = [];

        foreach (preg_split('/;[ \t]*(?:\r?\n|$)/', $sql) ?: [] as $statement) {
            // Drop comment-only chunks; a statement may still contain comments.
            if (trim((string) preg_replace('/^\s*--.*$/m', '', $statement)) !== '') {
                $statements[] = trim($statement);
            }
        }

        return $statements;
    }

    private function run(string $sql, string $name): void
    {
        foreach (self::statements($sql) as $statement) {
            try {
                $this->context->command($statement);
            } catch (\Throwable $e) {
                throw new RuntimeException("Migration '{$name}' failed: {$e->getMessage()}", 0, $e);
            }
        }
    }
}
