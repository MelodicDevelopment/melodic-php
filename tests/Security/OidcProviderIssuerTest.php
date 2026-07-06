<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\OidcProvider;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

/** OidcProvider with a canned discovery document — no network, no cache. */
class CannedDiscoveryOidcProvider extends OidcProvider
{
    /** @param array<string, mixed> $discovery */
    public function __construct(
        string $discoveryUrl,
        private readonly array $discovery,
    ) {
        parent::__construct($discoveryUrl, sys_get_temp_dir() . '/unused');
    }

    public function discover(): array
    {
        return $this->discovery;
    }
}

class OidcProviderIssuerTest extends TestCase
{
    public function testIssuerOnDiscoveryHostIsAccepted(): void
    {
        $provider = new CannedDiscoveryOidcProvider(
            'https://idp.example.com/.well-known/openid-configuration',
            ['issuer' => 'https://idp.example.com/tenant-1'],
        );

        $this->assertSame('https://idp.example.com/tenant-1', $provider->getIssuer());
    }

    public function testIssuerOnForeignHostIsRejected(): void
    {
        $provider = new CannedDiscoveryOidcProvider(
            'https://idp.example.com/.well-known/openid-configuration',
            ['issuer' => 'https://attacker.example.net'],
        );

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('issuer host');

        $provider->getIssuer();
    }

    public function testMissingIssuerIsRejected(): void
    {
        $provider = new CannedDiscoveryOidcProvider(
            'https://idp.example.com/.well-known/openid-configuration',
            [],
        );

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('missing issuer');

        $provider->getIssuer();
    }
}
