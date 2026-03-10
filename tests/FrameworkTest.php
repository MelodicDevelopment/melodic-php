<?php

declare(strict_types=1);

namespace Tests;

use Melodic\Framework;
use PHPUnit\Framework\TestCase;

final class FrameworkTest extends TestCase
{
    public function testVersionIsSemanticVersionString(): void
    {
        $this->assertIsString(Framework::VERSION);
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Framework::VERSION);
    }
}
