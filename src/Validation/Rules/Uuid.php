<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;
use Melodic\Validation\RuleInterface;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Uuid implements RuleInterface
{
    public function __construct(
        public readonly string $message = 'Must be a valid UUID'
    ) {}

    public function validate(mixed $value): bool
    {
        // Optional by default: only #[Required] rejects an absent/null value.
        if ($value === null) {
            return true;
        }

        return is_string($value)
            && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }
}
