<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\AuthConfig;
use Melodic\Security\AuthLoginRenderer;
use Melodic\Security\AuthProviderRegistry;
use Melodic\Security\LoginPageConfig;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

class AuthLoginRendererTest extends TestCase
{
    private function renderer(?string $customCss): AuthLoginRenderer
    {
        return new AuthLoginRenderer(
            new AuthConfig(loginPage: new LoginPageConfig(customCss: $customCss)),
            new AuthProviderRegistry(),
        );
    }

    public function testCustomCssIsEmittedInStyleBlock(): void
    {
        $html = $this->renderer('.login-card { border: 1px solid red; }')->render();

        $this->assertStringContainsString('<style>.login-card { border: 1px solid red; }</style>', $html);
    }

    public function testCustomCssBreakingOutOfStyleIsRejected(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('customCss');

        $this->renderer('</style><script>alert(1)</script>')->render();
    }
}
