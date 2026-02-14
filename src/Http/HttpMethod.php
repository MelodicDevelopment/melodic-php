<?php

declare(strict_types=1);

namespace Melodic\Http;

enum HttpMethod: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case OPTIONS = 'OPTIONS';

    public static function parse(string $method): self
    {
        return self::tryFrom(strtoupper($method))
            ?? throw new \ValueError("Invalid HTTP method: {$method}");
    }
}
