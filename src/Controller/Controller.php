<?php

declare(strict_types=1);

namespace Melodic\Controller;

use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;

abstract class Controller
{
    protected Request $request;

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    protected function json(mixed $data, int $statusCode = 200): JsonResponse
    {
        return new JsonResponse(data: $data, statusCode: $statusCode);
    }

    protected function created(mixed $data, ?string $location = null): JsonResponse
    {
        $headers = $location !== null ? ['Location' => $location] : [];

        return new JsonResponse(data: $data, statusCode: 201, headers: $headers);
    }

    protected function noContent(): Response
    {
        return new Response(statusCode: 204);
    }

    protected function notFound(mixed $data = null): JsonResponse
    {
        return new JsonResponse(
            data: $data ?? ['error' => 'Not Found'],
            statusCode: 404,
        );
    }

    protected function badRequest(mixed $data = null): JsonResponse
    {
        return new JsonResponse(
            data: $data ?? ['error' => 'Bad Request'],
            statusCode: 400,
        );
    }

    protected function unauthorized(mixed $data = null): JsonResponse
    {
        return new JsonResponse(
            data: $data ?? ['error' => 'Unauthorized'],
            statusCode: 401,
        );
    }

    protected function forbidden(mixed $data = null): JsonResponse
    {
        return new JsonResponse(
            data: $data ?? ['error' => 'Forbidden'],
            statusCode: 403,
        );
    }
}
