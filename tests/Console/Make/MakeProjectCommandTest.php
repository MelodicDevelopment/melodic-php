<?php

declare(strict_types=1);

namespace Tests\Console\Make;

use Melodic\Console\Console;
use Melodic\Console\Make\MakeProjectCommand;
use PHPUnit\Framework\TestCase;

class MakeProjectCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/melodic_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDir($this->tempDir);
    }

    public function testMakeProjectDefaultCreatesFullStructure(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:project', 'test-app']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        $projectDir = $this->tempDir . '/test-app';
        $this->assertDirectoryExists($projectDir);
        $this->assertDirectoryExists($projectDir . '/config');
        $this->assertDirectoryExists($projectDir . '/public');
        $this->assertDirectoryExists($projectDir . '/bin');
        $this->assertDirectoryExists($projectDir . '/src/Controllers');
        $this->assertDirectoryExists($projectDir . '/src/Services');
        $this->assertDirectoryExists($projectDir . '/src/DTO');
        $this->assertDirectoryExists($projectDir . '/src/Data');
        $this->assertDirectoryExists($projectDir . '/src/Middleware');
        $this->assertDirectoryExists($projectDir . '/src/Providers');
        $this->assertDirectoryExists($projectDir . '/storage/cache');
        $this->assertDirectoryExists($projectDir . '/storage/logs');
        $this->assertDirectoryExists($projectDir . '/tests');

        // Default (full) includes views and HomeController
        $this->assertDirectoryExists($projectDir . '/views/layouts');
        $this->assertDirectoryExists($projectDir . '/views/home');
        $this->assertFileExists($projectDir . '/views/layouts/main.phtml');
        $this->assertFileExists($projectDir . '/views/home/index.phtml');
        $this->assertFileExists($projectDir . '/src/Controllers/HomeController.php');
    }

    public function testMakeProjectDefaultIndexHasMvcAndApiRoutes(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'full-app']);
        ob_get_clean();

        $indexPhp = file_get_contents($this->tempDir . '/full-app/public/index.php');
        $this->assertStringContainsString('HomeController', $indexPhp);
        $this->assertStringContainsString("'/'", $indexPhp);
        $this->assertStringContainsString('API routes', $indexPhp);
    }

    public function testMakeProjectApiTypeHasNoViews(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:project', 'api-only', '--type=api']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        $projectDir = $this->tempDir . '/api-only';
        $this->assertDirectoryDoesNotExist($projectDir . '/views');
        $this->assertFileDoesNotExist($projectDir . '/src/Controllers/HomeController.php');

        $indexPhp = file_get_contents($projectDir . '/public/index.php');
        $this->assertStringNotContainsString('HomeController', $indexPhp);
    }

    public function testMakeProjectMvcTypeCreatesViewsAndHomeController(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:project', 'my-site', '--type=mvc']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        $projectDir = $this->tempDir . '/my-site';
        $this->assertDirectoryExists($projectDir . '/views/layouts');
        $this->assertDirectoryExists($projectDir . '/views/home');
        $this->assertFileExists($projectDir . '/views/layouts/main.phtml');
        $this->assertFileExists($projectDir . '/views/home/index.phtml');
        $this->assertFileExists($projectDir . '/src/Controllers/HomeController.php');

        // HomeController should not have a .gitkeep (it now has a real file)
        $this->assertFileDoesNotExist($projectDir . '/src/Controllers/.gitkeep');
    }

    public function testMakeProjectCreatesRequiredFiles(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'my-app']);
        ob_get_clean();

        $projectDir = $this->tempDir . '/my-app';
        $this->assertFileExists($projectDir . '/composer.json');
        $this->assertFileExists($projectDir . '/config/config.json');
        $this->assertFileExists($projectDir . '/.gitignore');
        $this->assertFileExists($projectDir . '/public/index.php');
        $this->assertFileExists($projectDir . '/public/.htaccess');
        $this->assertFileExists($projectDir . '/bin/console');
        $this->assertFileExists($projectDir . '/src/Providers/AppServiceProvider.php');
    }

    public function testMakeProjectComposerJsonHasCorrectNamespace(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'my-app']);
        ob_get_clean();

        $composerJson = json_decode(
            file_get_contents($this->tempDir . '/my-app/composer.json'),
            true,
        );

        $this->assertSame('app/my-app', $composerJson['name']);
        $this->assertArrayHasKey('MyApp\\', $composerJson['autoload']['psr-4']);
        $this->assertSame('src/', $composerJson['autoload']['psr-4']['MyApp\\']);
    }

    public function testMakeProjectRefusesToOverwriteExistingDirectory(): void
    {
        mkdir($this->tempDir . '/existing-project', 0755, true);
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:project', 'existing-project']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
    }

    public function testMakeProjectNoNameShowsUsage(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:project']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
    }

    public function testMakeProjectGitkeepInEmptyDirs(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'gitkeep-test', '--type=api']);
        ob_get_clean();

        $projectDir = $this->tempDir . '/gitkeep-test';
        $this->assertFileExists($projectDir . '/src/Controllers/.gitkeep');
        $this->assertFileExists($projectDir . '/src/Services/.gitkeep');
        $this->assertFileExists($projectDir . '/src/DTO/.gitkeep');
        $this->assertFileExists($projectDir . '/storage/cache/.gitkeep');
        $this->assertFileExists($projectDir . '/storage/logs/.gitkeep');
    }

    public function testMakeProjectConsoleIsExecutable(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'exec-test']);
        ob_get_clean();

        $this->assertTrue(is_executable($this->tempDir . '/exec-test/bin/console'));
    }

    public function testMakeProjectServiceProviderHasCorrectNamespace(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:project', 'ns-test']);
        ob_get_clean();

        $content = file_get_contents($this->tempDir . '/ns-test/src/Providers/AppServiceProvider.php');
        $this->assertStringContainsString('namespace NsTest\\Providers;', $content);
    }

    private function createConsole(): Console
    {
        $console = new Console();
        $console->register(new MakeProjectCommand());
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
