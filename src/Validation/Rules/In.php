<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class In
{
    public readonly string $message;

    public function __construct(
        public readonly array $values,
        ?string $message = null
    ) {
        $list = implode(', ', $this->values);
        $this->message = $message ?? "Must be one of: {$list}";
    }

    public function validate(mixed $value): bool
    {
        return in_array($value, $this->values, true);
    }
}
