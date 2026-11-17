<?php

declare(strict_types=1);

namespace Melodic\Validation\Rules;

use Attribute;
use Melodic\Validation\RuleInterface;

/**
 * An absolute http(s) URL. Other schemes (javascript:, data:, file:, ...) are
 * rejected so a validated value is safe to use as a link target.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class Url implements RuleInterface
{
    public function __construct(
        public readonly string $message = 'Must be a valid URL'
    ) {}

    public function validate(mixed $value): bool
    {
        // Optional by default: only #[Required] rejects an absent/null value.
        if ($value === null) {
            return true;
        }

        if (!is_string($value) || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        // FILTER_VALIDATE_URL accepts any scheme (javascript:, file:, ...).
        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
