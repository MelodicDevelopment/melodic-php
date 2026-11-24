<?php

declare(strict_types=1);

namespace Tests\Security;

use Melodic\Cache\ArrayCache;
use Melodic\Cache\Psr16CacheAdapter;
use Melodic\Core\Configuration;
use Melodic\DI\Container;
use Melodic\DI\NotFoundException;
use Melodic\Http\Exception\HttpException;
use Melodic\Http\Middleware\RequestHandlerInterface;
use Melodic\Http\Request;
use Melodic\Http\Response;
use Melodic\Log\FileLogger;
use Melodic\Log\LogLevel;
use Melodic\Log\PsrLoggerAdapter;
use Melodic\Security\CsrfMiddleware;
use Melodic\Security\CsrfToken;
use Melodic\Security\UserContext;
use Melodic\Session\ArraySession;
use Melodic\Validation\Rules\Date;
use Melodic\Validation\Rules\Url;
use Melodic\Validation\Rules\Uuid;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface as PsrContainer;
use Psr\Container\NotFoundExceptionInterface;
use Psr\Log\AbstractLogger;

class CapturingHandler implements RequestHandlerInterface
{
    public ?Request $request = null;

    public function handle(Request $request): Response
    {
        $this->request = $request;

        return new Response(200, 'ok');
    }
}

class SecurityFeaturesTest extends TestCase
{
    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    private function request(string $method, string $uri, array $body = [], array $headers = []): Request
    {
        return new Request(
            server: ['REQUEST_METHOD' => $method, 'REQUEST_URI' => $uri],
            query: [],
            body: $body,
            headers: $headers,
            cookies: [],
            files: [],
        );
    }

