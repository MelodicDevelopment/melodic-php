<?php

declare(strict_types=1);

namespace Melodic\Core;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Error\ExceptionHandler;
use Melodic\Http\Middleware\ErrorHandlerMiddleware;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\Exception\NotFoundException;
use Melodic\Log\LoggerInterface;
use Melodic\Log\NullLogger;
use Melodic\Routing\Router;
use Melodic\Routing\RoutingMiddleware;

class Application
{
    private readonly Configuration $configuration;
    private readonly Container $container;
    private readonly Router $router;

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /** @var ServiceProvider[] */
    private array $providers = [];

    public function __construct(
        private readonly string $basePath,
    ) {
        $this->configuration = new Configuration();
        $this->container = new Container();
        $this->router = new Router();

        $this->container->instance(Configuration::class, $this->configuration);
        $this->container->instance(Container::class, $this->container);
        $this->container->instance(Router::class, $this->router);
        $this->container->instance(self::class, $this);
    }

    public function loadConfig(string $path): self
    {
        $fullPath = str_starts_with($path, '/')
            ? $path
            : $this->basePath . '/' . $path;

        $this->configuration->loadFile($fullPath);

        return $this;
    }

    public function config(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->configuration;
        }

        return $this->configuration->get($key, $default);
    }

    public function addMiddleware(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;

        return $this;
    }

    public function services(callable $callback): self
    {
        $callback($this->container);

        return $this;
    }

    public function register(ServiceProvider $provider): self
    {
        $this->providers[] = $provider;
        $provider->register($this->container);

        return $this;
    }

    public function routes(callable $callback): self
    {
        $callback($this->router);

        return $this;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    public function getConfiguration(): Configuration
    {
        return $this->configuration;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function run(?Request $request = null): void
    {
        $debug = (bool) $this->configuration->get('app.debug', false);

        try {
            // Boot all service providers
            foreach ($this->providers as $provider) {
                $provider->boot($this->container);
            }

            // Resolve logger (falls back to NullLogger if not registered)
            $logger = $this->container->has(LoggerInterface::class)
                ? $this->container->get(LoggerInterface::class)
                : new NullLogger();

            // Register the exception handler in the container
            $exceptionHandler = new ExceptionHandler($logger);
            $exceptionHandler->setDebug($debug);
            $this->container->instance(ExceptionHandler::class, $exceptionHandler);

            $request = $request ?? Request::capture();

            // Build the final handler (routing middleware)
            $routingMiddleware = new RoutingMiddleware($this->router, $this->container);
            $finalHandler = new class($routingMiddleware) implements RequestHandlerInterface {
                public function __construct(
                    private readonly RoutingMiddleware $routing,
                ) {}

                public function handle(Request $request): Response
                {
                    return $this->routing->process(
                        $request,
                        new class implements RequestHandlerInterface {
                            public function handle(Request $request): Response
                            {
                                throw new NotFoundException();
                            }
                        }
                    );
                }
            };

            // Build pipeline with all middleware
            $pipeline = new Pipeline($finalHandler);

            // Error handler is piped first so it wraps the entire middleware stack
            $pipeline->pipe(new ErrorHandlerMiddleware($logger, $debug));

            foreach ($this->middlewares as $middleware) {
                $pipeline->pipe($middleware);
            }

            $response = $pipeline->handle($request);
            $response->send();
        } catch (\Throwable $e) {
            // Last-resort safety net for catastrophic failures
            // Use ExceptionHandler if available, otherwise fall back to plain text
            $request = $request ?? new Request();

            if (isset($exceptionHandler)) {
                $response = $exceptionHandler->handle($e, $request);
                $response->send();
            } else {
                http_response_code(500);
                header('Content-Type: text/plain; charset=UTF-8');

                if ($debug) {
                    echo "Fatal error: {$e->getMessage()}\n";
                    echo "In: {$e->getFile()}:{$e->getLine()}\n";
                    echo $e->getTraceAsString();
                } else {
                    echo 'An internal server error occurred.';
                }
            }
        }
    }
}
