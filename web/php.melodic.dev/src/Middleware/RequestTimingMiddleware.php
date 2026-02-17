<?php

declare(strict_types=1);

namespace MelodicWeb\Middleware;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;

class RequestTimingMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $start = hrtime(true);

        $response = $handler->handle($request);

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;

        return $response->withHeader('X-Response-Time', round($elapsedMs, 2) . 'ms');
    }
}
