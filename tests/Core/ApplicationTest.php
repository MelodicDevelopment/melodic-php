<?php

declare(strict_types=1);

namespace Tests\Core;

use Melodic\Core\Application;
use PHPUnit\Framework\TestCase;

class ApplicationTest extends TestCase
{
    private string $tempDir;
    private string|false $originalAppEnv;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/melodic_app_test_' . uniqid();
        mkdir($this->tempDir . '/config', 0755, true);

        $this->originalAppEnv = getenv('APP_ENV');
        putenv('APP_ENV');
    }

    protected function tearDown(): void
    {
        // Restore APP_ENV
        if ($this->originalAppEnv !== false) {
            putenv('APP_ENV=' . $this->originalAppEnv);
        } else {
            putenv('APP_ENV');
        }

        $this->removeDir($this->tempDir);
    }

    public function testLoadEnvironmentConfigLoadsBaseConfig(): void
    {
        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:test.db'],
        ]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('sqlite:test.db', $app->config('database.dsn'));
    }

    public function testLoadEnvironmentConfigDefaultsToDev(): void
    {
        $this->writeConfig('config.json', ['app' => ['name' => 'Test']]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('dev', $app->getEnvironment());
        $this->assertSame('dev', $app->config('app.environment'));
    }

    public function testLoadEnvironmentConfigLoadsEnvSpecificFile(): void
    {
        putenv('APP_ENV=qa');

        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:base.db'],
            'app' => ['debug' => true],
        ]);
        $this->writeConfig('config.qa.json', [
            'database' => ['dsn' => 'mysql:host=qa-server'],
        ]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('qa', $app->getEnvironment());
        $this->assertSame('mysql:host=qa-server', $app->config('database.dsn'));
        $this->assertTrue($app->config('app.debug'));
    }

    public function testLoadEnvironmentConfigSkipsEnvFileForDev(): void
    {
        putenv('APP_ENV=dev');

        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:base.db'],
        ]);

        // Even if config.dev.json exists as an env file, it should be loaded as the local override
        $this->writeConfig('config.dev.json', [
            'database' => ['dsn' => 'sqlite:local.db'],
        ]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('dev', $app->getEnvironment());
        $this->assertSame('sqlite:local.db', $app->config('database.dsn'));
    }

    public function testLoadEnvironmentConfigDevOverridesLoadLast(): void
    {
        putenv('APP_ENV=qa');

        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:base.db'],
        ]);
        $this->writeConfig('config.qa.json', [
            'database' => ['dsn' => 'mysql:host=qa-server'],
        ]);
        $this->writeConfig('config.dev.json', [
            'database' => ['dsn' => 'sqlite:local-override.db'],
        ]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        // config.dev.json should override the QA config
        $this->assertSame('sqlite:local-override.db', $app->config('database.dsn'));
        $this->assertSame('qa', $app->getEnvironment());
    }

    public function testLoadEnvironmentConfigMissingEnvFileIsIgnored(): void
    {
        putenv('APP_ENV=staging');

        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:base.db'],
        ]);
        // No config.staging.json exists

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('staging', $app->getEnvironment());
        $this->assertSame('sqlite:base.db', $app->config('database.dsn'));
    }

    public function testLoadEnvironmentConfigMissingDevFileIsIgnored(): void
    {
        $this->writeConfig('config.json', [
            'database' => ['dsn' => 'sqlite:base.db'],
        ]);
        // No config.dev.json exists

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('sqlite:base.db', $app->config('database.dsn'));
    }

    public function testLoadEnvironmentConfigSetsEnvironmentInConfig(): void
    {
        putenv('APP_ENV=pd');

        $this->writeConfig('config.json', ['app' => ['name' => 'Test']]);

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig();

        $this->assertSame('pd', $app->config('app.environment'));
        $this->assertSame('Test', $app->config('app.name'));
    }

    public function testLoadEnvironmentConfigReturnsSelfForChaining(): void
    {
        $this->writeConfig('config.json', []);

        $app = new Application($this->tempDir);
        $result = $app->loadEnvironmentConfig();

        $this->assertSame($app, $result);
    }

    public function testGetEnvironmentDefaultsToDev(): void
    {
        $app = new Application($this->tempDir);

        $this->assertSame('dev', $app->getEnvironment());
    }

    public function testLoadEnvironmentConfigCustomConfigDir(): void
    {
        mkdir($this->tempDir . '/settings', 0755, true);
        $this->writeConfig('../settings/config.json', [
            'app' => ['name' => 'Custom'],
        ], $this->tempDir . '/settings/config.json');

        $app = new Application($this->tempDir);
        $app->loadEnvironmentConfig('settings');

        $this->assertSame('Custom', $app->config('app.name'));
    }

    private function writeConfig(string $filename, array $data, ?string $path = null): void
    {
        $path = $path ?? $this->tempDir . '/config/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
