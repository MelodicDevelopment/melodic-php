<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

/**
 * A global middleware that only adds headers to responses can implement this
 * so its headers are also applied to *error* responses. Exceptions (404/405
 * from the router, HttpExceptions, CSRF failures, ...) unwind past the
 * middleware stack to the error handler, so without this an error response
 * would carry no CORS or security headers — and a cross-origin browser could
 * not even read the error body.
 */
interface ResponseDecoratorInterface
{
    public function decorate(Request $request, Response $response): Response;
}
