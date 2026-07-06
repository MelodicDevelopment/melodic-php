<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

/**
 * Comparison is strict (===): the allowed values must match the property's
 * declared type. Model binding coerces wire input to that type before
 * validation runs, so `#[In([1, 2])]` on an int property matches "1" from
 * the client — but on an untyped/mixed property, "1" !== 1 and would fail.
 * Type the property or list values of the matching type.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class In
{
    public readonly string $message;

    /** @param array<int, mixed> $values */
    public function __construct(
        public readonly array $values,
        ?string $message = null
    ) {
        $list = implode(', ', $this->values);
        $this->message = $message ?? "Must be one of: {$list}";
    }

    public function validate(mixed $value): bool
    {
        // Optional by default: only #[Required] rejects an absent/null value.
        if ($value === null) {
            return true;
        }

        return in_array($value, $this->values, true);
    }
}
