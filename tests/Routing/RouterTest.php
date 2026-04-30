<?php

declare(strict_types=1);

namespace Tests\Routing;

use Melodic\Http\HttpMethod;
use Melodic\Routing\Route;
use Melodic\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    // -------------------------------------------------------
    // Route registration for each HTTP method
    // -------------------------------------------------------

    public function testGetRegistersRouteWithGetMethod(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame(HttpMethod::GET, $routes[0]->method);
        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame('UserController', $routes[0]->controller);
        $this->assertSame('index', $routes[0]->action);
    }

    public function testPostRegistersRouteWithPostMethod(): void
    {
        $this->router->post('/users', 'UserController', 'store');

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame(HttpMethod::POST, $routes[0]->method);
        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame('store', $routes[0]->action);
    }

    public function testPutRegistersRouteWithPutMethod(): void
    {
        $this->router->put('/users/{id}', 'UserController', 'update');

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame(HttpMethod::PUT, $routes[0]->method);
        $this->assertSame('/users/{id}', $routes[0]->pattern);
        $this->assertSame('update', $routes[0]->action);
    }

    public function testDeleteRegistersRouteWithDeleteMethod(): void
    {
        $this->router->delete('/users/{id}', 'UserController', 'destroy');

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame(HttpMethod::DELETE, $routes[0]->method);
        $this->assertSame('destroy', $routes[0]->action);
    }

    public function testPatchRegistersRouteWithPatchMethod(): void
    {
        $this->router->patch('/users/{id}', 'UserController', 'partialUpdate');

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame(HttpMethod::PATCH, $routes[0]->method);
        $this->assertSame('partialUpdate', $routes[0]->action);
    }

    // -------------------------------------------------------
    // Route matching — exact paths
    // -------------------------------------------------------

    public function testMatchReturnsRouteForExactPath(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $result = $this->router->match(HttpMethod::GET, '/users');

        $this->assertNotNull($result);
        $this->assertInstanceOf(Route::class, $result['route']);
        $this->assertSame('UserController', $result['route']->controller);
        $this->assertSame('index', $result['route']->action);
        $this->assertSame([], $result['params']);
    }

    public function testMatchReturnsNullForWrongMethod(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $result = $this->router->match(HttpMethod::POST, '/users');

        $this->assertNull($result);
    }

    public function testMatchReturnsNullForWrongPath(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $result = $this->router->match(HttpMethod::GET, '/posts');

        $this->assertNull($result);
    }

    public function testMatchReturnsNullWhenNoRoutesRegistered(): void
    {
        $result = $this->router->match(HttpMethod::GET, '/anything');

        $this->assertNull($result);
    }

    // -------------------------------------------------------
    // Route matching — parameterized paths
    // -------------------------------------------------------

    public function testMatchExtractsParameterFromPath(): void
    {
        $this->router->get('/users/{id}', 'UserController', 'show');

        $result = $this->router->match(HttpMethod::GET, '/users/42');

        $this->assertNotNull($result);
        $this->assertSame('show', $result['route']->action);
        $this->assertSame('42', $result['params']['id']);
    }

    public function testMatchExtractsMultipleParametersFromPath(): void
    {
        $this->router->get('/users/{userId}/posts/{postId}', 'PostController', 'show');

        $result = $this->router->match(HttpMethod::GET, '/users/5/posts/99');

        $this->assertNotNull($result);
        $this->assertSame('5', $result['params']['userId']);
        $this->assertSame('99', $result['params']['postId']);
    }

    public function testMatchDoesNotMatchPartialPath(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $result = $this->router->match(HttpMethod::GET, '/users/extra');

        $this->assertNull($result);
    }

    public function testMatchDoesNotMatchShorterPath(): void
    {
        $this->router->get('/users/{id}', 'UserController', 'show');

        $result = $this->router->match(HttpMethod::GET, '/users');

        $this->assertNull($result);
    }

    public function testParameterDoesNotMatchSlashes(): void
    {
        $this->router->get('/users/{id}', 'UserController', 'show');

        $result = $this->router->match(HttpMethod::GET, '/users/42/extra');

        $this->assertNull($result);
    }

    // -------------------------------------------------------
    // Route groups with prefix
    // -------------------------------------------------------

    public function testGroupAppliesPrefixToRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/users', 'UserController', 'index');
        });

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/api/users', $routes[0]->pattern);
    }

    public function testNestedGroupsConcatenatePrefixes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->group('/v1', function (Router $r) {
                $r->get('/users', 'UserController', 'index');
            });
        });

        $routes = $this->router->getRoutes();

        $this->assertCount(1, $routes);
        $this->assertSame('/api/v1/users', $routes[0]->pattern);
    }

    public function testGroupPrefixDoesNotLeakToOuterRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/inner', 'InnerController', 'index');
        });
        $this->router->get('/outer', 'OuterController', 'index');

        $routes = $this->router->getRoutes();

        $this->assertCount(2, $routes);
        $this->assertSame('/api/inner', $routes[0]->pattern);
        $this->assertSame('/outer', $routes[1]->pattern);
    }

    public function testGroupedRoutesAreMatchable(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/users/{id}', 'UserController', 'show');
        });

        $result = $this->router->match(HttpMethod::GET, '/api/users/7');

        $this->assertNotNull($result);
        $this->assertSame('7', $result['params']['id']);
    }

    // -------------------------------------------------------
    // apiResource generates correct CRUD routes
    // -------------------------------------------------------

    public function testApiResourceRegistersAllCrudRoutes(): void
    {
        $this->router->apiResource('/users', 'UserController');

        $routes = $this->router->getRoutes();

        $this->assertCount(5, $routes);

        // GET /users -> index
        $this->assertSame(HttpMethod::GET, $routes[0]->method);
        $this->assertSame('/users', $routes[0]->pattern);
        $this->assertSame('index', $routes[0]->action);

        // GET /users/{id} -> show
        $this->assertSame(HttpMethod::GET, $routes[1]->method);
        $this->assertSame('/users/{id}', $routes[1]->pattern);
        $this->assertSame('show', $routes[1]->action);

        // POST /users -> store
        $this->assertSame(HttpMethod::POST, $routes[2]->method);
        $this->assertSame('/users', $routes[2]->pattern);
        $this->assertSame('store', $routes[2]->action);

        // PUT /users/{id} -> update
        $this->assertSame(HttpMethod::PUT, $routes[3]->method);
        $this->assertSame('/users/{id}', $routes[3]->pattern);
        $this->assertSame('update', $routes[3]->action);

        // DELETE /users/{id} -> destroy
        $this->assertSame(HttpMethod::DELETE, $routes[4]->method);
        $this->assertSame('/users/{id}', $routes[4]->pattern);
        $this->assertSame('destroy', $routes[4]->action);
    }

    public function testApiResourceInsideGroupGetsPrefixed(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->apiResource('/users', 'UserController');
        });

        $routes = $this->router->getRoutes();

        $this->assertCount(5, $routes);
        $this->assertSame('/api/users', $routes[0]->pattern);
        $this->assertSame('/api/users/{id}', $routes[1]->pattern);
    }

    public function testApiResourceRoutesAreMatchable(): void
    {
        $this->router->apiResource('/users', 'UserController');

        $index = $this->router->match(HttpMethod::GET, '/users');
        $this->assertNotNull($index);
        $this->assertSame('index', $index['route']->action);

        $show = $this->router->match(HttpMethod::GET, '/users/1');
        $this->assertNotNull($show);
        $this->assertSame('show', $show['route']->action);
        $this->assertSame('1', $show['params']['id']);

        $store = $this->router->match(HttpMethod::POST, '/users');
        $this->assertNotNull($store);
        $this->assertSame('store', $store['route']->action);

        $update = $this->router->match(HttpMethod::PUT, '/users/1');
        $this->assertNotNull($update);
        $this->assertSame('update', $update['route']->action);

        $destroy = $this->router->match(HttpMethod::DELETE, '/users/1');
        $this->assertNotNull($destroy);
        $this->assertSame('destroy', $destroy['route']->action);
    }

    // -------------------------------------------------------
    // Middleware assignment
    // -------------------------------------------------------

    public function testRouteMiddlewareIsStoredOnRoute(): void
    {
        $this->router->get('/admin', 'AdminController', 'index', ['AuthMiddleware']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['AuthMiddleware'], $routes[0]->middleware);
    }

    public function testGroupMiddlewareIsAppliedToRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/users', 'UserController', 'index');
        }, middleware: ['AuthMiddleware']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['AuthMiddleware'], $routes[0]->middleware);
    }

    public function testGroupAndRouteMiddlewareAreMerged(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/admin', 'AdminController', 'index', ['AdminMiddleware']);
        }, middleware: ['AuthMiddleware']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['AuthMiddleware', 'AdminMiddleware'], $routes[0]->middleware);
    }

    public function testNestedGroupMiddlewareIsConcatenated(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->group('/admin', function (Router $r) {
                $r->get('/dashboard', 'DashboardController', 'index');
            }, middleware: ['AdminMiddleware']);
        }, middleware: ['AuthMiddleware']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['AuthMiddleware', 'AdminMiddleware'], $routes[0]->middleware);
    }

    public function testGroupMiddlewareDoesNotLeakToOuterRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/inner', 'InnerController', 'index');
        }, middleware: ['AuthMiddleware']);
        $this->router->get('/outer', 'OuterController', 'index');

        $routes = $this->router->getRoutes();

        $this->assertSame(['AuthMiddleware'], $routes[0]->middleware);
        $this->assertSame([], $routes[1]->middleware);
    }

    public function testApiResourceMiddlewareIsAppliedToAllRoutes(): void
    {
        $this->router->apiResource('/users', 'UserController', ['AuthMiddleware']);

        $routes = $this->router->getRoutes();

        foreach ($routes as $route) {
            $this->assertSame(['AuthMiddleware'], $route->middleware);
        }
    }

    // -------------------------------------------------------
    // Route attributes
    // -------------------------------------------------------

    public function testRouteAttributesDefaultToEmptyArray(): void
    {
        $this->router->get('/users', 'UserController', 'index');

        $routes = $this->router->getRoutes();

        $this->assertSame([], $routes[0]->attributes);
    }

    public function testRouteAttributesAreStoredOnRoute(): void
    {
        $this->router->get('/users', 'UserController', 'index', [], ['permission' => 'users.view']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['permission' => 'users.view'], $routes[0]->attributes);
    }

    public function testGroupAttributesAreAppliedToRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/users', 'UserController', 'index');
        }, attributes: ['scope' => 'api']);

        $routes = $this->router->getRoutes();

        $this->assertSame(['scope' => 'api'], $routes[0]->attributes);
    }

    public function testRouteAttributesOverrideGroupAttributesOnConflict(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/users', 'UserController', 'index', [], ['permission' => 'users.view']);
        }, attributes: ['permission' => 'api.read', 'scope' => 'api']);

        $routes = $this->router->getRoutes();

        $this->assertSame(
            ['permission' => 'users.view', 'scope' => 'api'],
            $routes[0]->attributes,
        );
    }

    public function testNestedGroupAttributesMergeWithChildKeysWinning(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->group('/admin', function (Router $r) {
                $r->get('/dashboard', 'DashboardController', 'index');
            }, attributes: ['scope' => 'admin']);
        }, attributes: ['scope' => 'api', 'tier' => 'public']);

        $routes = $this->router->getRoutes();

        $this->assertSame(
            ['scope' => 'admin', 'tier' => 'public'],
            $routes[0]->attributes,
        );
    }

    public function testGroupAttributesDoNotLeakToOuterRoutes(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->get('/inner', 'InnerController', 'index');
        }, attributes: ['scope' => 'api']);
        $this->router->get('/outer', 'OuterController', 'index');

        $routes = $this->router->getRoutes();

        $this->assertSame(['scope' => 'api'], $routes[0]->attributes);
        $this->assertSame([], $routes[1]->attributes);
    }

    public function testApiResourceAttributesAreAppliedToAllRoutes(): void
    {
        $this->router->apiResource('/users', 'UserController', [], ['permission' => 'users.manage']);

        $routes = $this->router->getRoutes();

        $this->assertCount(5, $routes);

        foreach ($routes as $route) {
            $this->assertSame(['permission' => 'users.manage'], $route->attributes);
        }
    }

    public function testGroupAndApiResourceAttributesMerge(): void
    {
        $this->router->group('/api', function (Router $r) {
            $r->apiResource('/users', 'UserController', [], ['permission' => 'users.manage']);
        }, attributes: ['scope' => 'api']);

        $routes = $this->router->getRoutes();

        foreach ($routes as $route) {
            $this->assertSame(
                ['scope' => 'api', 'permission' => 'users.manage'],
                $route->attributes,
            );
        }
    }

    // -------------------------------------------------------
    // Fluent interface
    // -------------------------------------------------------

    public function testRegistrationMethodsReturnRouterForChaining(): void
    {
        $result = $this->router
            ->get('/a', 'A', 'a')
            ->post('/b', 'B', 'b')
            ->put('/c', 'C', 'c')
            ->delete('/d', 'D', 'd')
            ->patch('/e', 'E', 'e');

        $this->assertSame($this->router, $result);
        $this->assertCount(5, $this->router->getRoutes());
    }

    public function testMatchReturnsFirstMatchingRoute(): void
    {
        $this->router->get('/users', 'First', 'first');
        $this->router->get('/users', 'Second', 'second');

        $result = $this->router->match(HttpMethod::GET, '/users');

        $this->assertNotNull($result);
        $this->assertSame('First', $result['route']->controller);
    }
}
