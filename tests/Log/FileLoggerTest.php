<?php

declare(strict_types=1);

namespace Tests\Log;

use Melodic\Log\FileLogger;
use Melodic\Log\LogLevel;
use PHPUnit\Framework\TestCase;

final class FileLoggerTest extends TestCase
{
    private string $logDirectory;

    protected function setUp(): void
    {
        $this->logDirectory = sys_get_temp_dir() . '/melodic-test-logs-' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->logDirectory)) {
            $files = glob($this->logDirectory . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->logDirectory);
        }
    }

    private function getLogContent(): string
    {
        $filename = 'melodic-' . date('Y-m-d') . '.log';
        $path = $this->logDirectory . '/' . $filename;

        return file_exists($path) ? file_get_contents($path) : '';
    }

    public function testWritesToFile(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->info('Test message');

        $content = $this->getLogContent();
        $this->assertStringContainsString('INFO: Test message', $content);
    }

    public function testCreatesDirectoryIfNeeded(): void
    {
        $this->assertDirectoryDoesNotExist($this->logDirectory);

        $logger = new FileLogger($this->logDirectory);
        $logger->info('Test');

        $this->assertDirectoryExists($this->logDirectory);
    }

    public function testLogEntryFormat(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->error('Something went wrong');

        $content = $this->getLogContent();
        $this->assertMatchesRegularExpression(
            '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\] ERROR: Something went wrong$/',
            trim($content),
        );
    }

    public function testMessageInterpolation(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->info('User {name} logged in from {ip}', ['name' => 'alice', 'ip' => '127.0.0.1']);

        $content = $this->getLogContent();
        $this->assertStringContainsString('INFO: User alice logged in from 127.0.0.1', $content);
    }

    public function testExceptionFormatting(): void
    {
        $logger = new FileLogger($this->logDirectory);
        $exception = new \RuntimeException('Something broke');

        $logger->error('An error occurred', ['exception' => $exception]);

        $content = $this->getLogContent();
        $this->assertStringContainsString('ERROR: An error occurred', $content);
        $this->assertStringContainsString('Exception: RuntimeException', $content);
        $this->assertStringContainsString('Message: Something broke', $content);
        $this->assertStringContainsString('Trace:', $content);
    }

    public function testRespectsMinimumLogLevel(): void
    {
        $logger = new FileLogger($this->logDirectory, LogLevel::WARNING);

        $logger->error('Should appear');
        $logger->warning('Should also appear');
        $logger->info('Should not appear');
        $logger->debug('Should not appear');

        $content = $this->getLogContent();
        $this->assertStringContainsString('ERROR: Should appear', $content);
        $this->assertStringContainsString('WARNING: Should also appear', $content);
        $this->assertStringNotContainsString('INFO: Should not appear', $content);
        $this->assertStringNotContainsString('DEBUG: Should not appear', $content);
    }

    public function testMinLevelEmergencyOnlyLogsEmergency(): void
    {
        $logger = new FileLogger($this->logDirectory, LogLevel::EMERGENCY);

        $logger->emergency('Critical failure');
        $logger->alert('Alert message');

        $content = $this->getLogContent();
        $this->assertStringContainsString('EMERGENCY: Critical failure', $content);
        $this->assertStringNotContainsString('ALERT: Alert message', $content);
    }

    public function testAllConvenienceMethods(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->emergency('e');
        $logger->alert('a');
        $logger->critical('c');
        $logger->error('er');
        $logger->warning('w');
        $logger->notice('n');
        $logger->info('i');
        $logger->debug('d');

        $content = $this->getLogContent();
        $this->assertStringContainsString('EMERGENCY: e', $content);
        $this->assertStringContainsString('ALERT: a', $content);
        $this->assertStringContainsString('CRITICAL: c', $content);
        $this->assertStringContainsString('ERROR: er', $content);
        $this->assertStringContainsString('WARNING: w', $content);
        $this->assertStringContainsString('NOTICE: n', $content);
        $this->assertStringContainsString('INFO: i', $content);
        $this->assertStringContainsString('DEBUG: d', $content);
    }

    public function testLogMethodDirectly(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->log(LogLevel::NOTICE, 'Direct log call');

        $content = $this->getLogContent();
        $this->assertStringContainsString('NOTICE: Direct log call', $content);
    }

    public function testAppendsMultipleEntries(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->info('First');
        $logger->info('Second');

        $content = $this->getLogContent();
        $this->assertStringContainsString('INFO: First', $content);
        $this->assertStringContainsString('INFO: Second', $content);
    }

    public function testWritesToDateBasedFile(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->info('Test');

        $expectedFile = $this->logDirectory . '/melodic-' . date('Y-m-d') . '.log';
        $this->assertFileExists($expectedFile);
    }

    public function testLogDirectoryAndFileAreGroupReadableOnly(): void
    {
        $nested = $this->logDirectory . '/fresh';
        $logger = new FileLogger($nested);
        $logger->info('hello');

        clearstatcache();
        $this->assertSame(0750, fileperms($nested) & 0777);

        $files = glob($nested . '/*.log');
        $this->assertNotEmpty($files);
        $this->assertSame(0640, fileperms($files[0]) & 0777);

        foreach ($files as $file) {
            unlink($file);
        }
        rmdir($nested);
    }

    public function testNewlinesInMessageItselfAreSanitized(): void
    {
        $logger = new FileLogger($this->logDirectory);
        $logger->info("line-one\nFAKE: forged entry");

        $content = $this->getLogContent();

        $this->assertStringContainsString('line-one FAKE: forged entry', $content);
    }

    public function testInterpolatedContextCannotInjectNewLogLines(): void
    {
        $logger = new FileLogger($this->logDirectory);

        $logger->info('User {name} signed in', [
            'name' => "evil\n[2099-01-01 00:00:00] EMERGENCY: forged entry",
        ]);

        $content = $this->getLogContent();

        // The CR/LF is collapsed to a space, so no forged line is created.
        $this->assertStringNotContainsString("\n[2099-01-01 00:00:00] EMERGENCY", $content);
        $this->assertStringContainsString('evil [2099-01-01 00:00:00] EMERGENCY: forged entry', $content);
    }
}
