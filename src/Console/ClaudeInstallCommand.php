<?php

declare(strict_types=1);

namespace Melodic\Console;

class ClaudeInstallCommand extends Command
{
    public function __construct()
    {
        parent::__construct('claude:install', 'Install Claude Code agents and skills into your project');
    }

    public function execute(array $args): int
    {
        $projectDir = getcwd();
        $frameworkDir = dirname(__DIR__, 2);
        $claudeDir = $projectDir . '/.claude';

        $this->writeln('Installing Melodic Claude Code configuration...');
        $this->writeln('');

        // Items to install — source path relative to framework .claude/ dir
        $agents = ['melodic-expert.md'];
        $skills = ['melodic:scaffold-resource', 'melodic:scaffold-app', 'melodic:add-middleware'];

        // Install agents
        $agentCount = 0;
        foreach ($agents as $agent) {
            $source = $frameworkDir . '/.claude/agents/' . $agent;
            $dest = $claudeDir . '/agents/' . $agent;

            if ($this->installFile($source, $dest, $args)) {
                $agentCount++;
            }
        }

        // Install skills
        $skillCount = 0;
        foreach ($skills as $skill) {
            $source = $frameworkDir . '/.claude/skills/' . $skill . '/SKILL.md';
            $dest = $claudeDir . '/skills/' . $skill . '/SKILL.md';

            if ($this->installFile($source, $dest, $args)) {
                $skillCount++;
            }
        }

        // Generate CLAUDE.md if it doesn't exist
        $claudeMdPath = $projectDir . '/CLAUDE.md';
        if (!file_exists($claudeMdPath)) {
            file_put_contents($claudeMdPath, self::CLAUDE_MD_STUB);
            $this->writeln('  Created  CLAUDE.md');
        } else {
            $this->writeln('  Skipped  CLAUDE.md (already exists)');
        }

        $this->writeln('');
        $this->writeln("Installed {$agentCount} agent(s) and {$skillCount} skill(s).");
        $this->writeln('');
        $this->writeln('Available skills:');
        $this->writeln('  /melodic:scaffold-app         Scaffold a new Melodic application');
        $this->writeln('  /melodic:scaffold-resource     Scaffold a CQRS resource (Model, Queries, Commands, Service, Controller)');
        $this->writeln('  /melodic:add-middleware        Scaffold a middleware class');
        $this->writeln('');
        $this->writeln('Available agents:');
        $this->writeln('  melodic-expert                 Framework expert for architecture, patterns, and debugging');

        return 0;
    }

    private function installFile(string $source, string $dest, array $args): bool
    {
        if (!file_exists($source)) {
            $this->error("  Missing  {$source}");
            return false;
        }

        $force = in_array('--force', $args, true);

        if (file_exists($dest) && !$force) {
            $this->writeln('  Skipped  ' . $this->relativePath($dest));
            return false;
        }

        $destDir = dirname($dest);
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        copy($source, $dest);
        $this->writeln('  Created  ' . $this->relativePath($dest));

        return true;
    }

    private function relativePath(string $path): string
    {
        $cwd = getcwd();

        if ($cwd !== false && str_starts_with($path, $cwd)) {
            return ltrim(substr($path, strlen($cwd)), '/');
        }

        return $path;
    }

    private const CLAUDE_MD_STUB = <<<'MARKDOWN'
# Project

This project is built with the [Melodic PHP Framework](https://github.com/MelodicDevelopment/melodic-php).

## Architecture

```
HTTP Request → Middleware Pipeline → Router → Controller → Service → Query/Command → Database
```

## Key Patterns

- **CQRS**: Query/Command objects in services (no mediator). Services call `(new SomeQuery($id))->execute($this->context)`.
- **Layering**: Controller → Service → Query/Command → DbContext. No direct DB access in controllers.
- **DI Container**: Auto-wiring with interface bindings. Register in ServiceProvider or bootstrap.
- **Model binding**: Controller action parameters typed as `Model` subclasses are auto-hydrated from request body and validated.

## Conventions

- PHP 8.2+ — enums, readonly properties, constructor promotion, match expressions
- `declare(strict_types=1)` in every PHP file
- PascalCase classes, camelCase methods/properties
- Use `Model::toPascalArray()` for INSERT params, `Model::toUpdateArray()` for UPDATE params
- Use `apiResource()` for standard CRUD routes

## Naming

| Type | Location | Pattern | Example |
|---|---|---|---|
| Model | `src/DTO/` | `{Entity}Model` | `ChurchModel` |
| Query | `src/Data/{Entity}/Queries/` | `Get{Entity}ByIdQuery` | `GetChurchByIdQuery` |
| Command | `src/Data/{Entity}/Commands/` | `Create{Entity}Command` | `CreateChurchCommand` |
| Service | `src/Services/` | `{Entity}Service` | `ChurchService` |
| Controller | `src/Controllers/` | `{Entity}Controller` | `ChurchController` |

## Claude Code

Run `vendor/bin/melodic claude:install` to install Melodic-specific agents and skills.
MARKDOWN;
}
