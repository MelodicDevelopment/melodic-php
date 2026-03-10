<?php

declare(strict_types=1);

namespace Tests\Http;

use Melodic\Http\RedirectResponse;
use PHPUnit\Framework\TestCase;

final class RedirectResponseTest extends TestCase
{
    public function testDefaultsTo302Redirect(): void
    {
        $response = new RedirectResponse('/dashboard');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/dashboard', $response->getHeaders()['Location']);
        $this->assertSame('', $response->getBody());
    }

    public function testCustomStatusCode(): void
    {
        $response = new RedirectResponse('/login', 301);

        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location']);
    }

    public function testAbsoluteUrl(): void
    {
        $response = new RedirectResponse('https://example.com/callback');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('https://example.com/callback', $response->getHeaders()['Location']);
    }

    public function testTemporaryRedirect307(): void
    {
        $response = new RedirectResponse('/new-location', 307);

        $this->assertSame(307, $response->getStatusCode());
        $this->assertSame('/new-location', $response->getHeaders()['Location']);
    }

    public function testBodyIsAlwaysEmpty(): void
    {
        $response = new RedirectResponse('/any-url', 303);

        $this->assertSame('', $response->getBody());
    }
}
