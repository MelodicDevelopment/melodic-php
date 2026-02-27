<?php

declare(strict_types=1);

namespace Melodic\Console;

use Melodic\Framework;

class Console
{
    /** @var array<string, CommandInterface> */
    private array $commands = [];

    private string $name = 'Melodic Console';

    private string $version = Framework::VERSION;

    public function register(CommandInterface $command): void
    {
        $this->commands[$command->getName()] = $command;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    /**
     * @param array<string> $argv
     */
    public function run(array $argv): int
    {
        $commandName = $argv[1] ?? null;

        if ($commandName === null || $commandName === 'help') {
            $this->showHelp();
            return 0;
        }

        if (!isset($this->commands[$commandName])) {
            echo "Unknown command: {$commandName}" . PHP_EOL . PHP_EOL;
            $this->showHelp();
            return 1;
        }

        $args = array_slice($argv, 2);

        return $this->commands[$commandName]->execute($args);
    }

    private function showHelp(): void
    {
        echo "{$this->name} {$this->version}" . PHP_EOL;
        echo PHP_EOL;
        echo "Available commands:" . PHP_EOL;

        foreach ($this->commands as $name => $command) {
            echo "  {$name}" . str_repeat(' ', max(1, 24 - strlen($name))) . $command->getDescription() . PHP_EOL;
        }
    }
}
