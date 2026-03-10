<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\User;
use Melodic\Security\UserContext;
use PHPUnit\Framework\TestCase;

final class UserContextTest extends TestCase
{
    private function createUser(): User
    {
        return new User('42', 'alice', 'alice@example.com', ['admin', 'editor']);
    }

    public function testIsAuthenticatedWhenUserProvided(): void
    {
        $context = new UserContext($this->createUser());

        $this->assertTrue($context->isAuthenticated());
    }

    public function testIsNotAuthenticatedWhenUserIsNull(): void
    {
        $context = new UserContext();

        $this->assertFalse($context->isAuthenticated());
    }

    public function testGetUser(): void
    {
        $user = $this->createUser();
        $context = new UserContext($user);

        $this->assertSame($user, $context->getUser());
    }

    public function testGetUserReturnsNullWhenUnauthenticated(): void
    {
        $context = new UserContext();

        $this->assertNull($context->getUser());
    }

    public function testGetUsername(): void
    {
        $context = new UserContext($this->createUser());

        $this->assertSame('alice', $context->getUsername());
    }

    public function testGetUsernameReturnsNullWhenUnauthenticated(): void
    {
        $context = new UserContext();

        $this->assertNull($context->getUsername());
    }

    public function testHasEntitlementDelegatesToUser(): void
    {
        $context = new UserContext($this->createUser());

        $this->assertTrue($context->hasEntitlement('admin'));
        $this->assertFalse($context->hasEntitlement('superadmin'));
    }

    public function testHasEntitlementReturnsFalseWhenUnauthenticated(): void
    {
        $context = new UserContext();

        $this->assertFalse($context->hasEntitlement('admin'));
    }

    public function testHasAnyEntitlementDelegatesToUser(): void
    {
        $context = new UserContext($this->createUser());

        $this->assertTrue($context->hasAnyEntitlement('superadmin', 'editor'));
        $this->assertFalse($context->hasAnyEntitlement('superadmin', 'viewer'));
    }

    public function testHasAnyEntitlementReturnsFalseWhenUnauthenticated(): void
    {
        $context = new UserContext();

        $this->assertFalse($context->hasAnyEntitlement('admin'));
    }

    public function testGetProvider(): void
    {
        $context = new UserContext($this->createUser(), 'google');

        $this->assertSame('google', $context->getProvider());
    }

    public function testGetProviderReturnsNullByDefault(): void
    {
        $context = new UserContext();

        $this->assertNull($context->getProvider());
    }

    public function testGetClaim(): void
    {
        $context = new UserContext(null, null, ['iss' => 'my-app', 'aud' => 'api']);

        $this->assertSame('my-app', $context->getClaim('iss'));
        $this->assertSame('api', $context->getClaim('aud'));
    }

    public function testGetClaimReturnsDefaultWhenMissing(): void
    {
        $context = new UserContext();

        $this->assertNull($context->getClaim('missing'));
        $this->assertSame('fallback', $context->getClaim('missing', 'fallback'));
    }

    public function testGetClaims(): void
    {
        $claims = ['sub' => '42', 'iss' => 'my-app'];
        $context = new UserContext(null, null, $claims);

        $this->assertSame($claims, $context->getClaims());
    }

    public function testAnonymousCreatesUnauthenticatedContext(): void
    {
        $context = UserContext::anonymous();

        $this->assertFalse($context->isAuthenticated());
        $this->assertNull($context->getUser());
        $this->assertNull($context->getProvider());
        $this->assertSame([], $context->getClaims());
    }

    public function testFromClaimsWithSubAndUsername(): void
    {
        $claims = [
            'sub' => '99',
            'username' => 'bob',
            'email' => 'bob@example.com',
            'entitlements' => ['viewer'],
            'provider' => 'auth0',
        ];

        $context = UserContext::fromClaims($claims);

        $this->assertTrue($context->isAuthenticated());
        $this->assertSame('99', $context->getUser()->id);
        $this->assertSame('bob', $context->getUser()->username);
        $this->assertSame('bob@example.com', $context->getUser()->email);
        $this->assertSame(['viewer'], $context->getUser()->entitlements);
        $this->assertSame('auth0', $context->getProvider());
        $this->assertSame($claims, $context->getClaims());
    }

    public function testFromClaimsFallsBackToPreferredUsername(): void
    {
        $claims = [
            'sub' => '1',
            'preferred_username' => 'preferred_bob',
            'email' => 'bob@example.com',
        ];

        $context = UserContext::fromClaims($claims);

        $this->assertSame('preferred_bob', $context->getUser()->username);
    }

    public function testFromClaimsFallsBackToName(): void
    {
        $claims = [
            'sub' => '1',
            'name' => 'Bob Smith',
            'email' => 'bob@example.com',
        ];

        $context = UserContext::fromClaims($claims);

        $this->assertSame('Bob Smith', $context->getUser()->username);
    }

    public function testFromClaimsWithMinimalData(): void
    {
        $context = UserContext::fromClaims([]);

        $this->assertTrue($context->isAuthenticated());
        $this->assertSame('', $context->getUser()->id);
        $this->assertSame('', $context->getUser()->username);
        $this->assertSame('', $context->getUser()->email);
        $this->assertSame([], $context->getUser()->entitlements);
        $this->assertNull($context->getProvider());
    }
}
