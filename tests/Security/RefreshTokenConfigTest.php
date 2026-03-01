<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\RefreshTokenConfig;
use PHPUnit\Framework\TestCase;

final class RefreshTokenConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new RefreshTokenConfig();

        $this->assertSame(604800, $config->tokenLifetime);
        $this->assertSame('kingdom_refresh', $config->cookieName);
        $this->assertSame('', $config->cookieDomain);
        $this->assertSame('/auth/refresh', $config->cookiePath);
        $this->assertTrue($config->cookieSecure);
        $this->assertSame('Lax', $config->cookieSameSite);
    }

    public function testFromArrayWithDefaults(): void
    {
        $config = RefreshTokenConfig::fromArray([]);

        $this->assertSame(604800, $config->tokenLifetime);
        $this->assertSame('kingdom_refresh', $config->cookieName);
        $this->assertSame('', $config->cookieDomain);
        $this->assertSame('/auth/refresh', $config->cookiePath);
        $this->assertTrue($config->cookieSecure);
        $this->assertSame('Lax', $config->cookieSameSite);
    }

    public function testFromArrayWithOverrides(): void
    {
        $config = RefreshTokenConfig::fromArray([
            'tokenLifetime' => 86400,
            'cookieName' => 'app_refresh',
            'cookieDomain' => '.example.com',
            'cookiePath' => '/api/refresh',
            'cookieSecure' => false,
            'cookieSameSite' => 'Strict',
        ]);

        $this->assertSame(86400, $config->tokenLifetime);
        $this->assertSame('app_refresh', $config->cookieName);
        $this->assertSame('.example.com', $config->cookieDomain);
        $this->assertSame('/api/refresh', $config->cookiePath);
        $this->assertFalse($config->cookieSecure);
        $this->assertSame('Strict', $config->cookieSameSite);
    }
}
