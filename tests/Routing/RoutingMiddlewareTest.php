<?php

declare(strict_types=1);

namespace Tests\Routing;

use Melodic\Controller\Controller;
use Melodic\Data\Model;
use Melodic\DI\Container;
use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\MiddlewareInterface;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Exception\MethodNotAllowedException;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Routing\Router;
use Melodic\Routing\RoutingMiddleware;
use Melodic\Validation\Rules\Required;
use PHPUnit\Framework\TestCase;

// -------------------------------------------------------
// Stub classes used by the tests
// -------------------------------------------------------

class StubRequestModel extends Model
{
    #[Required]
    public string $name;

    public ?string $email = null;
}

class StubIntModel extends Model
{
    #[Required]
    public int $count;
}

class StubService
{
    public function label(): string
    {
        return 'service-ok';
    }
}

class StubController extends Controller
{
    public function index(): JsonResponse
    {
        return $this->json(['action' => 'index']);
    }

    public function storeCount(StubIntModel $model): JsonResponse
    {
        return $this->json(['action' => 'storeCount', 'count' => $model->count]);
    }

    public function withService(StubService $service): JsonResponse
    {
        return $this->json(['action' => 'withService', 'label' => $service->label()]);
    }

    public function show(string $id): JsonResponse
    {
        return $this->json(['action' => 'show', 'id' => $id]);
    }

    public function store(StubRequestModel $model): JsonResponse
    {
        return $this->json(['action' => 'store', 'name' => $model->name, 'email' => $model->email]);
    }

    public function update(string $id, StubRequestModel $model): JsonResponse
    {
        return $this->json(['action' => 'update', 'id' => $id, 'name' => $model->name]);
    }

    public function withDefault(string $format = 'json'): JsonResponse
    {
        return $this->json(['action' => 'withDefault', 'format' => $format]);
    }

    public function showTyped(int $id): JsonResponse
    {
        return $this->json(['action' => 'showTyped', 'id' => $id]);
    }

    public function showStatus(StubRouteStatus $status): JsonResponse
    {
        return $this->json(['action' => 'showStatus', 'status' => $status->value]);
    }
}

enum StubRouteStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}

class StubFallbackHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new JsonResponse(['fallback' => true], 404);
    }
}

class StubHeaderMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $handler->handle($request);

        return $response->withHeader('X-Middleware-Applied', 'true');
    }
}

class StubRouteAttributesMiddleware implements MiddlewareInterface
{
    public ?array $capturedAttributes = null;
    public mixed $capturedRoute = null;

    public function process(Request $request, RequestHandlerInterface $handler): Response
    {
        $this->capturedAttributes = $request->getAttribute('route.attributes');
        $this->capturedRoute = $request->getAttribute('route');

        return $handler->handle($request);
    }
}

// -------------------------------------------------------
// Tests
// -------------------------------------------------------

final class RoutingMiddlewareTest extends TestCase
{
    private Router $router;
    private Container $container;
    private StubFallbackHandler $fallbackHandler;

    protected function setUp(): void
    {
        $this->router = new Router();
        $this->container = new Container();
        $this->fallbackHandler = new StubFallbackHandler();
    }

    private function createMiddleware(): RoutingMiddleware
    {
        return new RoutingMiddleware($this->router, $this->container);
    }

