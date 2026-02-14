<?php

declare(strict_types=1);

namespace Melodic\Http\Middleware;

use Melodic\Http\Request;
use Melodic\Http\Response;

class Pipeline implements RequestHandlerInterface
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    public function __construct(
        private readonly RequestHandlerInterface $fallbackHandler,
    ) {}

    public function pipe(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function handle(Request $request): Response
    {
        $handler = $this->buildHandler(0);

        return $handler->handle($request);
    }

    private function buildHandler(int $index): RequestHandlerInterface
    {
        if ($index >= count($this->middlewares)) {
            return $this->fallbackHandler;
        }

        $middleware = $this->middlewares[$index];
        $next = $this->buildHandler($index + 1);

        return new class($middleware, $next) implements RequestHandlerInterface {
            public function __construct(
                private readonly MiddlewareInterface $middleware,
                private readonly RequestHandlerInterface $next,
            ) {}

            public function handle(Request $request): Response
            {
                return $this->middleware->process($request, $this->next);
            }
        };
    }
}
