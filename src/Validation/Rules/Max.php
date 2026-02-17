<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Max
{
    public readonly string $message;

    public function __construct(
        public readonly int|float $max,
        ?string $message = null
    ) {
        $this->message = $message ?? "Must be no more than {$this->max}";
    }

    public function validate(mixed $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        return $value <= $this->max;
    }
}
