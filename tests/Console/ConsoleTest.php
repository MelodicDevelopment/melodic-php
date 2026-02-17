<?php

declare(strict_types=1);

namespace Tests\Console;

use Melodic\Console\Command;
use Melodic\Console\CommandInterface;
use Melodic\Console\Console;
use PHPUnit\Framework\TestCase;

class ConsoleTest extends TestCase
{
    private Console $console;

    protected function setUp(): void
    {
        $this->console = new Console();
    }

    public function testRegisterAndRunCommand(): void
    {
        $command = new class extends Command {
            public bool $executed = false;

            public function __construct()
            {
                parent::__construct('test:run', 'A test command');
            }

            public function execute(array $args): int
            {
                $this->executed = true;
                return 0;
            }
        };

        $this->console->register($command);
        $exitCode = $this->console->run(['app', 'test:run']);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($command->executed);
    }

    public function testCommandReceivesArguments(): void
    {
        $command = new class extends Command {
            public array $receivedArgs = [];

            public function __construct()
            {
                parent::__construct('test:args', 'Args test');
            }

            public function execute(array $args): int
            {
                $this->receivedArgs = $args;
                return 0;
            }
        };

        $this->console->register($command);
        $this->console->run(['app', 'test:args', 'foo', 'bar']);

        $this->assertSame(['foo', 'bar'], $command->receivedArgs);
    }

    public function testHelpOutputListsAllCommands(): void
    {
        $this->console->setName('Test App');
        $this->console->setVersion('2.0.0');

        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('alpha', 'First command');
            }

            public function execute(array $args): int
            {
                return 0;
            }
        });

        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('beta', 'Second command');
            }

            public function execute(array $args): int
            {
                return 0;
            }
        });

        ob_start();
        $exitCode = $this->console->run(['app', 'help']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Test App 2.0.0', $output);
        $this->assertStringContainsString('alpha', $output);
        $this->assertStringContainsString('First command', $output);
        $this->assertStringContainsString('beta', $output);
        $this->assertStringContainsString('Second command', $output);
    }

    public function testNoCommandShowsHelp(): void
    {
        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('test:cmd', 'A command');
            }

            public function execute(array $args): int
            {
                return 0;
            }
        });

        ob_start();
        $exitCode = $this->console->run(['app']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('test:cmd', $output);
    }

    public function testUnknownCommandShowsHelpAndReturnsOne(): void
    {
        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('real:cmd', 'A real command');
            }

            public function execute(array $args): int
            {
                return 0;
            }
        });

        ob_start();
        $exitCode = $this->console->run(['app', 'fake:cmd']);
        $output = ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Unknown command: fake:cmd', $output);
        $this->assertStringContainsString('Available commands:', $output);
        $this->assertStringContainsString('real:cmd', $output);
    }

    public function testExitCodesAreReturnedCorrectly(): void
    {
        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('success', 'Returns 0');
            }

            public function execute(array $args): int
            {
                return 0;
            }
        });

        $this->console->register(new class extends Command {
            public function __construct()
            {
                parent::__construct('failure', 'Returns 42');
            }

            public function execute(array $args): int
            {
                return 42;
            }
        });

        $this->assertSame(0, $this->console->run(['app', 'success']));
        $this->assertSame(42, $this->console->run(['app', 'failure']));
    }

    public function testCommandWritelnOutputsTextWithNewline(): void
    {
        $command = new class extends Command {
            public function __construct()
            {
                parent::__construct('test:write', 'Write test');
            }

            public function execute(array $args): int
            {
                $this->writeln('Hello World');
                return 0;
            }
        };

        $this->console->register($command);

        ob_start();
        $command->execute([]);
        $output = ob_get_clean();

        $this->assertSame("Hello World\n", $output);
    }

    public function testCommandTableOutputsFormattedTable(): void
    {
        $command = new class extends Command {
            public function __construct()
            {
                parent::__construct('test:table', 'Table test');
            }

            public function execute(array $args): int
            {
                $this->table(
                    ['Name', 'Value'],
                    [
                        ['foo', 'bar'],
                        ['hello', 'world'],
                    ],
                );
                return 0;
            }
        };

        $this->console->register($command);

        ob_start();
        $command->execute([]);
        $output = ob_get_clean();

        $this->assertStringContainsString('| Name  | Value |', $output);
        $this->assertStringContainsString('| foo   | bar   |', $output);
        $this->assertStringContainsString('| hello | world |', $output);
        $this->assertStringContainsString('+-------+-------+', $output);
    }
}
