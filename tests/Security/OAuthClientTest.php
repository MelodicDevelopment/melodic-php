<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\OAuthClient;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

class OAuthClientTest extends TestCase
{
    public function testDecodesSuccessfulResponse(): void
    {
        $decoded = OAuthClient::decodeJsonResponse(
            'https://idp/token',
            200,
            '{"access_token":"abc","token_type":"Bearer"}',
        );

        $this->assertSame('abc', $decoded['access_token']);
    }

    public function testRejectsNonJsonBody(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid JSON');

        OAuthClient::decodeJsonResponse('https://idp/token', 200, '<html>error page</html>');
    }

    public function testRejectsErrorStatusAndSurfacesDescription(): void
    {
        try {
            OAuthClient::decodeJsonResponse(
                'https://idp/token',
                400,
                '{"error":"invalid_grant","error_description":"code expired"}',
            );
            $this->fail('Expected SecurityException');
        } catch (SecurityException $e) {
            $this->assertStringContainsString('code expired', $e->getMessage());
        }
    }

    public function testRejectsErrorBodyEvenWith200Status(): void
    {
        // Some non-compliant providers return 200 with an error payload.
        $this->expectException(SecurityException::class);

        OAuthClient::decodeJsonResponse('https://idp/token', 200, '{"error":"server_error"}');
    }

    public function testRejectsServerErrorStatus(): void
    {
        $this->expectException(SecurityException::class);

        OAuthClient::decodeJsonResponse('https://idp/token', 500, '{}');
    }

    public function testRejectsUnparseableStatus(): void
    {
        // 0 = status line could not be parsed → treated as failure.
        $this->expectException(SecurityException::class);

        OAuthClient::decodeJsonResponse('https://idp/token', 0, '{}');
    }
}
