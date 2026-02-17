<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Min
{
    public readonly string $message;

    public function __construct(
        public readonly int|float $min,
        ?string $message = null
    ) {
        $this->message = $message ?? "Must be at least {$this->min}";
    }

    public function validate(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        return $value >= $this->min;
    }
}