    public function testCsrfMiddlewareExposesTokenAndRejectsForgedPosts(): void
    {
        $session = new ArraySession();
        $middleware = new CsrfMiddleware($session);
        $handler = new CapturingHandler();

        $middleware->process($this->request('GET', '/form'), $handler);
        $token = $handler->request?->getAttribute('csrfToken');
        $this->assertIsString($token);

        // valid token, reusable (per-session, not one-time)
        $middleware->process($this->request('POST', '/form', ['csrf_token' => $token]), $handler);
        $middleware->process($this->request('POST', '/form', [], ['X-CSRF-Token' => $token]), $handler);

        try {
            $middleware->process($this->request('POST', '/form', ['csrf_token' => 'forged']), $handler);
            $this->fail('Expected a 403');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->expectException(HttpException::class);
        $middleware->process($this->request('DELETE', '/form'), $handler);
    }

    public function testCsrfMiddlewareSkipsApiPathsAndBearerRequests(): void
    {
        $middleware = new CsrfMiddleware(new ArraySession());
        $handler = new CapturingHandler();

        $this->assertSame(200, $middleware->process($this->request('POST', '/api/users'), $handler)->getStatusCode());
        $this->assertSame(200, $middleware->process(
            $this->request('POST', '/form', [], ['Authorization' => 'Bearer x']),
            $handler,
        )->getStatusCode());

        // "/apiary" is not under "/api"
        $this->expectException(HttpException::class);
        $middleware->process($this->request('POST', '/apiary'), $handler);
    }

    public function testCsrfCheckDoesNotConsumeButValidateDoes(): void
    {
        $csrf = new CsrfToken(new ArraySession());
        $token = $csrf->getToken();

        $this->assertTrue($csrf->check($token));
        $this->assertTrue($csrf->check($token));
        $this->assertFalse($csrf->check('nope'));
        $this->assertTrue($csrf->validate($token));
        $this->assertFalse($csrf->check($token));
    }

    public function testUserContextCoercesOddClaimShapes(): void
    {
        $context = UserContext::fromClaims([
            'sub' => 42,
            'name' => ['not', 'a', 'string'],
            'email' => null,
            'entitlements' => 'admin',
            'provider' => ['x'],
        ]);

        $this->assertSame('42', $context->getUser()?->id);
        $this->assertSame('', $context->getUsername());
        $this->assertTrue($context->hasEntitlement('admin'));
        $this->assertNull($context->getProvider());
    }

    public function testConfigurationResolvesEnvironmentPlaceholders(): void
    {
        $file = sys_get_temp_dir() . '/melodic_env_' . uniqid() . '.json';
        file_put_contents($file, json_encode([
            'db' => ['password' => '${MELODIC_TEST_SECRET}', 'host' => '${MELODIC_TEST_UNSET:-localhost}'],
            'literal' => '${MELODIC_TEST_UNSET}',
            'list' => ['x-${MELODIC_TEST_SECRET}'],
        ]));
        putenv('MELODIC_TEST_SECRET=s3cret');

        try {
            $config = new Configuration();
            $config->loadFile($file);
        } finally {
            putenv('MELODIC_TEST_SECRET');
            unlink($file);
        }

        $this->assertSame('s3cret', $config->get('db.password'));
        $this->assertSame('localhost', $config->get('db.host'));
        $this->assertSame('${MELODIC_TEST_UNSET}', $config->get('literal'));
        $this->assertSame(['x-s3cret'], $config->get('list'));
    }

    public function testFileLoggerPrunesOldFiles(): void
    {
        $dir = sys_get_temp_dir() . '/melodic_logs_' . uniqid();
        mkdir($dir);

        foreach (['2020-01-01', '2020-01-02', '2020-01-03'] as $day) {
            file_put_contents("{$dir}/melodic-{$day}.log", "old\n");
        }

        (new FileLogger($dir, LogLevel::DEBUG, maxFiles: 2))->info('hello');

        $remaining = array_map('basename', glob($dir . '/*.log') ?: []);
        sort($remaining);

        $this->assertSame(['melodic-2020-01-03.log', 'melodic-' . date('Y-m-d') . '.log'], $remaining);

        array_map('unlink', glob($dir . '/*') ?: []);
        rmdir($dir);
    }

    public function testNewValidationRules(): void
    {
        $this->assertTrue((new Url())->validate('https://example.com/a?b=1'));
        $this->assertFalse((new Url())->validate('javascript:alert(1)'));
        $this->assertTrue((new Url())->validate(null));

        $this->assertTrue((new Uuid())->validate('3f2504e0-4f89-41d3-9a0c-0305e82c3301'));
        $this->assertFalse((new Uuid())->validate('3f2504e0-4f89'));

        $this->assertTrue((new Date())->validate('2026-02-28'));
        $this->assertFalse((new Date())->validate('2026-02-31'));
        $this->assertTrue((new Date('d/m/Y'))->validate('28/02/2026'));
        $this->assertFalse((new Date())->validate(20260228));
    }

    public function testContainerIsPsr11(): void
    {
        $container = new Container();

        $this->assertInstanceOf(PsrContainer::class, $container);

        try {
            $container->get('No\\Such\\Thing');
            $this->fail('Expected not-found');
        } catch (NotFoundExceptionInterface $e) {
            $this->assertInstanceOf(NotFoundException::class, $e);
        }

        $this->assertFalse($container->isBound(ArrayCache::class));
        $container->bind(ArrayCache::class, ArrayCache::class);
        $this->assertTrue($container->isBound(ArrayCache::class));
    }

    public function testPsrLoggerAdapterForwardsLevelAndContext(): void
    {
        $psr = new class extends AbstractLogger {
            /** @var array<int, array{0: mixed, 1: string, 2: array<mixed>}> */
            public array $records = [];

            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };

        $logger = new PsrLoggerAdapter($psr);
        $logger->warning('careful {x}', ['x' => 1]);
        $logger->log(LogLevel::ERROR, 'boom');

        $this->assertSame(['warning', 'careful {x}', ['x' => 1]], $psr->records[0]);
        $this->assertSame('error', $psr->records[1][0]);
    }

    public function testPsr16CacheAdapterHashesReservedKeys(): void
    {
        $psr = new class implements \Psr\SimpleCache\CacheInterface {
            /** @var array<string, mixed> */
            public array $data = [];

            public function get(string $key, mixed $default = null): mixed { return $this->data[$key] ?? $default; }
            public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool { $this->data[$key] = $value; return true; }
            public function delete(string $key): bool { unset($this->data[$key]); return true; }
            public function clear(): bool { $this->data = []; return true; }
            public function getMultiple(iterable $keys, mixed $default = null): iterable { return []; }
            public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool { return true; }
            public function deleteMultiple(iterable $keys): bool { return true; }
            public function has(string $key): bool { return isset($this->data[$key]); }
        };

        $cache = new Psr16CacheAdapter($psr);
        $cache->set('plain', 1);
        $cache->set('view:home:x', 2);

        $this->assertSame(1, $cache->get('plain'));
        $this->assertSame(2, $cache->get('view:home:x'));
        $this->assertTrue($cache->has('view:home:x'));
        $this->assertArrayHasKey('plain', $psr->data);
        $this->assertArrayNotHasKey('view:home:x', $psr->data);
        $this->assertTrue($cache->delete('view:home:x'));
        $this->assertFalse($cache->has('view:home:x'));
    }
}
