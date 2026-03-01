<?php

declare(strict_types=1);

namespace Melodic\Security;

class RefreshTokenService
{
    public function __construct(
        private readonly RefreshTokenRepositoryInterface $repository,
        private readonly RefreshTokenConfig $config,
    ) {
    }

    /**
     * @return array{token: string, model: RefreshToken}
     */
    public function create(int $userId): array
    {
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $familyId = self::generateUuidV4();
        $now = new \DateTimeImmutable();

        $refreshToken = new RefreshToken(
            id: 0,
            userId: $userId,
            tokenHash: $tokenHash,
            familyId: $familyId,
            generation: 1,
            expiresAt: $now->modify("+{$this->config->tokenLifetime} seconds"),
            revokedAt: null,
            createdAt: $now,
        );

        $this->repository->store($refreshToken);

        return ['token' => $rawToken, 'model' => $refreshToken];
    }

    public function validate(string $rawToken): RefreshToken
    {
        $tokenHash = hash('sha256', $rawToken);
        $refreshToken = $this->repository->findByTokenHash($tokenHash);

        if ($refreshToken === null) {
            throw new SecurityException('Invalid refresh token.');
        }

        if ($refreshToken->isRevoked()) {
            $this->repository->revokeByFamily($refreshToken->familyId);
            throw new SecurityException('Refresh token reuse detected.');
        }

        if ($refreshToken->isExpired()) {
            throw new SecurityException('Refresh token has expired.');
        }

        $latestGeneration = $this->repository->findLatestGenerationByFamily($refreshToken->familyId);

        if ($refreshToken->generation < $latestGeneration) {
            $this->repository->revokeByFamily($refreshToken->familyId);
            throw new SecurityException('Refresh token reuse detected.');
        }

        return $refreshToken;
    }

    /**
     * @return array{token: string, model: RefreshToken}
     */
    public function rotate(RefreshToken $current): array
    {
        $this->repository->revokeByFamily($current->familyId);

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $now = new \DateTimeImmutable();

        $refreshToken = new RefreshToken(
            id: 0,
            userId: $current->userId,
            tokenHash: $tokenHash,
            familyId: $current->familyId,
            generation: $current->generation + 1,
            expiresAt: $now->modify("+{$this->config->tokenLifetime} seconds"),
            revokedAt: null,
            createdAt: $now,
        );

        $this->repository->store($refreshToken);

        return ['token' => $rawToken, 'model' => $refreshToken];
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->repository->revokeByUserId($userId);
    }

    private static function generateUuidV4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
