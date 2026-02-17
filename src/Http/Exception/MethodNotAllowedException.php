<?php

declare(strict_types=1);

namespace Melodic\Http\Exception;

class MethodNotAllowedException extends HttpException
{
    public function __construct(string $message = 'Method Not Allowed', ?\Throwable $previous = null)
    {
        parent::__construct(405, $message, $previous);
    }
}
