<?php

declare(strict_types=1);

namespace Melodic\Console\Make;

use Melodic\Console\Command;

class MakeEntityCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:entity', 'Generate CQRS entity files (DTO, queries, commands, service, controller)');
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? null;

        if ($name === null) {
            $this->error('Usage: make:entity <EntityName>');
            return 1;
        }

        $entity = Stub::pascalCase($name);
        $plural = Stub::pluralize($entity);
        $table = Stub::snakeCase($plural);
        $variable = Stub::camelCase($entity);

        $composerPath = getcwd() . '/composer.json';

        if (!file_exists($composerPath)) {
            $this->error('No composer.json found in the current directory.');
            return 1;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $psr4 = $composer['autoload']['psr-4'] ?? [];

        if (empty($psr4)) {
            $this->error('No PSR-4 autoload configuration found in composer.json.');
            return 1;
        }

        // Use the first PSR-4 namespace
        $namespace = rtrim(array_key_first($psr4), '\\');
        $srcDir = rtrim($psr4[array_key_first($psr4)], '/');
        $baseDir = getcwd() . '/' . $srcDir;

        $replacements = [
            'namespace' => $namespace,
            'entity' => $entity,
            'plural' => $plural,
            'table' => $table,
            'variable' => $variable,
        ];

        $this->writeln("Generating entity '{$entity}'...");

        $files = [
            "{$baseDir}/DTO/{$entity}Model.php" => self::MODEL_STUB,
            "{$baseDir}/Data/{$entity}/Queries/GetAll{$plural}Query.php" => self::GET_ALL_QUERY_STUB,
            "{$baseDir}/Data/{$entity}/Queries/Get{$entity}ByIdQuery.php" => self::GET_BY_ID_QUERY_STUB,
            "{$baseDir}/Data/{$entity}/Commands/Create{$entity}Command.php" => self::CREATE_COMMAND_STUB,
            "{$baseDir}/Data/{$entity}/Commands/Update{$entity}Command.php" => self::UPDATE_COMMAND_STUB,
            "{$baseDir}/Data/{$entity}/Commands/Delete{$entity}Command.php" => self::DELETE_COMMAND_STUB,
            "{$baseDir}/Services/{$entity}Service.php" => self::SERVICE_STUB,
            "{$baseDir}/Controllers/{$entity}Controller.php" => self::CONTROLLER_STUB,
        ];

        $created = 0;
        $skipped = 0;

        foreach ($files as $path => $stub) {
            if (file_exists($path)) {
                $relative = str_replace(getcwd() . '/', '', $path);
                $this->writeln("  skip  {$relative} (already exists)");
                $skipped++;
                continue;
            }

            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, Stub::render($stub, $replacements));

            $relative = str_replace(getcwd() . '/', '', $path);
            $this->writeln("  create  {$relative}");
            $created++;
        }

        // Remove .gitkeep files from directories that now have content
        foreach (['DTO', 'Data', 'Services', 'Controllers'] as $subdir) {
            $gitkeep = $baseDir . '/' . $subdir . '/.gitkeep';
            if (file_exists($gitkeep)) {
                unlink($gitkeep);
            }
        }

        $this->writeln('');
        $this->writeln("Created {$created} file(s), skipped {$skipped} file(s).");

        return 0;
    }

    private const MODEL_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\DTO;

use Melodic\Data\Model;

class {entity}Model extends Model
{
    public int $id;
}
PHP;

    private const GET_ALL_QUERY_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Data\{entity}\Queries;

use Melodic\Data\DbContextInterface;
use Melodic\Data\QueryInterface;
use {namespace}\DTO\{entity}Model;

class GetAll{plural}Query implements QueryInterface
{
    private readonly string $sql;

    public function __construct()
    {
        $this->sql = "SELECT * FROM {table}";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * @return array<{entity}Model>
     */
    public function execute(DbContextInterface $context): array
    {
        return $context->query({entity}Model::class, $this->sql);
    }
}
PHP;

