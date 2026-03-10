<?php

declare(strict_types=1);

namespace Tests\Log;

use Melodic\Log\LogLevel;
use PHPUnit\Framework\TestCase;

final class LogLevelTest extends TestCase
{
    public function testHasAllEightCases(): void
    {
        $cases = LogLevel::cases();

        $this->assertCount(8, $cases);
    }

    public function testSeverityValues(): void
    {
        $this->assertSame(0, LogLevel::EMERGENCY->severity());
        $this->assertSame(1, LogLevel::ALERT->severity());
        $this->assertSame(2, LogLevel::CRITICAL->severity());
        $this->assertSame(3, LogLevel::ERROR->severity());
        $this->assertSame(4, LogLevel::WARNING->severity());
        $this->assertSame(5, LogLevel::NOTICE->severity());
        $this->assertSame(6, LogLevel::INFO->severity());
        $this->assertSame(7, LogLevel::DEBUG->severity());
    }

    public function testParseValidLevels(): void
    {
        $this->assertSame(LogLevel::EMERGENCY, LogLevel::parse('emergency'));
        $this->assertSame(LogLevel::ALERT, LogLevel::parse('alert'));
        $this->assertSame(LogLevel::CRITICAL, LogLevel::parse('critical'));
        $this->assertSame(LogLevel::ERROR, LogLevel::parse('error'));
        $this->assertSame(LogLevel::WARNING, LogLevel::parse('warning'));
        $this->assertSame(LogLevel::NOTICE, LogLevel::parse('notice'));
        $this->assertSame(LogLevel::INFO, LogLevel::parse('info'));
        $this->assertSame(LogLevel::DEBUG, LogLevel::parse('debug'));
    }

    public function testParseCaseInsensitive(): void
    {
        $this->assertSame(LogLevel::ERROR, LogLevel::parse('ERROR'));
        $this->assertSame(LogLevel::WARNING, LogLevel::parse('Warning'));
    }

    public function testParseInvalidThrowsValueError(): void
    {
        $this->expectException(\ValueError::class);
        $this->expectExceptionMessage('Invalid log level: invalid');

        LogLevel::parse('invalid');
    }
}
