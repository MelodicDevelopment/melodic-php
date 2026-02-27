<?php

declare(strict_types=1);

namespace Tests\Console\Make;

use Melodic\Console\Console;
use Melodic\Console\Make\MakeEntityCommand;
use PHPUnit\Framework\TestCase;

class MakeEntityCommandTest extends TestCase
{
    private string $tempDir;
    private string $originalCwd;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/melodic_entity_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->originalCwd = getcwd();
        chdir($this->tempDir);

        // Create a minimal project structure with composer.json
        $this->createProjectStructure();
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        $this->removeDir($this->tempDir);
    }

    public function testMakeEntityCreatesAllFiles(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $this->assertSame(0, $exitCode);

        $this->assertFileExists($this->tempDir . '/src/DTO/ChurchModel.php');
        $this->assertFileExists($this->tempDir . '/src/Data/Church/Queries/GetAllChurchesQuery.php');
        $this->assertFileExists($this->tempDir . '/src/Data/Church/Queries/GetChurchByIdQuery.php');
        $this->assertFileExists($this->tempDir . '/src/Data/Church/Commands/CreateChurchCommand.php');
        $this->assertFileExists($this->tempDir . '/src/Data/Church/Commands/UpdateChurchCommand.php');
        $this->assertFileExists($this->tempDir . '/src/Data/Church/Commands/DeleteChurchCommand.php');
        $this->assertFileExists($this->tempDir . '/src/Services/ChurchService.php');
        $this->assertFileExists($this->tempDir . '/src/Controllers/ChurchController.php');
    }

    public function testMakeEntityModelHasCorrectNamespace(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $content = file_get_contents($this->tempDir . '/src/DTO/ChurchModel.php');
        $this->assertStringContainsString('namespace MyApp\\DTO;', $content);
        $this->assertStringContainsString('class ChurchModel extends Model', $content);
    }

    public function testMakeEntityQueryHasCorrectSql(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $getAllContent = file_get_contents(
            $this->tempDir . '/src/Data/Church/Queries/GetAllChurchesQuery.php',
        );
        $this->assertStringContainsString('SELECT * FROM churches', $getAllContent);
        $this->assertStringContainsString('namespace MyApp\\Data\\Church\\Queries;', $getAllContent);

        $getByIdContent = file_get_contents(
            $this->tempDir . '/src/Data/Church/Queries/GetChurchByIdQuery.php',
        );
        $this->assertStringContainsString('SELECT * FROM churches WHERE id = :id', $getByIdContent);
    }

    public function testMakeEntityCommandsHaveCorrectSql(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $deleteContent = file_get_contents(
            $this->tempDir . '/src/Data/Church/Commands/DeleteChurchCommand.php',
        );
        $this->assertStringContainsString('DELETE FROM churches WHERE id = :id', $deleteContent);

        $updateContent = file_get_contents(
            $this->tempDir . '/src/Data/Church/Commands/UpdateChurchCommand.php',
        );
        $this->assertStringContainsString('UPDATE churches SET', $updateContent);
    }

    public function testMakeEntityServiceUsesCorrectImports(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $content = file_get_contents($this->tempDir . '/src/Services/ChurchService.php');
        $this->assertStringContainsString('namespace MyApp\\Services;', $content);
        $this->assertStringContainsString('use MyApp\\Data\\Church\\Queries\\GetAllChurchesQuery;', $content);
        $this->assertStringContainsString('use MyApp\\DTO\\ChurchModel;', $content);
        $this->assertStringContainsString('class ChurchService extends Service', $content);
    }

    public function testMakeEntityControllerHasCrudMethods(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $content = file_get_contents($this->tempDir . '/src/Controllers/ChurchController.php');
        $this->assertStringContainsString('class ChurchController extends ApiController', $content);
        $this->assertStringContainsString('function index()', $content);
        $this->assertStringContainsString('function show(', $content);
        $this->assertStringContainsString('function store()', $content);
        $this->assertStringContainsString('function update(', $content);
        $this->assertStringContainsString('function destroy(', $content);
        $this->assertStringContainsString('ChurchService $churchService', $content);
    }

    public function testMakeEntitySkipsExistingFiles(): void
    {
        $console = $this->createConsole();

        // Create one file ahead of time
        mkdir($this->tempDir . '/src/DTO', 0755, true);
        file_put_contents($this->tempDir . '/src/DTO/ChurchModel.php', 'existing content');

        ob_start();
        $exitCode = $console->run(['melodic', 'make:entity', 'Church']);
        $output = ob_get_clean();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('skip', $output);

        // Original file should be preserved
        $this->assertSame('existing content', file_get_contents($this->tempDir . '/src/DTO/ChurchModel.php'));

        // Other files should still be created
        $this->assertFileExists($this->tempDir . '/src/Services/ChurchService.php');
    }

    public function testMakeEntityNoNameShowsUsage(): void
    {
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:entity']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
    }

    public function testMakeEntityNoComposerJsonFails(): void
    {
        unlink($this->tempDir . '/composer.json');
        $console = $this->createConsole();

        ob_start();
        $exitCode = $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $this->assertSame(1, $exitCode);
    }

    public function testMakeEntityPluralizesCorrectly(): void
    {
        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Category']);
        ob_get_clean();

        $this->assertFileExists($this->tempDir . '/src/Data/Category/Queries/GetAllCategoriesQuery.php');
    }

    public function testMakeEntityRemovesGitkeepFiles(): void
    {
        // Create .gitkeep files
        mkdir($this->tempDir . '/src/DTO', 0755, true);
        mkdir($this->tempDir . '/src/Controllers', 0755, true);
        mkdir($this->tempDir . '/src/Services', 0755, true);
        file_put_contents($this->tempDir . '/src/DTO/.gitkeep', '');
        file_put_contents($this->tempDir . '/src/Controllers/.gitkeep', '');
        file_put_contents($this->tempDir . '/src/Services/.gitkeep', '');

        $console = $this->createConsole();

        ob_start();
        $console->run(['melodic', 'make:entity', 'Church']);
        ob_get_clean();

        $this->assertFileDoesNotExist($this->tempDir . '/src/DTO/.gitkeep');
        $this->assertFileDoesNotExist($this->tempDir . '/src/Controllers/.gitkeep');
        $this->assertFileDoesNotExist($this->tempDir . '/src/Services/.gitkeep');
    }

    private function createProjectStructure(): void
    {
        $composerJson = json_encode([
            'name' => 'app/my-app',
            'autoload' => [
                'psr-4' => [
                    'MyApp\\' => 'src/',
                ],
            ],
        ], JSON_PRETTY_PRINT);

        file_put_contents($this->tempDir . '/composer.json', $composerJson);
        mkdir($this->tempDir . '/src', 0755, true);
    }

    private function createConsole(): Console
    {
        $console = new Console();
        $console->register(new MakeEntityCommand());
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
