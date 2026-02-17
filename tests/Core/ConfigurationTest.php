<?php

declare(strict_types=1);

namespace Tests\Core;

use Melodic\Core\Configuration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ConfigurationTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/melodic_config_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    private function createConfigFile(string $filename, array $data): string
    {
        $path = $this->tempDir . '/' . $filename;
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
        return $path;
    }

    public function testConstructorAcceptsInitialData(): void
    {
        $config = new Configuration(['key' => 'value']);

        $this->assertSame('value', $config->get('key'));
    }

    public function testConstructorDefaultsToEmptyData(): void
    {
        $config = new Configuration();

        $this->assertSame([], $config->all());
    }

    public function testLoadFileLoadsJsonConfig(): void
    {
        $path = $this->createConfigFile('config.json', [
            'app' => ['name' => 'TestApp', 'debug' => true],
            'database' => ['host' => 'localhost'],
        ]);

        $config = new Configuration();
        $config->loadFile($path);

        $this->assertSame('TestApp', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertSame('localhost', $config->get('database.host'));
    }

    public function testLoadFileMergesWithExistingData(): void
    {
        $path = $this->createConfigFile('config.json', [
            'new_key' => 'new_value',
        ]);

        $config = new Configuration(['existing' => 'data']);
        $config->loadFile($path);

        $this->assertSame('data', $config->get('existing'));
        $this->assertSame('new_value', $config->get('new_key'));
    }

    public function testLoadFileThrowsForMissingFile(): void
    {
        $config = new Configuration();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found');

        $config->loadFile('/nonexistent/path/config.json');
    }

    public function testLoadFileThrowsForInvalidJson(): void
    {
        $path = $this->tempDir . '/invalid.json';
        file_put_contents($path, '{invalid json content!!!');

        $config = new Configuration();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        $config->loadFile($path);
    }

    public function testGetWithSimpleKey(): void
    {
        $config = new Configuration(['name' => 'Melodic', 'version' => 1]);

        $this->assertSame('Melodic', $config->get('name'));
        $this->assertSame(1, $config->get('version'));
    }

    public function testGetWithDotNotationNestedKeys(): void
    {
        $config = new Configuration([
            'database' => [
                'connections' => [
                    'default' => [
                        'host' => '127.0.0.1',
                        'port' => 3306,
                    ],
                ],
            ],
        ]);

        $this->assertSame('127.0.0.1', $config->get('database.connections.default.host'));
        $this->assertSame(3306, $config->get('database.connections.default.port'));
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $config = new Configuration();

        $this->assertNull($config->get('nonexistent'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $config = new Configuration();

        $this->assertSame('fallback', $config->get('missing', 'fallback'));
        $this->assertSame(42, $config->get('missing.nested', 42));
    }

    public function testGetReturnsDefaultWhenIntermediateKeyIsNotArray(): void
    {
        $config = new Configuration(['key' => 'string_value']);

        $this->assertSame('default', $config->get('key.nested', 'default'));
    }

    public function testGetReturnsEntireNestedArray(): void
    {
        $config = new Configuration([
            'app' => ['name' => 'Test', 'debug' => false],
        ]);

        $result = $config->get('app');

        $this->assertSame(['name' => 'Test', 'debug' => false], $result);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $config = new Configuration(['key' => 'value']);

        $this->assertTrue($config->has('key'));
    }

    public function testHasReturnsTrueForNestedKey(): void
    {
        $config = new Configuration([
            'database' => ['host' => 'localhost'],
        ]);

        $this->assertTrue($config->has('database.host'));
        $this->assertTrue($config->has('database'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $config = new Configuration();

        $this->assertFalse($config->has('nonexistent'));
        $this->assertFalse($config->has('deeply.nested.key'));
    }

    public function testHasReturnsFalseForMissingNestedKey(): void
    {
        $config = new Configuration(['app' => ['name' => 'Test']]);

        $this->assertFalse($config->has('app.missing'));
        $this->assertFalse($config->has('app.name.deeper'));
    }

    public function testHasReturnsTrueForNullValue(): void
    {
        $config = new Configuration(['key' => null]);

        $this->assertTrue($config->has('key'));
    }

    public function testSetSimpleKey(): void
    {
        $config = new Configuration();
        $config->set('key', 'value');

        $this->assertSame('value', $config->get('key'));
    }

    public function testSetDotNotationCreatesNestedStructure(): void
    {
        $config = new Configuration();
        $config->set('database.host', 'localhost');
        $config->set('database.port', 5432);

        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(5432, $config->get('database.port'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $config = new Configuration(['key' => 'old']);
        $config->set('key', 'new');

        $this->assertSame('new', $config->get('key'));
    }

    public function testSetCreatesIntermediateArrays(): void
    {
        $config = new Configuration();
        $config->set('a.b.c', 'deep');

        $this->assertSame('deep', $config->get('a.b.c'));
        $this->assertSame(['c' => 'deep'], $config->get('a.b'));
    }

    public function testSetOverwritesNonArrayIntermediate(): void
    {
        $config = new Configuration(['a' => 'string']);
        $config->set('a.b', 'nested');

        $this->assertSame('nested', $config->get('a.b'));
    }

    public function testAllReturnsEntireDataArray(): void
    {
        $data = ['key1' => 'val1', 'key2' => ['nested' => 'val2']];
        $config = new Configuration($data);

        $this->assertSame($data, $config->all());
    }

    public function testMergeDeepMergesArrays(): void
    {
        $config = new Configuration([
            'app' => ['name' => 'Test', 'debug' => false],
            'keep' => 'this',
        ]);

        $config->merge([
            'app' => ['debug' => true, 'version' => '1.0'],
            'new' => 'value',
        ]);

        $this->assertSame('Test', $config->get('app.name'));
        $this->assertTrue($config->get('app.debug'));
        $this->assertSame('1.0', $config->get('app.version'));
        $this->assertSame('this', $config->get('keep'));
        $this->assertSame('value', $config->get('new'));
    }
}
