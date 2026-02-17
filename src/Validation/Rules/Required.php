<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Required
{
    public function __construct(
        public readonly string $message = 'This field is required'
    ) {}

    public function validate(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        return true;
    }
}
