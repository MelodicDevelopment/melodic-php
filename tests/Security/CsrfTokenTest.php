<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\CsrfToken;
use Melodic\Security\SessionManager;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class CsrfTokenTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testGetTokenReusesStoredTokenAcrossRenders(): void
    {
        $csrf = new CsrfToken(new SessionManager());

        $first = $csrf->getToken();
        $second = $csrf->getToken();

        // Repeated renders must return the same token (no churn).
        $this->assertSame($first, $second);
        $this->assertNotSame('', $first);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testValidateConsumesTokenAndRejectsReuse(): void
    {
        $csrf = new CsrfToken(new SessionManager());
        $token = $csrf->getToken();

        // Valid once, then single-use: the same token is rejected afterwards.
        $this->assertTrue($csrf->validate($token));
        $this->assertFalse($csrf->validate($token));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMismatchDoesNotConsumeStoredToken(): void
    {
        $csrf = new CsrfToken(new SessionManager());
        $token = $csrf->getToken();

        // A forged/garbage POST must not invalidate the legitimate form's token.
        $this->assertFalse($csrf->validate('forged-token'));
        $this->assertTrue($csrf->validate($token));
    }
}
