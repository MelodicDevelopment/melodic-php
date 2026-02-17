<?php

declare(strict_types=1);

namespace Melodic\Validation;

class ValidationResult
{
    /**
     * @param bool $isValid
     * @param array<string, string[]> $errors
     */
    public function __construct(
        public readonly bool $isValid,
        public readonly array $errors = []
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, string[]> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}
