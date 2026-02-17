<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Error\ExceptionHandler;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\LoggerInterface;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    private readonly ExceptionHandler $exceptionHandler;

    public function __construct(
        LoggerInterface $logger,
        bool $debug = false,
    ) {
        $this->exceptionHandler = new ExceptionHandler($logger);
        $this->exceptionHandler->setDebug($debug);
    }

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->exceptionHandler->handle($e, $request);
        }
    }
}
