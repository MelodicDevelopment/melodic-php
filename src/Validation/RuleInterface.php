<?php

declare(strict_types=1);

namespace Melodic\Validation;

/**
 * Contract for validation rule attributes. Implementations should also expose
 * a public `$message` string; the Validator reports it when validate() returns
 * false. Rules are optional-by-default: return true for null and let
 * #[Required] reject absent values.
 */
interface RuleInterface
{
    public function validate(mixed $value): bool;
}
