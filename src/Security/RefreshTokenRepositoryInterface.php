<?php

declare(strict_types=1);

namespace Melodic\Security;

interface RefreshTokenRepositoryInterface
{
    public function findByTokenHash(string $hash): ?RefreshToken;

    public function findLatestGenerationByFamily(string $familyId): int;

    public function store(RefreshToken $token): void;

    public function revokeByFamily(string $familyId): void;

    public function revokeByUserId(int $userId): void;

    public function deleteExpired(): int;
}
