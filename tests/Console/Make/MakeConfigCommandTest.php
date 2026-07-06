<?php

declare(strict_types=1);

namespace Tests\Console\Make;

use Melodic\Console\Console;
use Melodic\Console\Make\MakeConfigCommand;
use PHPUnit\Framework\TestCase;

class MakeConfigCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/melodic_config_cmd_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDir($this->tempDir);
    }

    public function testMakeConfigCreatesFile(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config', 'staging']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tempDir . '/config/config.staging.json');
    }

    public function testMakeConfigCreatesValidJson(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:config', 'qa']);
        ob_get_clean();

        $content = file_get_contents($this->tempDir . '/config/config.qa.json');
        $data = json_decode($content, true);

        $this->assertNotNull($data);
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('database', $data);
        $this->assertArrayHasKey('jwt', $data);
    }

    public function testMakeConfigRefusesToOverwrite(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        file_put_contents($this->tempDir . '/config/config.staging.json', '{"existing": true}');

        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config', 'staging']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);

        // Original file should be preserved
        $content = file_get_contents($this->tempDir . '/config/config.staging.json');
        $this->assertStringContainsString('existing', $content);
    }

    public function testMakeConfigNoNameShowsUsage(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
    }

    public function testMakeConfigCreatesConfigDirIfMissing(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config', 'pd']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertDirectoryExists($this->tempDir . '/config');
        $this->assertFileExists($this->tempDir . '/config/config.pd.json');
    }

    public function testMakeConfigLowercasesEnvironmentName(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config', 'QA']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->tempDir . '/config/config.qa.json');
    }

    public function testMakeConfigRejectsPathTraversalName(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:config', '../../foo']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
        $this->assertFileDoesNotExist($this->tempDir . '/config.foo.json');
        $this->assertFileDoesNotExist(dirname($this->tempDir) . '/config.foo.json');
    }

    public function testMakeConfigRejectsSpecialCharacterNames(): void
    {
        mkdir($this->tempDir . '/config', 0755, true);
        $console = $this->createConsole();

        foreach (['q a', 'qa.json', 'qa/1', 'qa;rm'] as $bad) {
            ob_start();
            $exitCode = $console->run(['melodic', 'make:config', $bad]);
            ob_get_clean();

            $this->assertSame(1, $exitCode, "Expected '{$bad}' to be rejected");
        }
    }

    private function createConsole(): Console
    {
        $console = new Console();
        $console->register(new MakeConfigCommand());
        return $console;
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
