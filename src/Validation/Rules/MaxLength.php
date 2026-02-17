<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MaxLength
{
    public readonly string $message;

    public function __construct(
        public readonly int $max,
        ?string $message = null
    ) {
        $this->message = $message ?? "Must be no more than {$this->max} characters";
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return mb_strlen($value) <= $this->max;
    }
}
