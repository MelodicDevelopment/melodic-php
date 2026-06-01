<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\AuthConfig;
use PHPUnit\Framework\TestCase;

class AuthConfigTest extends TestCase
{
    public function testCookieDefaultsAreSecure(): void
    {
        $config = AuthConfig::fromArray([]);

        $this->assertTrue($config->cookieSecure);
        $this->assertSame('Lax', $config->cookieSameSite);
        $this->assertSame('/', $config->cookiePath);
        $this->assertSame('', $config->cookieDomain);
    }

    public function testCookieConfigIsParsed(): void
    {
        $config = AuthConfig::fromArray([
            'cookieSecure' => false,
            'cookieSameSite' => 'Strict',
            'cookiePath' => '/app',
            'cookieDomain' => 'example.com',
        ]);

        $this->assertFalse($config->cookieSecure);
        $this->assertSame('Strict', $config->cookieSameSite);
        $this->assertSame('/app', $config->cookiePath);
        $this->assertSame('example.com', $config->cookieDomain);
    }
}
