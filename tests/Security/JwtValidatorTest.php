<?php

declare(strict_types=1);

namespace Tests\Security;

use Firebase\JWT\JWT;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\JwtValidator;
use Melodic\Security\LocalAuthConfig;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

class JwtValidatorTest extends TestCase
{
    private const KEY = 'a-sufficiently-long-signing-secret-32+';

    private function validator(): JwtValidator
    {
        $config = new LocalAuthConfig(
            signingKey: self::KEY,
            issuer: 'melodic-app',
            audience: 'melodic-app',
        );

        return new JwtValidator(new AuthProviderRegistry(), $config);
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function encode(array $claims): string
    {
        return JWT::encode($claims, self::KEY, 'HS256');
    }

    public function testValidatesWellFormedLocalToken(): void
    {
        $token = $this->encode([
            'iss' => 'melodic-app',
            'aud' => 'melodic-app',
            'exp' => time() + 3600,
            'sub' => 'user-1',
        ]);

        $claims = $this->validator()->validate($token);

        $this->assertSame('user-1', $claims['sub']);
    }

    public function testRejectsLocalTokenMissingExp(): void
    {
        $token = $this->encode([
            'iss' => 'melodic-app',
            'aud' => 'melodic-app',
            'sub' => 'user-1',
        ]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('exp');

        $this->validator()->validate($token);
    }

    public function testRejectsLocalTokenWithWrongAudience(): void
    {
        $token = $this->encode([
            'iss' => 'melodic-app',
            'aud' => 'someone-else',
            'exp' => time() + 3600,
        ]);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('audience');

        $this->validator()->validate($token);
    }
}
