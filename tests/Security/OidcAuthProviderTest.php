<?php

declare(strict_types=1);

namespace Tests\Security;

use Firebase\JWT\JWT;
use Melodic\Security\AuthProviderConfig;
use Melodic\Security\AuthProviderType;
use Melodic\Security\OidcAuthProvider;
use Melodic\Security\OidcProvider;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

/**
 * OidcProvider stub that returns a fixed JWKS and issuer, so token validation
 * can be exercised without any network/discovery round-trip.
 */
class FakeOidcProvider extends OidcProvider
{
    /**
     * @param array<string, mixed> $jwks
     */
    public function __construct(
        private readonly array $jwks,
        private readonly string $issuer,
    ) {
        parent::__construct('https://unused.test/.well-known/openid-configuration', sys_get_temp_dir() . '/unused');
    }

    public function getJwks(): array
    {
        return $this->jwks;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }
}

class OidcAuthProviderTest extends TestCase
{
    private const ISSUER = 'https://issuer.test';
    private const CLIENT_ID = 'client-xyz';
    private const KID = 'test-key';

    private string $privateKey;
    private string $modulus;
    private string $exponent;
    private OidcAuthProvider $provider;

    protected function setUp(): void
    {
        $resource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($resource === false) {
            $this->markTestSkipped('openssl RSA key generation unavailable.');
        }

        openssl_pkey_export($resource, $privateKey);
        $this->privateKey = $privateKey;
        $details = openssl_pkey_get_details($resource);
        $this->modulus = $this->base64Url($details['rsa']['n']);
        $this->exponent = $this->base64Url($details['rsa']['e']);

        $jwks = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => self::KID,
                'n' => $this->modulus,
                'e' => $this->exponent,
            ]],
        ];

        $config = new AuthProviderConfig(
            name: 'test',
            type: AuthProviderType::Oidc,
            clientId: self::CLIENT_ID,
        );

        $this->provider = new OidcAuthProvider(
            $config,
            sys_get_temp_dir() . '/unused',
            new FakeOidcProvider($jwks, self::ISSUER),
        );
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function token(array $claims): string
    {
        return JWT::encode($claims, $this->privateKey, 'RS256', self::KID);
    }

    public function testValidTokenIsAccepted(): void
    {
        $claims = $this->provider->validateToken($this->token([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'exp' => time() + 3600,
            'sub' => 'user-1',
        ]));

        $this->assertSame('user-1', $claims['sub']);
    }

    public function testValidTokenIsAcceptedWhenJwkOmitsAlg(): void
    {
        // Microsoft Entra's JWKS omits the per-key "alg". parseKeySet rejects such
        // keys unless a default algorithm is supplied — regression for the live
        // "JWK must contain an alg parameter" failure.
        $jwksWithoutAlg = [
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'kid' => self::KID,
                'n' => $this->modulus,
                'e' => $this->exponent,
            ]],
        ];

        $provider = new OidcAuthProvider(
            new AuthProviderConfig(
                name: 'test',
                type: AuthProviderType::Oidc,
                clientId: self::CLIENT_ID,
            ),
            sys_get_temp_dir() . '/unused',
            new FakeOidcProvider($jwksWithoutAlg, self::ISSUER),
        );

        $claims = $provider->validateToken($this->token([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'exp' => time() + 3600,
            'sub' => 'user-1',
        ]));

        $this->assertSame('user-1', $claims['sub']);
    }

    public function testTokenWithWrongIssuerIsRejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('issuer');

        $this->provider->validateToken($this->token([
            'iss' => 'https://evil.test',
            'aud' => self::CLIENT_ID,
            'exp' => time() + 3600,
        ]));
    }

    public function testTokenMissingExpIsRejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('exp');

        $this->provider->validateToken($this->token([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
        ]));
    }

    public function testTokenWithWrongAudienceIsRejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('audience');

        $this->provider->validateToken($this->token([
            'iss' => self::ISSUER,
            'aud' => 'some-other-client',
            'exp' => time() + 3600,
        ]));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $this->expectException(SecurityException::class);

        $this->provider->validateToken($this->token([
            'iss' => self::ISSUER,
            'aud' => self::CLIENT_ID,
            'exp' => time() - 10,
        ]));
    }
}
