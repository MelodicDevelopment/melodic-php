<?php

declare(strict_types=1);

namespace Melodic\Core;

use Melodic\Cache\CacheInterface;
use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Error\ExceptionHandler;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\Pipeline;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Middleware\ResponseDecoratorInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Http\Exception\HttpException;
use Melodic\Http\Exception\NotFoundException;
use Melodic\Http\HttpMethod;
use Melodic\Log\LoggerInterface;
use Melodic\Log\NullLogger;
use Melodic\Routing\Router;
use Melodic\Routing\RoutingMiddleware;
use Melodic\View\ViewEngine;

class Application
{
    private readonly Configuration $configuration;
    private readonly Container $container;
    private readonly Router $router;

    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /** @var ServiceProvider[] */
    private array $providers = [];

    private bool $booted = false;

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

        // Default ViewEngine: templates live in {basePath}/views (override
        // with the `views.path` config key, or bind your own ViewEngine).
        // Lazy, so API-only applications never pay for it.
        $this->container->singleton(ViewEngine::class, function (Container $c): ViewEngine {
            $path = (string) $this->configuration->get('views.path', 'views');

            if (!self::isAbsolutePath($path)) {
                $path = $this->basePath . '/' . $path;
            }

            return new ViewEngine(
                $path,
                $c->isBound(CacheInterface::class) ? $c->get(CacheInterface::class) : null,
            );
        });
    }

    private static function isAbsolutePath(string $path): bool
    {
        // POSIX root, Windows drive ("C:\", "C:/") or UNC path.
        return str_starts_with($path, '/')
            || str_starts_with($path, '\\')
            || preg_match('#^[A-Za-z]:[\\/]#', $path) === 1;
    }

    public function loadConfig(string $path): self
    {
        $fullPath = self::isAbsolutePath($path)
            ? $path
            : $this->basePath . '/' . $path;

        $this->configuration->loadFile($fullPath);

        return $this;
    }

    public function loadEnvironmentConfig(string $configDir = 'config'): self
    {
        $basePath = $this->basePath . '/' . $configDir;

        // Always load base config
        $this->configuration->loadFile($basePath . '/config.json');

        // Detect environment
        $env = getenv('APP_ENV') ?: 'dev';

        // Load environment-specific overrides (skip for 'dev')
        if ($env !== 'dev') {
            $envPath = $basePath . '/config.' . $env . '.json';
            if (file_exists($envPath)) {
                $this->configuration->loadFile($envPath);
            }
        }

        // Load local developer overrides (always gitignored)
        $localPath = $basePath . '/config.dev.json';
        if (file_exists($localPath)) {
            $this->configuration->loadFile($localPath);
        }

        // Set environment in config for runtime access
        $this->configuration->set('app.environment', $env);

        return $this;
    }

    public function getEnvironment(): string
    {
        return $this->configuration->get('app.environment', 'dev');
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

        // A provider registered after boot (e.g. between handle() calls)
        // still gets its boot() hook.
        if ($this->booted) {
            $provider->boot($this->container);
        }

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

    /**
     * Boot service providers and bind the shared ExceptionHandler. Runs once;
     * called automatically by handle() and run().
     */
    public function boot(): self
    {
        if ($this->booted) {
            return $this;
        }

        $debug = (bool) $this->configuration->get('app.debug', false);

        // Bind the exception handler as a singleton so providers' boot() can
        // resolve it from the container and register exception mappers. An
        // application-supplied binding is left alone.
        if (!$this->container->isBound(ExceptionHandler::class)) {
            $this->container->singleton(ExceptionHandler::class, function (Container $c) use ($debug) {
                $logger = $c->has(LoggerInterface::class)
                    ? $c->get(LoggerInterface::class)
                    : new NullLogger();
                $handler = new ExceptionHandler($logger);
                $handler->setDebug($debug);
                return $handler;
            });
        }

        foreach ($this->providers as $provider) {
            $provider->boot($this->container);
        }

        $this->booted = true;

        return $this;
    }

    /**
     * Run a request through the middleware pipeline and router and return the
     * Response without sending it. Exceptions are converted to error responses
     * by the ExceptionHandler, exactly as in run(). This is the entry point
     * for tests and for long-running runtimes that manage their own I/O.
     */
    public function handle(Request $request): Response
    {
        $this->boot();

        /** @var ExceptionHandler $exceptionHandler */
        $exceptionHandler = $this->container->get(ExceptionHandler::class);

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

        $pipeline = new Pipeline($finalHandler);

        // Error handler is piped first so it wraps the entire middleware stack.
        // Header-only middleware (CORS, security headers) also decorate the
        // error responses it produces — see ResponseDecoratorInterface.
        $decorators = array_values(array_filter(
            $this->middlewares,
            fn(MiddlewareInterface $m): bool => $m instanceof ResponseDecoratorInterface,
        ));

        $pipeline->pipe(new class($exceptionHandler, $decorators) implements MiddlewareInterface {
            /** @param ResponseDecoratorInterface[] $decorators */
            public function __construct(
                private readonly ExceptionHandler $exceptionHandler,
                private readonly array $decorators,
            ) {}

            public function process(Request $request, RequestHandlerInterface $handler): Response
            {
                try {
                    return $handler->handle($request);
                } catch (\Throwable $e) {
                    $response = $this->exceptionHandler->handle($e, $request);

                    foreach (array_reverse($this->decorators) as $decorator) {
                        $response = $decorator->decorate($request, $response);
                    }

                    return $response;
                }
            }
        });

        foreach ($this->middlewares as $middleware) {
            $pipeline->pipe($middleware);
        }

        return $pipeline->handle($request);
    }

    public function run(?Request $request = null): void
    {
        $debug = (bool) $this->configuration->get('app.debug', false);

        try {
            $this->registerErrorHandlers();
            $this->boot();

            $request = $request ?? $this->captureRequest();

            $response = $this->handle($request);
            $response->send($request->method() !== HttpMethod::HEAD);
        } catch (\Throwable $e) {
            // Last-resort safety net for catastrophic failures (provider boot,
            // an unparseable request line, ...). Use the ExceptionHandler if
            // it can be resolved, otherwise fall back to plain text. The
            // fallback request must not re-read the superglobals' method —
            // that is what failed when the method was unsupported.
            $request = $request ?? new Request(server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? '/',
            ] + $_SERVER);

            try {
                /** @var ExceptionHandler $exceptionHandler */
                $exceptionHandler = $this->booted ? $this->container->get(ExceptionHandler::class) : null;
            } catch (\Throwable) {
                $exceptionHandler = null;
            }

            if ($exceptionHandler !== null) {
                $response = $exceptionHandler->handle($e, $request);
                $response->send($request->method() !== HttpMethod::HEAD);
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

    /**
     * Build the Request from the superglobals. An HTTP method the framework
     * does not model (PROPFIND, TRACE, garbage) is a client problem: answer
     * 501 instead of letting the ValueError escape as a fatal error.
     */
    private function captureRequest(): Request
    {
        try {
            return Request::capture();
        } catch (\ValueError $e) {
            throw new HttpException(501, 'Not Implemented', $e);
        }
    }

    /**
     * Opt-in: with `app.errorsAsExceptions` set, PHP warnings and notices
     * become ErrorExceptions (and therefore proper 500 responses and log
     * entries) instead of being silently ignored or leaking into output.
     * Deprecations are never converted. Off by default so existing
     * applications keep their current behavior.
     */
    private function registerErrorHandlers(): void
    {
        if (!(bool) $this->configuration->get('app.errorsAsExceptions', false)) {
            return;
        }

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0
                || in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)
            ) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
    }
}
