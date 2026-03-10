<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $user = new User('123', 'alice', 'alice@example.com', ['admin', 'editor']);

        $this->assertSame('123', $user->id);
        $this->assertSame('alice', $user->username);
        $this->assertSame('alice@example.com', $user->email);
        $this->assertSame(['admin', 'editor'], $user->entitlements);
    }

    public function testConstructorDefaultsEntitlementsToEmpty(): void
    {
        $user = new User('1', 'bob', 'bob@example.com');

        $this->assertSame([], $user->entitlements);
    }

    public function testHasEntitlementReturnsTrue(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['admin', 'editor']);

        $this->assertTrue($user->hasEntitlement('admin'));
        $this->assertTrue($user->hasEntitlement('editor'));
    }

    public function testHasEntitlementReturnsFalse(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['editor']);

        $this->assertFalse($user->hasEntitlement('admin'));
    }

    public function testHasEntitlementIsStrict(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['admin']);

        $this->assertFalse($user->hasEntitlement('Admin'));
        $this->assertFalse($user->hasEntitlement('ADMIN'));
    }

    public function testHasAnyEntitlementReturnsTrueWhenOneMatches(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['editor']);

        $this->assertTrue($user->hasAnyEntitlement('admin', 'editor'));
    }

    public function testHasAnyEntitlementReturnsFalseWhenNoneMatch(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['viewer']);

        $this->assertFalse($user->hasAnyEntitlement('admin', 'editor'));
    }

    public function testHasAnyEntitlementWithNoArguments(): void
    {
        $user = new User('1', 'alice', 'alice@example.com', ['admin']);

        $this->assertFalse($user->hasAnyEntitlement());
    }
}
