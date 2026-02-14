<?php

declare(strict_types=1);

namespace Melodic\Core;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\JsonResponse;
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
        // Boot all service providers
        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

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
                            return new JsonResponse(['error' => 'Not Found'], 404);
                        }
                    }
                );
            }
        };

        // Build pipeline with all middleware
        $pipeline = new Pipeline($finalHandler);

        foreach ($this->middlewares as $middleware) {
            $pipeline->pipe($middleware);
        }

        $response = $pipeline->handle($request);
        $response->send();
    }
}
