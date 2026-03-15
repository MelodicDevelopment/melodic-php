# Console

Melodic includes a lightweight CLI console for running commands from the terminal. It provides a `Console` runner, a `Command` base class with output helpers, and several built-in commands.

## Quick Start

Create a console entry point (e.g. `bin/console`):

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Console\Console;
use Melodic\Console\CacheClearCommand;
use Melodic\Console\RouteListCommand;

$console = new Console();
$console->setName('My App');
$console->setVersion('1.0.0');

// Register built-in commands
$console->register(new RouteListCommand($router));
$console->register(new CacheClearCommand($cache));

// Register custom commands
$console->register(new MigrateCommand($db));

exit($console->run($argv));
```

```bash
php bin/console                  # shows help with all available commands
php bin/console help             # same as above
php bin/console route:list       # list all registered routes
php bin/console cache:clear      # clear the application cache
```

## Writing Custom Commands

Extend the `Command` base class:

```php
use Melodic\Console\Command;

class GreetCommand extends Command
{
    public function __construct()
    {
        parent::__construct('greet', 'Greet a user by name');
    }

    public function execute(array $args): int
    {
        $name = $args[0] ?? 'World';
        $this->writeln("Hello, {$name}!");
        return 0;
    }
}
```

```bash
php bin/console greet Alice
# Hello, Alice!
```

### Return Codes

The `execute()` method returns an integer exit code:
- `0` — success
- Non-zero — failure

### Output Helpers

The `Command` base class provides:

| Method | Description |
|---|---|
| `writeln(string $text)` | Write to stdout with a newline |
| `write(string $text)` | Write to stdout without a newline |
| `error(string $text)` | Write to stderr with a newline |
| `table(array $headers, array $rows)` | Render a formatted ASCII table |

### Table Output

```php
$this->table(
    ['Method', 'Path', 'Controller'],
    [
        ['GET', '/users', 'UserController'],
        ['POST', '/users', 'UserController'],
    ],
);
```

Output:

```
+--------+--------+----------------+
| Method | Path   | Controller     |
+--------+--------+----------------+
| GET    | /users | UserController |
| POST   | /users | UserController |
+--------+--------+----------------+
```

## Implementing CommandInterface

For full control without extending `Command`, implement `CommandInterface` directly:

```php
use Melodic\Console\CommandInterface;

class CustomCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'custom:run';
    }

    public function getDescription(): string
    {
        return 'A fully custom command';
    }

    public function execute(array $args): int
    {
        // your logic
        return 0;
    }
}
```

## Built-in Commands

### `route:list`

Displays all registered routes in a table with method, path, controller, and action columns. Requires a `Router` instance.

### `cache:clear`

Calls `CacheInterface::clear()` to flush all cached data. Requires a `CacheInterface` instance. See [Cache](cache.md).

### `claude:install`

Installs Claude Code agents, skills, and a starter `CLAUDE.md` into your project. This enables framework-aware AI assistance when using [Claude Code](https://claude.com/claude-code).

```bash
vendor/bin/melodic claude:install           # install (skips existing files)
vendor/bin/melodic claude:install --force   # overwrite existing files
```

Installs:

| Type | Name | Description |
|---|---|---|
| Agent | `melodic-expert` | Framework expert for architecture, patterns, and debugging |
| Skill | `/melodic:scaffold-app` | Scaffold a new Melodic application |
| Skill | `/melodic:scaffold-resource` | Scaffold a full CQRS resource |
| Skill | `/melodic:add-middleware` | Scaffold a middleware class |

See [Claude Code Integration](claude-code.md) for full details.
