<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Error\ExceptionHandler;
use Melodic\Http\Request;
use Melodic\Http\Response;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExceptionHandler $exceptionHandler,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->exceptionHandler->handle($e, $request);
        }
    }
}
