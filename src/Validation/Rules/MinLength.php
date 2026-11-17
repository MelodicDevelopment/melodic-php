<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;
use Melodic\Validation\RuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinLength implements RuleInterface
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
        // Optional by default: only #[Required] rejects an absent/null value.
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        // Count characters when mbstring is available (it is optional).
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);

        return $length >= $this->min;
    }
}
