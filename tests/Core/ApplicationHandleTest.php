<?php

declare(strict_types=1);

namespace Tests\Core;

use Melodic\Controller\ApiController;
use Melodic\Controller\MvcController;
use Melodic\Core\Application;
use Melodic\Data\Model;
use Melodic\Http\JsonResponse;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Routing\FromQuery;
use Melodic\Routing\Router;
use Melodic\Security\AuthorizationMiddleware;
use Melodic\Security\UserContext;
use Melodic\Validation\Rules\Min;
use Melodic\Validation\Rules\Required;
use PHPUnit\Framework\TestCase;

class HandleAddress extends Model
{
    #[Required]
    public ?string $City = null;
}

class HandleOrder extends Model
{
    public ?array $Tags = null;
    public ?HandleAddress $Address = null;
}

class HandleFilter extends Model
{
    #[Min(1)]
    public ?int $Page = null;
}

class HandleController extends ApiController
{
    public function show(string $name): JsonResponse
    {
        return $this->json(['name' => $name]);
    }

    public function store(HandleOrder $order): JsonResponse
    {
        return $this->json(['city' => $order->Address?->City, 'tags' => $order->Tags]);
    }

    public function index(#[FromQuery] HandleFilter $filter): JsonResponse
    {
        return $this->json(['page' => $filter->Page]);
    }

    public function admin(): JsonResponse
    {
        return $this->json(['ok' => true]);
    }
}

class HandlePageController extends MvcController
{
    public function home(): Response
    {
        return $this->view('home', ['path' => '/etc/hosts', 'name' => 'World']);
    }
}

class ApplicationHandleTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/melodic_handle_' . uniqid();
        mkdir($this->dir . '/views', 0755, true);
        file_put_contents($this->dir . '/views/home.phtml', 'Hello <?= $this->e($name) ?> at <?= $this->e($path) ?>');
    }

    protected function tearDown(): void
    {
        @unlink($this->dir . '/views/home.phtml');
        @rmdir($this->dir . '/views');
        @rmdir($this->dir);
    }

    private function app(): Application
    {
        $app = new Application($this->dir);
        $app->routes(function (Router $r): void {
            $r->get('/users/{name}', HandleController::class, 'show');
            $r->post('/orders', HandleController::class, 'store');
            $r->get('/orders', HandleController::class, 'index');
            $r->get('/admin', HandleController::class, 'admin', [AuthorizationMiddleware::class], ['entitlements' => ['admin']]);
            $r->get('/', HandlePageController::class, 'home');
        });

        return $app;
    }

    /** @param array<string, mixed> $body */
    private function request(string $method, string $uri, array $body = [], array $attributes = []): Request
    {
        parse_str((string) parse_url($uri, PHP_URL_QUERY), $query);

        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: $query,
            body: $body,
            headers: ['Accept' => 'application/json'],
            attributes: $attributes,
            cookies: [],
            files: [],
        );
    }

    public function testHandleReturnsResponseWithoutSending(): void
    {
        $response = $this->app()->handle($this->request('GET', '/users/John%20Doe'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"name":"John Doe"}', $response->getBody());
    }

    public function testHandleConvertsUnknownRouteTo404(): void
    {
        $this->assertSame(404, $this->app()->handle($this->request('GET', '/nope'))->getStatusCode());
    }

    public function testHandleCanBeCalledRepeatedly(): void
    {
        $app = $this->app();

        $this->assertSame(200, $app->handle($this->request('GET', '/users/a'))->getStatusCode());
        $this->assertSame(200, $app->handle($this->request('GET', '/users/b'))->getStatusCode());
    }

    public function testNestedModelIsHydratedFromBody(): void
    {
        $response = $this->app()->handle($this->request('POST', '/orders', [
            'tags' => ['a'],
            'address' => ['city' => 'Grand Rapids'],
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"city":"Grand Rapids","tags":["a"]}', $response->getBody());
    }

    public function testWrongShapeForArrayPropertyIs400Not500(): void
    {
        $response = $this->app()->handle($this->request('POST', '/orders', ['tags' => 'not-an-array']));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString('must be an array', $response->getBody());
    }

    public function testWrongShapeForNestedModelIs400Not500(): void
    {
        $response = $this->app()->handle($this->request('POST', '/orders', ['address' => 'nope']));

        $this->assertSame(400, $response->getStatusCode());
    }

    public function testNestedModelValidationErrorsUseDottedKeys(): void
    {
        $response = $this->app()->handle($this->request('POST', '/orders', ['address' => []]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertArrayHasKey('Address.City', json_decode($response->getBody(), true));
    }

    public function testFromQueryBindsAndValidatesQueryString(): void
    {
        $app = $this->app();

        $this->assertSame('{"page":3}', $app->handle($this->request('GET', '/orders?page=3'))->getBody());
        $this->assertSame(400, $app->handle($this->request('GET', '/orders?page=0'))->getStatusCode());
        $this->assertSame(400, $app->handle($this->request('GET', '/orders?page=abc'))->getStatusCode());
    }

    public function testRouteEntitlementAttributeIsEnforced(): void
    {
        $app = $this->app();
        $user = fn(array $entitlements) => UserContext::fromClaims(['sub' => '1', 'entitlements' => $entitlements]);

        $this->assertSame(401, $app->handle($this->request('GET', '/admin'))->getStatusCode());
        $this->assertSame(403, $app->handle($this->request('GET', '/admin', [], ['userContext' => $user(['editor'])]))->getStatusCode());
        $this->assertSame(200, $app->handle($this->request('GET', '/admin', [], ['userContext' => $user(['admin'])]))->getStatusCode());
    }

    public function testDefaultViewEngineRendersAndViewVariablesCannotHijackTheTemplate(): void
    {
        $response = $this->app()->handle($this->request('GET', '/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Hello World at /etc/hosts', $response->getBody());
    }

    public function testTrailingSlashMatchesSameRoute(): void
    {
        $this->assertSame(200, $this->app()->handle($this->request('GET', '/users/a/'))->getStatusCode());
    }
}
