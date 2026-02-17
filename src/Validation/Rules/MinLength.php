<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinLength
{
    public readonly string $message;

    public function __construct(
        public readonly int $min,
        ?string $message = null
    ) {
        $this->message = $message ?? "Must be at least {$this->min} characters";
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return mb_strlen($value) >= $this->min;
    }
}
