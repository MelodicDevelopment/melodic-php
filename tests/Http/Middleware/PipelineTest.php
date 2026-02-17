<?php

declare(strict_types=1);

namespace Tests\Http\Middleware;

use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use PHPUnit\Framework\TestCase;

class PipelineTest extends TestCase
{
    private function makeRequest(string $method = 'GET', string $uri = '/'): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],
            body: [],
            headers: [],
        );
    }

    private function makeFinalHandler(int $statusCode = 200, string $body = 'final'): RequestHandlerInterface
    {
        return new class($statusCode, $body) implements RequestHandlerInterface {
            public function __construct(
                private readonly int $statusCode,
                private readonly string $body,
            ) {}

            public function handle(Request $request): Response
            {
                return new Response($this->statusCode, $this->body);
            }
        };
    }

    // -------------------------------------------------------
    // Empty pipeline — only final handler
    // -------------------------------------------------------

    public function testEmptyPipelineReturnsFallbackHandlerResponse(): void
    {
        $pipeline = new Pipeline($this->makeFinalHandler(200, 'hello'));

        $response = $pipeline->handle($this->makeRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('hello', $response->getBody());
    }

    // -------------------------------------------------------
    // Single middleware
    // -------------------------------------------------------

    public function testSingleMiddlewareCanModifyResponse(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                $response = $handler->handle($request);

                return $response->withHeader('X-Modified', 'yes');
            }
        };

        $pipeline = new Pipeline($this->makeFinalHandler());
        $pipeline->pipe($middleware);

        $response = $pipeline->handle($this->makeRequest());

        $this->assertSame('yes', $response->getHeaders()['X-Modified']);
        $this->assertSame('final', $response->getBody());
    }

    public function testSingleMiddlewareCanModifyRequest(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public ?string $capturedAttribute = null;

            public function handle(Request $request): Response
            {
                $this->capturedAttribute = $request->getAttribute('added');

                return new Response(200, 'ok');
            }
        };

        $middleware = new class implements MiddlewareInterface {
            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                return $handler->handle($request->withAttribute('added', 'value'));
            }
        };

        $pipeline = new Pipeline($finalHandler);
        $pipeline->pipe($middleware);
        $pipeline->handle($this->makeRequest());

        $this->assertSame('value', $finalHandler->capturedAttribute);
    }

    // -------------------------------------------------------
    // Multiple middleware — execution order
    // -------------------------------------------------------

    public function testMultipleMiddlewareExecuteInOrder(): void
    {
        $log = [];

        $makeMiddleware = function (string $name) use (&$log): MiddlewareInterface {
            return new class($name, $log) implements MiddlewareInterface {
                public function __construct(
                    private readonly string $name,
                    private array &$log,
                ) {}

                public function process(Request $request, RequestHandlerInterface $handler): Response
                {
                    $this->log[] = $this->name . ':before';
                    $response = $handler->handle($request);
                    $this->log[] = $this->name . ':after';

                    return $response;
                }
            };
        };

        $finalHandler = new class($log) implements RequestHandlerInterface {
            public function __construct(private array &$log) {}

            public function handle(Request $request): Response
            {
                $this->log[] = 'handler';

                return new Response(200, 'done');
            }
        };

        $pipeline = new Pipeline($finalHandler);
        $pipeline->pipe($makeMiddleware('first'));
        $pipeline->pipe($makeMiddleware('second'));
        $pipeline->pipe($makeMiddleware('third'));

        $pipeline->handle($this->makeRequest());

        $this->assertSame([
            'first:before',
            'second:before',
            'third:before',
            'handler',
            'third:after',
            'second:after',
            'first:after',
        ], $log);
    }

    public function testMultipleMiddlewareCanEachModifyResponse(): void
    {
        $addHeader = function (string $name, string $value): MiddlewareInterface {
            return new class($name, $value) implements MiddlewareInterface {
                public function __construct(
                    private readonly string $name,
                    private readonly string $value,
                ) {}

                public function process(Request $request, RequestHandlerInterface $handler): Response
                {
                    $response = $handler->handle($request);

                    return $response->withHeader($this->name, $this->value);
                }
            };
        };

        $pipeline = new Pipeline($this->makeFinalHandler());
        $pipeline->pipe($addHeader('X-First', '1'));
        $pipeline->pipe($addHeader('X-Second', '2'));

        $response = $pipeline->handle($this->makeRequest());

        $headers = $response->getHeaders();
        $this->assertSame('1', $headers['X-First']);
        $this->assertSame('2', $headers['X-Second']);
    }

    // -------------------------------------------------------
    // Short-circuiting middleware
    // -------------------------------------------------------

    public function testMiddlewareCanShortCircuitWithoutCallingNext(): void
    {
        $shortCircuit = new class implements MiddlewareInterface {
            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                return new Response(403, 'forbidden');
            }
        };

        $neverReached = new class implements MiddlewareInterface {
            public bool $wasCalled = false;

            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                $this->wasCalled = true;

                return $handler->handle($request);
            }
        };

        $finalHandler = new class implements RequestHandlerInterface {
            public bool $wasCalled = false;

            public function handle(Request $request): Response
            {
                $this->wasCalled = true;

                return new Response(200, 'ok');
            }
        };

        $pipeline = new Pipeline($finalHandler);
        $pipeline->pipe($shortCircuit);
        $pipeline->pipe($neverReached);

        $response = $pipeline->handle($this->makeRequest());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('forbidden', $response->getBody());
        $this->assertFalse($neverReached->wasCalled);
        $this->assertFalse($finalHandler->wasCalled);
    }

    public function testLaterMiddlewareCanShortCircuitWhileEarlierStillPostProcesses(): void
    {
        $log = [];

        $outerMiddleware = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}

            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                $this->log[] = 'outer:before';
                $response = $handler->handle($request);
                $this->log[] = 'outer:after';

                return $response;
            }
        };

        $shortCircuit = new class($log) implements MiddlewareInterface {
            public function __construct(private array &$log) {}

            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                $this->log[] = 'short-circuit';

                return new Response(401, 'unauthorized');
            }
        };

        $finalHandler = new class($log) implements RequestHandlerInterface {
            public function __construct(private array &$log) {}

            public function handle(Request $request): Response
            {
                $this->log[] = 'handler';

                return new Response(200, 'ok');
            }
        };

        $pipeline = new Pipeline($finalHandler);
        $pipeline->pipe($outerMiddleware);
        $pipeline->pipe($shortCircuit);

        $response = $pipeline->handle($this->makeRequest());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame([
            'outer:before',
            'short-circuit',
            'outer:after',
        ], $log);
    }

    // -------------------------------------------------------
    // Request modifications preserved through chain
    // -------------------------------------------------------

    public function testRequestModificationsArePreservedThroughChain(): void
    {
        $addAttribute = function (string $key, string $value): MiddlewareInterface {
            return new class($key, $value) implements MiddlewareInterface {
                public function __construct(
                    private readonly string $key,
                    private readonly string $value,
                ) {}

                public function process(Request $request, RequestHandlerInterface $handler): Response
                {
                    return $handler->handle($request->withAttribute($this->key, $this->value));
                }
            };
        };

        $finalHandler = new class implements RequestHandlerInterface {
            public ?Request $receivedRequest = null;

            public function handle(Request $request): Response
            {
                $this->receivedRequest = $request;

                return new Response(200, 'ok');
            }
        };

        $pipeline = new Pipeline($finalHandler);
        $pipeline->pipe($addAttribute('role', 'admin'));
        $pipeline->pipe($addAttribute('tenant', 'acme'));

        $pipeline->handle($this->makeRequest());

        $this->assertSame('admin', $finalHandler->receivedRequest->getAttribute('role'));
        $this->assertSame('acme', $finalHandler->receivedRequest->getAttribute('tenant'));
    }

    // -------------------------------------------------------
    // Pipe returns self for chaining
    // -------------------------------------------------------

    public function testPipeReturnsSelfForChaining(): void
    {
        $middleware = new class implements MiddlewareInterface {
            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                return $handler->handle($request);
            }
        };

        $pipeline = new Pipeline($this->makeFinalHandler());
        $result = $pipeline->pipe($middleware);

        $this->assertSame($pipeline, $result);
    }
}
