# Claude Code Integration

The Melodic PHP framework ships with Claude Code agents and skills that help you build applications faster. These provide framework-aware scaffolding, architecture guidance, and pattern enforcement.

## Installation

After installing the framework via Composer, run:

```bash
vendor/bin/melodic claude:install
```

This copies the following into your project's `.claude/` directory:

- **Agents** — AI assistants with deep framework knowledge
- **Skills** — Slash commands for common tasks
- **CLAUDE.md** — Project conventions file (only if one doesn't already exist)

Use `--force` to overwrite existing files:

```bash
vendor/bin/melodic claude:install --force
```

## Available Agents

### `melodic-expert`

A framework expert that understands the full architecture: CQRS patterns, middleware pipeline, DI container, routing, JWT authentication, MVC views, validation, and all conventions. Use it for:

- Architecture questions ("how should I structure this feature?")
- Debugging help ("why isn't my middleware running?")
- Code review with framework awareness
- Understanding the request lifecycle

## Available Skills

### `/melodic:scaffold-app [directory]`

Scaffold a complete new Melodic application. Prompts for app type (API, MVC, or both), authentication, database, and a starter entity. Generates a working project with all boilerplate.

```
/melodic:scaffold-app my-api
```

### `/melodic:scaffold-resource [EntityName]`

Scaffold a full CQRS resource: Model, Queries (GetAll, GetById), Commands (Create, Update, Delete), Service, and Controller. Prompts for entity fields, controller type, and auth requirements.

```
/melodic:scaffold-resource Product
```

### `/melodic:add-middleware [Name]`

Scaffold a new middleware class. Prompts for behavior (before/after/wrapping) and configuration needs. Generates the class and shows how to register it.

```
/melodic:add-middleware RateLimiting
```

## Customization

After installation, the files in your project's `.claude/` directory are yours to modify. You can:

- Edit the agent to add project-specific knowledge
- Customize skill templates to match your team's patterns
- Add your own skills and agents alongside the Melodic ones
- Update the generated `CLAUDE.md` with project-specific conventions
