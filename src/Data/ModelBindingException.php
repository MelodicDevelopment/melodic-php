<?php

declare(strict_types=1);

namespace Melodic\Data;

/**
 * Thrown when request data cannot be coerced to a model's declared property type
 * (e.g. a non-numeric string for an int field). The routing layer turns this into
 * a 400 response so malformed client input never surfaces as an uncaught TypeError.
 */
class ModelBindingException extends \RuntimeException
{
    public function __construct(
        public readonly string $field,
        string $message,
    ) {
        parent::__construct($message);
    }
}
