<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\RefreshToken;
use PHPUnit\Framework\TestCase;

final class RefreshTokenTest extends TestCase
{
    public function testConstructionWithAllProperties(): void
    {
        $now = new \DateTimeImmutable();
        $expiresAt = $now->modify('+7 days');

        $token = new RefreshToken(
            id: 1,
            userId: 42,
            tokenHash: 'abc123hash',
            familyId: '550e8400-e29b-41d4-a716-446655440000',
            generation: 1,
            expiresAt: $expiresAt,
            revokedAt: null,
            createdAt: $now,
        );

        $this->assertSame(1, $token->id);
        $this->assertSame(42, $token->userId);
        $this->assertSame('abc123hash', $token->tokenHash);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $token->familyId);
        $this->assertSame(1, $token->generation);
        $this->assertSame($expiresAt, $token->expiresAt);
        $this->assertNull($token->revokedAt);
        $this->assertSame($now, $token->createdAt);
    }

    public function testIsExpiredReturnsFalseForFutureDate(): void
    {
        $token = $this->createToken(expiresAt: new \DateTimeImmutable('+1 hour'));

        $this->assertFalse($token->isExpired());
    }

    public function testIsExpiredReturnsTrueForPastDate(): void
    {
        $token = $this->createToken(expiresAt: new \DateTimeImmutable('-1 hour'));

        $this->assertTrue($token->isExpired());
    }

    public function testIsRevokedReturnsFalseWhenNull(): void
    {
        $token = $this->createToken(revokedAt: null);

        $this->assertFalse($token->isRevoked());
    }

    public function testIsRevokedReturnsTrueWhenSet(): void
    {
        $token = $this->createToken(revokedAt: new \DateTimeImmutable());

        $this->assertTrue($token->isRevoked());
    }

    private function createToken(
        ?\DateTimeImmutable $expiresAt = null,
        ?\DateTimeImmutable $revokedAt = null,
    ): RefreshToken {
        $now = new \DateTimeImmutable();

        return new RefreshToken(
            id: 1,
            userId: 42,
            tokenHash: 'hash',
            familyId: 'family-id',
            generation: 1,
            expiresAt: $expiresAt ?? $now->modify('+7 days'),
            revokedAt: $revokedAt,
            createdAt: $now,
        );
    }
}
