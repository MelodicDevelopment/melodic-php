<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Http\HttpMethod;
use PHPUnit\Framework\TestCase;

final class HttpMethodTest extends TestCase
{
    public function testParseHeadReturnsHeadCase(): void
    {
        $this->assertSame(HttpMethod::HEAD, HttpMethod::parse('HEAD'));
    }

    public function testParseHeadIsCaseInsensitive(): void
    {
        $this->assertSame(HttpMethod::HEAD, HttpMethod::parse('head'));
    }

    public function testParseThrowsForUnknownMethod(): void
    {
        $this->expectException(\ValueError::class);
        HttpMethod::parse('CONNECT');
    }
}