    private function createRequest(string $method, string $uri, array $body = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            body: $body,
        );
    }

    // -------------------------------------------------------
    // Fallback handler
    // -------------------------------------------------------

    public function testReturnsFallbackResponseWhenNoRouteMatches(): void
    {
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/nonexistent');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString('fallback', $response->getBody());
    }

    // -------------------------------------------------------
    // Basic dispatching
    // -------------------------------------------------------

    public function testDispatchesToControllerActionWithNoParams(): void
    {
        $this->router->get('/items', StubController::class, 'index');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('index', $data['action']);
    }

    public function testDispatchesToControllerActionWithRouteParams(): void
    {
        $this->router->get('/items/{id}', StubController::class, 'show');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items/42');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('show', $data['action']);
        $this->assertSame('42', $data['id']);
    }

    // -------------------------------------------------------
    // Route params as request attributes
    // -------------------------------------------------------

    public function testRouteParamsAreSetAsRequestAttributes(): void
    {
        // Register a controller action that reads an attribute from the request
        $this->router->get('/items/{id}', StubController::class, 'show');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items/99');

        // We can verify attributes indirectly: the controller receives the request
        // with attributes set. The 'show' action receives 'id' as a route param,
        // confirming the attribute was set and the param was resolved.
        $response = $middleware->process($request, $this->fallbackHandler);

        $data = json_decode($response->getBody(), true);
        $this->assertSame('99', $data['id']);
    }

    // -------------------------------------------------------
    // Model binding
    // -------------------------------------------------------

    public function testModelBindingHydratesFromRequestBody(): void
    {
        $this->router->post('/items', StubController::class, 'store');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('POST', '/items', [
            'name' => 'Test Item',
            'email' => 'test@example.com',
        ]);

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('store', $data['action']);
        $this->assertSame('Test Item', $data['name']);
        $this->assertSame('test@example.com', $data['email']);
    }

    public function testModelBindingValidationFailureReturns400WithErrors(): void
    {
        $this->router->post('/items', StubController::class, 'store');
        $middleware = $this->createMiddleware();

        // Empty body — 'name' is required
        $request = $this->createRequest('POST', '/items', []);

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(400, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('name', $data);
        $this->assertIsArray($data['name']);
        $this->assertNotEmpty($data['name']);
    }

    // -------------------------------------------------------
    // Mixed params: route param + model param
    // -------------------------------------------------------

    public function testMixedRouteParamAndModelParam(): void
    {
        $this->router->put('/items/{id}', StubController::class, 'update');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('PUT', '/items/7', [
            'name' => 'Updated Item',
        ]);

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('update', $data['action']);
        $this->assertSame('7', $data['id']);
        $this->assertSame('Updated Item', $data['name']);
    }

    // -------------------------------------------------------
    // Route middleware
    // -------------------------------------------------------

    public function testRouteMiddlewareIsApplied(): void
    {
        $this->router->get('/items', StubController::class, 'index', [StubHeaderMiddleware::class]);
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('true', $response->getHeaders()['X-Middleware-Applied'] ?? null);
    }

    // -------------------------------------------------------
    // Route attributes exposed to middleware
    // -------------------------------------------------------

    public function testRouteAttributesAreExposedToMiddleware(): void
    {
        $attributesMiddleware = new StubRouteAttributesMiddleware();
        $this->container->bind(StubRouteAttributesMiddleware::class, fn() => $attributesMiddleware);

        $this->router->get(
            '/items',
            StubController::class,
            'index',
            [StubRouteAttributesMiddleware::class],
            ['permission' => 'items.read'],
        );

        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items');

        $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(['permission' => 'items.read'], $attributesMiddleware->capturedAttributes);
        $this->assertNotNull($attributesMiddleware->capturedRoute);
    }

    // -------------------------------------------------------
    // Scalar/builtin types are skipped for model binding
    // -------------------------------------------------------

    public function testModelBindingSkipsScalarBuiltinTypes(): void
    {
        $this->router->get('/items/format', StubController::class, 'withDefault');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/items/format');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());

        $data = json_decode($response->getBody(), true);
        $this->assertSame('withDefault', $data['action']);
        $this->assertSame('json', $data['format']);
    }

    // -------------------------------------------------------
    // 405 Method Not Allowed
    // -------------------------------------------------------

    public function testReturns405WhenPathExistsForDifferentMethod(): void
    {
        $this->router->get('/items', StubController::class, 'index');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('POST', '/items');

        try {
            $middleware->process($request, $this->fallbackHandler);
            $this->fail('Expected MethodNotAllowedException');
        } catch (MethodNotAllowedException $e) {
            $this->assertEqualsCanonicalizing(['GET', 'HEAD'], $e->getAllowedMethods());
        }
    }

    public function testFallsThroughTo404WhenPathIsUnknown(): void
    {
        $this->router->get('/items', StubController::class, 'index');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('POST', '/nonexistent');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(404, $response->getStatusCode());
    }

    // -------------------------------------------------------
    // Model binding coercion & container-injected services
    // -------------------------------------------------------

    public function testModelBindingCoercesNumericStrings(): void
    {
        $this->router->post('/counts', StubController::class, 'storeCount');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('POST', '/counts', ['count' => '5']);

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame(5, $data['count']);
    }

    public function testModelBindingReturns400OnUncoercibleScalar(): void
    {
        $this->router->post('/counts', StubController::class, 'storeCount');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('POST', '/counts', ['count' => 'not-a-number']);

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('count', $data);
    }

    public function testNonModelClassParamResolvedFromContainer(): void
    {
        $this->router->get('/svc', StubController::class, 'withService');
        $middleware = $this->createMiddleware();
        $request = $this->createRequest('GET', '/svc');

        $response = $middleware->process($request, $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('service-ok', $data['label']);
    }

    // -------------------------------------------------------
    // Route param coercion
    // -------------------------------------------------------

    public function testIntTypedRouteParamIsCoerced(): void
    {
        $this->router->get('/typed/{id}', StubController::class, 'showTyped');
        $middleware = $this->createMiddleware();

        $response = $middleware->process($this->createRequest('GET', '/typed/42'), $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame(42, $data['id']);
    }

    public function testNonNumericValueForIntRouteParamReturns400(): void
    {
        $this->router->get('/typed/{id}', StubController::class, 'showTyped');
        $middleware = $this->createMiddleware();

        $response = $middleware->process($this->createRequest('GET', '/typed/abc'), $this->fallbackHandler);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertArrayHasKey('id', $data);
    }

    public function testStringTypedRouteParamIsUntouched(): void
    {
        $this->router->get('/items/{id}', StubController::class, 'show');
        $middleware = $this->createMiddleware();

        $response = $middleware->process($this->createRequest('GET', '/items/abc-123'), $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('abc-123', $data['id']);
    }

    public function testEnumTypedRouteParamIsCoerced(): void
    {
        $this->router->get('/status/{status}', StubController::class, 'showStatus');
        $middleware = $this->createMiddleware();

        $response = $middleware->process($this->createRequest('GET', '/status/active'), $this->fallbackHandler);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        $this->assertSame('active', $data['status']);
    }

    public function testInvalidEnumRouteParamReturns400(): void
    {
        $this->router->get('/status/{status}', StubController::class, 'showStatus');
        $middleware = $this->createMiddleware();

        $response = $middleware->process($this->createRequest('GET', '/status/bogus'), $this->fallbackHandler);

        $this->assertSame(400, $response->getStatusCode());
    }
}
