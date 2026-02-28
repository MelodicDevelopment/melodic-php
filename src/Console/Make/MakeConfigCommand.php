<?php

declare(strict_types=1);

namespace Melodic\Console\Make;

use Melodic\Console\Command;

class MakeConfigCommand extends Command
{
    public function __construct()
    {
        parent::__construct('make:config', 'Create an environment configuration file');
    }

    public function execute(array $args): int
    {
        $environment = $args[0] ?? null;

        if ($environment === null) {
            $this->error('Usage: make:config <environment>');
            return 1;
        }

        $environment = strtolower($environment);
        $configDir = getcwd() . '/config';
        $filePath = $configDir . '/config.' . $environment . '.json';

        if (file_exists($filePath)) {
            $this->error("Configuration file 'config/{$environment}.json' already exists.");
            return 1;
        }

        if (!is_dir($configDir)) {
            mkdir($configDir, 0755, true);
        }

        $content = Stub::render(self::CONFIG_ENVIRONMENT_STUB, [
            'environment' => $environment,
        ]);

        file_put_contents($filePath, $content);

        $this->writeln("Created config/config.{$environment}.json");

        return 0;
    }

    private const CONFIG_ENVIRONMENT_STUB = <<<'JSON'
{
    "app": {
        "debug": false
    },
    "database": {
        "dsn": ""
    },
    "jwt": {
        "secret": "",
        "algorithm": "HS256"
    }
}
JSON;
}
