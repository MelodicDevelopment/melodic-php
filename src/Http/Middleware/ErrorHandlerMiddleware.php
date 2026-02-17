<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Exception\HttpException;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\LoggerInterface;
use Melodic\Security\SecurityException;

class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $debug = false,
    ) {}

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $e) {
            return $this->handleException($e, $request);
        }
    }

    private function handleException(\Throwable $e, Request $request): Response
    {
        $statusCode = $this->resolveStatusCode($e);
        $message = $this->resolveMessage($e, $statusCode);

        $this->logException($e, $statusCode, $request);

        if ($this->isJsonRequest($request)) {
            return $this->buildJsonResponse($e, $statusCode, $message);
        }

        return $this->buildHtmlResponse($e, $statusCode, $message);
    }

    private function resolveStatusCode(\Throwable $e): int
    {
        return match (true) {
            $e instanceof HttpException => $e->getStatusCode(),
            $e instanceof SecurityException => 401,
            $e instanceof \JsonException => 400,
            default => 500,
        };
    }

    private function resolveMessage(\Throwable $e, int $statusCode): string
    {
        if ($statusCode >= 500 && !$this->debug) {
            return 'An internal server error occurred.';
        }

        return $e->getMessage() ?: $this->defaultStatusMessage($statusCode);
    }

    private function defaultStatusMessage(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            500 => 'Internal Server Error',
            default => 'Error',
        };
    }

    private function logException(\Throwable $e, int $statusCode, Request $request): void
    {
        $context = [
            'exception' => $e,
            'status' => $statusCode,
            'method' => $request->method()->value,
            'path' => $request->path(),
        ];

        if ($statusCode >= 500) {
            $this->logger->error('Server error: {method} {path} [{status}]', $context);
        } else {
            $this->logger->warning('Client error: {method} {path} [{status}]', $context);
        }
    }

    private function isJsonRequest(Request $request): bool
    {
        $accept = $request->header('Accept') ?? '';
        $contentType = $request->header('Content-Type') ?? '';
        $uri = $request->path();

        return str_contains($accept, 'application/json')
            || str_contains($contentType, 'application/json')
            || str_starts_with($uri, '/api');
    }

    private function buildJsonResponse(\Throwable $e, int $statusCode, string $message): JsonResponse
    {
        $data = ['error' => $message];

        if ($this->debug) {
            $data['exception'] = get_class($e);
            $data['file'] = $e->getFile();
            $data['line'] = $e->getLine();
            $data['trace'] = explode("\n", $e->getTraceAsString());
        }

        return new JsonResponse($data, $statusCode);
    }

    private function buildHtmlResponse(\Throwable $e, int $statusCode, string $message): Response
    {
        $title = htmlspecialchars("{$statusCode} — {$this->defaultStatusMessage($statusCode)}", ENT_QUOTES, 'UTF-8');
        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        $debugHtml = '';
        if ($this->debug) {
            $exceptionClass = htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars("{$e->getFile()}:{$e->getLine()}", ENT_QUOTES, 'UTF-8');
            $trace = htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8');

            $debugHtml = <<<HTML
                <div style="margin-top: 2rem; padding: 1.5rem; background: #1e1e2e; border-radius: 8px; text-align: left;">
                    <p style="margin: 0 0 0.5rem; color: #f38ba8; font-weight: 600;">{$exceptionClass}</p>
                    <p style="margin: 0 0 1rem; color: #a6adc8; font-size: 0.85rem;">{$file}</p>
                    <pre style="margin: 0; padding: 1rem; background: #11111b; border-radius: 4px; color: #cdd6f4; font-size: 0.8rem; overflow-x: auto; white-space: pre-wrap;">{$trace}</pre>
                </div>
            HTML;
        }

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>{$title}</title>
        </head>
        <body style="margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #181825; color: #cdd6f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;">
            <div style="text-align: center; max-width: 600px; padding: 2rem;">
                <h1 style="font-size: 4rem; margin: 0; color: #f38ba8;">{$statusCode}</h1>
                <p style="font-size: 1.25rem; margin: 1rem 0; color: #a6adc8;">{$safeMessage}</p>
                {$debugHtml}
            </div>
        </body>
        </html>
        HTML;

        return new Response(
            statusCode: $statusCode,
            body: $html,
            headers: ['Content-Type' => 'text/html; charset=UTF-8'],
        );
    }
}
