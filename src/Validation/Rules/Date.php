<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;
use Melodic\Validation\RuleInterface;

/**
 * A date/time string in an exact format (default "Y-m-d"). Use on string
 * properties; DateTime-typed properties are parsed during model binding.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Date implements RuleInterface
{
    public readonly string $message;

    public function __construct(
        public readonly string $format = 'Y-m-d',
        ?string $message = null
    ) {
        $this->message = $message ?? "Must be a date in the format {$this->format}";
    }

    public function validate(mixed $value): bool
    {
        // Optional by default: only #[Required] rejects an absent/null value.
        if ($value === null) {
            return true;
        }

        // A property typed as DateTime has already been parsed by model binding.
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!' . $this->format, $value);

        // Round-trip so overflowing dates ("2026-02-31") are rejected.
        return $parsed !== false && $parsed->format($this->format) === $value;
    }
}
