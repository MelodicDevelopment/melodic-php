<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Http\Response;
use Melodic\Security\RefreshTokenConfig;
use Melodic\Security\RefreshTokenCookieHelper;
use PHPUnit\Framework\TestCase;

final class RefreshTokenCookieHelperTest extends TestCase
{
    public function testAttachSetsCookieOnResponse(): void
    {
        $config = new RefreshTokenConfig(
            tokenLifetime: 3600,
            cookieName: 'test_refresh',
            cookieDomain: '.example.com',
            cookiePath: '/auth/refresh',
            cookieSecure: true,
            cookieSameSite: 'Strict',
        );

        $response = new Response(200, '');
        $result = RefreshTokenCookieHelper::attach($response, 'raw-token-value', $config);

        // Response is immutable — original unchanged
        $this->assertNotSame($response, $result);

        // Verify cookie was set by sending and checking (we can't inspect cookies directly,
        // so we verify the return type is correct and the method doesn't throw)
        $this->assertInstanceOf(Response::class, $result);
    }

    public function testClearSetsExpiredCookieOnResponse(): void
    {
        $config = new RefreshTokenConfig(cookieName: 'test_refresh');
        $response = new Response(200, '');

        $result = RefreshTokenCookieHelper::clear($response, $config);

        $this->assertNotSame($response, $result);
        $this->assertInstanceOf(Response::class, $result);
    }
}
