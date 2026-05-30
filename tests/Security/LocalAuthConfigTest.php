<?php

declare(strict_types=1);

namespace Tests\Security;

use InvalidArgumentException;
use Melodic\Security\LocalAuthConfig;
use PHPUnit\Framework\TestCase;

class LocalAuthConfigTest extends TestCase
{
    private const STRONG_KEY = 'a-sufficiently-long-signing-secret-32+';

    public function testRejectsEmptyHmacSigningKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LocalAuthConfig(signingKey: '');
    }

    public function testRejectsShortHmacSigningKey(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // 31 chars — one below the 32-char minimum.
        new LocalAuthConfig(signingKey: str_repeat('a', 31));
    }

    public function testAcceptsStrongHmacSigningKey(): void
    {
        $config = new LocalAuthConfig(signingKey: self::STRONG_KEY);

        $this->assertSame(self::STRONG_KEY, $config->signingKey);
        $this->assertSame('HS256', $config->algorithm);
    }

    public function testFromArrayWithEmptyKeyThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        LocalAuthConfig::fromArray([]);
    }

    public function testFromArrayWithStrongKeySucceeds(): void
    {
        $config = LocalAuthConfig::fromArray([
            'signingKey' => self::STRONG_KEY,
            'issuer' => 'my-app',
        ]);

        $this->assertSame('my-app', $config->issuer);
    }
}
