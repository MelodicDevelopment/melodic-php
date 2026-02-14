<?php

declare(strict_types=1);

namespace Melodic\Http;

class JsonResponse extends Response
{
    public function __construct(
        mixed $data,
        int $statusCode = 200,
        array $headers = [],
    ) {
        $headers['Content-Type'] = 'application/json';

        parent::__construct(
            statusCode: $statusCode,
            body: json_encode($data, JSON_THROW_ON_ERROR),
            headers: $headers,
        );
    }
}