    private const GET_BY_ID_QUERY_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Data\{entity}\Queries;

use Melodic\Data\DbContextInterface;
use Melodic\Data\QueryInterface;
use {namespace}\DTO\{entity}Model;

class Get{entity}ByIdQuery implements QueryInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "SELECT * FROM {table} WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): ?{entity}Model
    {
        return $context->queryFirst({entity}Model::class, $this->sql, ['id' => $this->id]);
    }
}
PHP;

    private const CREATE_COMMAND_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Data\{entity}\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class Create{entity}Command implements CommandInterface
{
    private readonly string $sql;

    public function __construct()
    {
        $this->sql = "INSERT INTO {table} () VALUES ()";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql);
    }
}
PHP;

    private const UPDATE_COMMAND_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Data\{entity}\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class Update{entity}Command implements CommandInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "UPDATE {table} SET  WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, ['id' => $this->id]);
    }
}
PHP;

    private const DELETE_COMMAND_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Data\{entity}\Commands;

use Melodic\Data\CommandInterface;
use Melodic\Data\DbContextInterface;

class Delete{entity}Command implements CommandInterface
{
    private readonly string $sql;

    public function __construct(
        private readonly int $id,
    ) {
        $this->sql = "DELETE FROM {table} WHERE id = :id";
    }

    public function getSql(): string
    {
        return $this->sql;
    }

    public function execute(DbContextInterface $context): int
    {
        return $context->command($this->sql, ['id' => $this->id]);
    }
}
PHP;

    private const SERVICE_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Services;

use Melodic\Service\Service;
use {namespace}\Data\{entity}\Commands\Create{entity}Command;
use {namespace}\Data\{entity}\Commands\Delete{entity}Command;
use {namespace}\Data\{entity}\Commands\Update{entity}Command;
use {namespace}\Data\{entity}\Queries\GetAll{plural}Query;
use {namespace}\Data\{entity}\Queries\Get{entity}ByIdQuery;
use {namespace}\DTO\{entity}Model;

class {entity}Service extends Service
{
    /**
     * @return array<{entity}Model>
     */
    public function getAll(): array
    {
        return (new GetAll{plural}Query())->execute($this->context);
    }

    public function getById(int $id): ?{entity}Model
    {
        return (new Get{entity}ByIdQuery($id))->execute($this->context);
    }

    public function create(): int
    {
        (new Create{entity}Command())->execute($this->context);
        return $this->context->lastInsertId();
    }

    public function update(int $id): int
    {
        return (new Update{entity}Command($id))->execute($this->context);
    }

    public function delete(int $id): int
    {
        return (new Delete{entity}Command($id))->execute($this->context);
    }
}
PHP;

    private const CONTROLLER_STUB = <<<'PHP'
<?php

declare(strict_types=1);

namespace {namespace}\Controllers;

use Melodic\Controller\ApiController;
use Melodic\Http\JsonResponse;
use Melodic\Http\Response;
use {namespace}\Services\{entity}Service;

class {entity}Controller extends ApiController
{
    public function __construct(
        private readonly {entity}Service ${variable}Service,
    ) {}

    public function index(): JsonResponse
    {
        ${plural} = $this->{variable}Service->getAll();
        return $this->json(array_map(fn($item) => $item->toArray(), ${plural}));
    }

    public function show(string $id): JsonResponse
    {
        ${variable} = $this->{variable}Service->getById((int) $id);
        return ${variable} ? $this->json(${variable}->toArray()) : $this->notFound();
    }

    public function store(): JsonResponse
    {
        $id = $this->{variable}Service->create();
        ${variable} = $this->{variable}Service->getById($id);
        return $this->created(${variable}?->toArray());
    }

    public function update(string $id): JsonResponse
    {
        $this->{variable}Service->update((int) $id);
        ${variable} = $this->{variable}Service->getById((int) $id);
        return ${variable} ? $this->json(${variable}->toArray()) : $this->notFound();
    }

    public function destroy(string $id): Response
    {
        $this->{variable}Service->delete((int) $id);
        return $this->noContent();
    }
}
PHP;
}
