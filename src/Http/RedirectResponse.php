<?php

declare(strict_types=1);

namespace Melodic\Http;

class RedirectResponse extends Response
{
    public function __construct(string $url, int $statusCode = 302)
    {
        parent::__construct(
            statusCode: $statusCode,
            body: '',
            headers: ['Location' => $url],
        );
    }
}
