<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

interface MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response;
}
