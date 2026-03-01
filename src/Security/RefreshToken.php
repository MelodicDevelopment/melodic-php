<?php

declare(strict_types=1);

namespace Melodic\Security;

class RefreshToken
{
    public function __construct(
        public readonly int $id,
        public readonly int $userId,
        public readonly string $tokenHash,
        public readonly string $familyId,
        public readonly int $generation,
        public readonly \DateTimeImmutable $expiresAt,
        public readonly ?\DateTimeImmutable $revokedAt,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }

    public function isExpired(): bool
    {
        return $this->expiresAt <= new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
