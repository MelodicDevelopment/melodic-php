<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Security\RefreshToken;
use Melodic\Security\RefreshTokenConfig;
use Melodic\Security\RefreshTokenRepositoryInterface;
use Melodic\Security\RefreshTokenService;
use Melodic\Security\SecurityException;
use PHPUnit\Framework\TestCase;

final class RefreshTokenServiceTest extends TestCase
{
    private FakeRefreshTokenRepository $repository;
    private RefreshTokenService $service;

    protected function setUp(): void
    {
        $this->repository = new FakeRefreshTokenRepository();
        $this->service = new RefreshTokenService(
            $this->repository,
            new RefreshTokenConfig(tokenLifetime: 3600),
        );
    }

    public function testCreateGenerates64CharHexToken(): void
    {
        $result = $this->service->create(42);

        $this->assertSame(64, strlen($result['token']));
        $this->assertTrue(ctype_xdigit($result['token']));
    }

    public function testCreateStoresModelWithSha256Hash(): void
    {
        $result = $this->service->create(42);
        $expectedHash = hash('sha256', $result['token']);

        $this->assertSame($expectedHash, $result['model']->tokenHash);
    }

    public function testCreateSetsGeneration1AndNewFamily(): void
    {
        $result = $this->service->create(42);

        $this->assertSame(1, $result['model']->generation);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $result['model']->familyId,
        );
    }

    public function testCreateSetsCorrectExpiry(): void
    {
        $before = new \DateTimeImmutable('+3599 seconds');
        $result = $this->service->create(42);
        $after = new \DateTimeImmutable('+3601 seconds');

        $this->assertGreaterThanOrEqual($before, $result['model']->expiresAt);
        $this->assertLessThanOrEqual($after, $result['model']->expiresAt);
    }

    public function testCreateStoresTokenInRepository(): void
    {
        $result = $this->service->create(42);

        $stored = $this->repository->findByTokenHash($result['model']->tokenHash);
        $this->assertNotNull($stored);
        $this->assertSame(42, $stored->userId);
    }

    public function testValidateReturnsValidToken(): void
    {
        $result = $this->service->create(42);

        $validated = $this->service->validate($result['token']);

        $this->assertSame($result['model']->tokenHash, $validated->tokenHash);
        $this->assertSame(42, $validated->userId);
    }

    public function testValidateThrowsForUnknownToken(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Invalid refresh token.');

        $this->service->validate('nonexistent-token');
    }

    public function testValidateThrowsAndRevokesFamilyForRevokedToken(): void
    {
        $result = $this->service->create(42);
        $this->repository->revokeByFamily($result['model']->familyId);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Refresh token reuse detected.');

        $this->service->validate($result['token']);
    }

    public function testValidateThrowsForExpiredToken(): void
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $token = new RefreshToken(
            id: 1,
            userId: 42,
            tokenHash: $tokenHash,
            familyId: 'family-1',
            generation: 1,
            expiresAt: new \DateTimeImmutable('-1 hour'),
            revokedAt: null,
            createdAt: new \DateTimeImmutable('-2 hours'),
        );

        $this->repository->store($token);

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Refresh token has expired.');

        $this->service->validate($rawToken);
    }

    public function testValidateDetectsStaleGenerationAndRevokesFamily(): void
    {
        $rawTokenOld = bin2hex(random_bytes(32));
        $hashOld = hash('sha256', $rawTokenOld);

        $oldToken = new RefreshToken(
            id: 1,
            userId: 42,
            tokenHash: $hashOld,
            familyId: 'family-1',
            generation: 1,
            expiresAt: new \DateTimeImmutable('+1 hour'),
            revokedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $newerToken = new RefreshToken(
            id: 2,
            userId: 42,
            tokenHash: hash('sha256', 'newer-token'),
            familyId: 'family-1',
            generation: 2,
            expiresAt: new \DateTimeImmutable('+1 hour'),
            revokedAt: null,
            createdAt: new \DateTimeImmutable(),
        );

        $this->repository->store($oldToken);
        $this->repository->store($newerToken);

        try {
            $this->service->validate($rawTokenOld);
            $this->fail('Expected SecurityException was not thrown.');
        } catch (SecurityException $e) {
            $this->assertSame('Refresh token reuse detected.', $e->getMessage());
        }

        // Both tokens in the family should now be revoked
        $found = $this->repository->findByTokenHash($hashOld);
        $this->assertNotNull($found);
        $this->assertTrue($found->isRevoked());
    }

    public function testRotateRevokesCurrentFamilyAndCreatesNewToken(): void
    {
        $result = $this->service->create(42);
        $rotated = $this->service->rotate($result['model']);

        $this->assertSame(64, strlen($rotated['token']));
        $this->assertSame($result['model']->familyId, $rotated['model']->familyId);
        $this->assertSame(2, $rotated['model']->generation);
        $this->assertSame(42, $rotated['model']->userId);
    }

    public function testRotateInvalidatesOldToken(): void
    {
        $result = $this->service->create(42);
        $this->service->rotate($result['model']);

        $this->expectException(SecurityException::class);

        $this->service->validate($result['token']);
    }

    public function testRevokeAllForUserDelegatesToRepository(): void
    {
        $result1 = $this->service->create(42);
        $result2 = $this->service->create(42);

        $this->service->revokeAllForUser(42);

        $token1 = $this->repository->findByTokenHash($result1['model']->tokenHash);
        $token2 = $this->repository->findByTokenHash($result2['model']->tokenHash);

        $this->assertNotNull($token1);
        $this->assertTrue($token1->isRevoked());
        $this->assertNotNull($token2);
        $this->assertTrue($token2->isRevoked());
    }
}

/**
 * In-memory fake for testing.
 */
final class FakeRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    /** @var array<string, RefreshToken> */
    private array $tokens = [];

    public function findByTokenHash(string $hash): ?RefreshToken
    {
        return $this->tokens[$hash] ?? null;
    }

    public function findLatestGenerationByFamily(string $familyId): int
    {
        $max = 0;

        foreach ($this->tokens as $token) {
            if ($token->familyId === $familyId && $token->generation > $max) {
                $max = $token->generation;
            }
        }

        return $max;
    }

    public function store(RefreshToken $token): void
    {
        $this->tokens[$token->tokenHash] = $token;
    }

    public function revokeByFamily(string $familyId): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->tokens as $hash => $token) {
            if ($token->familyId === $familyId && $token->revokedAt === null) {
                $this->tokens[$hash] = new RefreshToken(
                    id: $token->id,
                    userId: $token->userId,
                    tokenHash: $token->tokenHash,
                    familyId: $token->familyId,
                    generation: $token->generation,
                    expiresAt: $token->expiresAt,
                    revokedAt: $now,
                    createdAt: $token->createdAt,
                );
            }
        }
    }

    public function revokeByUserId(int $userId): void
    {
        $now = new \DateTimeImmutable();

        foreach ($this->tokens as $hash => $token) {
            if ($token->userId === $userId && $token->revokedAt === null) {
                $this->tokens[$hash] = new RefreshToken(
                    id: $token->id,
                    userId: $token->userId,
                    tokenHash: $token->tokenHash,
                    familyId: $token->familyId,
                    generation: $token->generation,
                    expiresAt: $token->expiresAt,
                    revokedAt: $now,
                    createdAt: $token->createdAt,
                );
            }
        }
    }

    public function deleteExpired(): int
    {
        $now = new \DateTimeImmutable();
        $count = 0;

        foreach ($this->tokens as $hash => $token) {
            if ($token->expiresAt <= $now) {
                unset($this->tokens[$hash]);
                $count++;
            }
        }

        return $count;
    }
}
