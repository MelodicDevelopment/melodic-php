<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Pattern
{
    public readonly string $message;

    public function __construct(
        public readonly string $regex,
        ?string $message = null
    ) {
        $this->message = $message ?? "Must match the pattern {$this->regex}";
    }

    public function validate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        return preg_match($this->regex, $value) === 1;
    }
}
