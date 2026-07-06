<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\SafeRedirect;
use PHPUnit\Framework\TestCase;

final class SafeRedirectTest extends TestCase
{
    public function testAcceptsLocalAbsolutePaths(): void
    {
        $this->assertTrue(SafeRedirect::isSafePath('/'));
        $this->assertTrue(SafeRedirect::isSafePath('/dashboard'));
        $this->assertTrue(SafeRedirect::isSafePath('/reports/2026?page=2'));
    }

    public function testRejectsOffSiteAndMalformedTargets(): void
    {
        // Browsers normalize "/\host" and "//host" to protocol-relative URLs.
        $this->assertFalse(SafeRedirect::isSafePath('/\\evil.com'));
        $this->assertFalse(SafeRedirect::isSafePath('//evil.com'));
        $this->assertFalse(SafeRedirect::isSafePath('https://evil.com'));
        $this->assertFalse(SafeRedirect::isSafePath('evil.com'));
        $this->assertFalse(SafeRedirect::isSafePath('\\evil.com'));
        $this->assertFalse(SafeRedirect::isSafePath(''));
    }
}
