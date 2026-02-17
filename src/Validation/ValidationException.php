<?php

declare(strict_types=1);

namespace Melodic\Validation;

class ValidationException extends \RuntimeException
{
    public function __construct(
        public readonly ValidationResult $result,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
