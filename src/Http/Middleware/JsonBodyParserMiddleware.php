<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $contentType = $request->header('Content-Type');

        if ($contentType !== null && str_contains(strtolower($contentType), 'application/json')) {
            $rawBody = $request->rawBody();

            if ($rawBody !== '') {
                $parsed = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
                $request = $request->withAttribute('parsedBody', $parsed);
            }
        }

        return $handler->handle($request);
    }
}
