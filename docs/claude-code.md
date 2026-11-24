# Claude Code Integration

The Melodic PHP framework ships with Claude Code agents and skills that help you build applications faster. These provide framework-aware scaffolding, architecture guidance, and pattern enforcement.

## Installation

After installing the framework via Composer, run this from your project root (the command installs into the current working directory):

```bash
vendor/bin/melodic claude:install
```

This copies the following from the installed framework package into your project:

- **Agents** — AI assistants with deep framework knowledge → `.claude/agents/`
- **Skills** — Slash commands for common tasks → `.claude/skills/`
- **CLAUDE.md** — A starter project conventions file, written to the project root (only if one doesn't already exist). It includes a "Melodic framework reference" section that imports `vendor/melodicdev/framework/MELODIC_PHP.md`, the framework's usage reference.

| Installed file | Source |
|---|---|
| `.claude/agents/melodic-expert.md` | copied from the framework |
| `.claude/skills/melodic:scaffold-app/SKILL.md` | copied from the framework |
| `.claude/skills/melodic:scaffold-resource/SKILL.md` | copied from the framework |
| `.claude/skills/melodic:add-middleware/SKILL.md` | copied from the framework |
| `CLAUDE.md` | generated from a built-in template |

Agent and skill files that already exist are skipped. Use `--force` to overwrite them — for example to pick up updated versions after upgrading the framework (any local edits to those files are lost):

```bash
vendor/bin/melodic claude:install --force
```

`--force` does not apply to `CLAUDE.md`: an existing `CLAUDE.md` is never overwritten. If the existing file does not mention `MELODIC_PHP.md`, the "Melodic framework reference" section is appended to the end of it (since 4.1) — nothing else in the file is touched — and the command reports `Updated  CLAUDE.md`; run it again and the file is left alone.

The command prints a `Created` / `Skipped` line per file and a summary of what was installed.

## Available Agents

### `melodic-expert`

A framework expert that understands the full architecture: CQRS patterns, middleware pipeline, DI container, routing, JWT authentication, MVC views, validation, and all conventions. Use it for:

- Architecture questions ("how should I structure this feature?")
- Debugging help ("why isn't my middleware running?")
- Code review with framework awareness
- Understanding the request lifecycle

## Available Skills

The skills are user-invoked slash commands (they are marked `disable-model-invocation`, so Claude does not run them on its own). Each one asks a few questions and then has Claude write the files. The agent and all three skills instruct Claude to read `vendor/melodicdev/framework/MELODIC_PHP.md` first and to treat the installed framework source as authoritative.

### `/melodic:scaffold-app [directory]`

Scaffold a complete new Melodic application. Prompts for app type (Web + API, API only, or Web only), authentication providers, database, and a starter entity.

The result uses the same layout as `vendor/bin/melodic make:project` (`src/DTO/`, `src/Data/{Entity}/Queries|Commands/`, routes in `public/index.php`, `loadEnvironmentConfig()`, a root namespace derived from the directory name), filled in with what the CLI skeleton leaves out: the database binding in `AppServiceProvider`, authentication wiring, a `bin/console` with the migration commands, a first migration, and a working CRUD resource for the starter entity.

```
/melodic:scaffold-app my-api
```

### `/melodic:scaffold-resource [EntityName]`

Scaffold a full, working CQRS resource: Model plus `Create…Request` / `Update…Request` DTOs, Queries (GetAll, GetById), Commands (Create, Update, Delete), Service, and an API controller and/or MVC controller with views. It then adds the routes and writes a migration file (`migrations/{timestamp}_create_{table}.sql`) when the project uses migrations, or otherwise prints the `CREATE TABLE` SQL. Prompts for entity fields, controller type, and auth requirements.

Files go in the same places `make:entity` uses (`src/DTO/`, `src/Data/{Entity}/Queries|Commands/`, `src/Services/`, `src/Controllers/`); unlike the CLI command, the Create/Update commands and controller are written out in full rather than left as TODO stubs. If the project uses a different layout, the skill follows the project.

```
/melodic:scaffold-resource Product
```

### `/melodic:add-middleware [Name]`

Scaffold a new middleware class. It first checks whether a built-in middleware (CORS, security headers, rate limiting, CSRF, authentication, authorization) already covers the need and, if so, registers that instead. Otherwise it prompts for its purpose, behavior (before/after/wrapping), configuration needs, and whether it runs on specific routes or on every request. Generates the class and shows how to register it.

```
/melodic:add-middleware RateLimiting
```

### Skills vs. the `make:*` commands

The skills and the CLI scaffolding commands target the same project layout, so they can be mixed freely. The difference is how much they produce: `make:project` and `make:entity` write fixed templates with TODO placeholders and no database, auth, or routes for the new entity, while the skills generate complete, working code from your answers. See [Console](console.md) for the `make:*` commands.

## Documentation Availability

The `docs/` directory is excluded from Composer distribution installs (`export-ignore` in `.gitattributes`), so it is not present under `vendor/melodicdev/framework/` in a normal install. What does ship alongside `src/` and `.claude/`: `README.md`, `MELODIC_PHP.md` (the usage reference the agent and skills read), `CHANGELOG.md`, and the `MIGRATION-*.md` guides. The guides in `docs/` are available in the [framework repository](https://github.com/MelodicDevelopment/melodic-php/tree/main/docs).

## Customization

After installation, the files in your project's `.claude/` directory are yours to modify. You can:

- Edit the agent to add project-specific knowledge
- Customize skill templates to match your team's patterns
- Add your own skills and agents alongside the Melodic ones
- Update the generated `CLAUDE.md` with project-specific conventions

Re-running `claude:install` leaves your modified files alone unless you pass `--force`.
