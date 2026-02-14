<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

interface RequestHandlerInterface
{
    public function handle(Request $request): Response;
}
