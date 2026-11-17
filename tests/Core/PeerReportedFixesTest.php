<?php

declare(strict_types=1);

namespace Tests\Core;

use Melodic\Console\Make\Stub;
use Melodic\Controller\ApiController;
use Melodic\Core\Application;
use Melodic\Core\Configuration;
use Melodic\Data\DbContext;
use Melodic\Data\ListOf;
use Melodic\Data\Model;
use Melodic\Data\ModelBindingException;
use Melodic\Error\ExceptionHandler;
use Melodic\Http\JsonResponse;
use Melodic\Http\Middleware\CorsMiddleware;
use Melodic\Http\Middleware\SecurityHeadersMiddleware;
use Melodic\Http\Request;
use Melodic\Log\LogLevel;
use Melodic\Log\NullLogger;
use Melodic\Routing\Router;
use Melodic\Security\RefreshToken;
use Melodic\Security\RefreshTokenConfig;
use Melodic\Security\RefreshTokenRepositoryInterface;
use Melodic\Security\RefreshTokenReuseException;
use Melodic\Security\RefreshTokenService;
use Melodic\Validation\Rules\Required;
use Melodic\Validation\ValidationException;
use Melodic\Validation\ValidationResult;
use Melodic\Validation\Validator;
use PDO;
use PHPUnit\Framework\TestCase;

class PeerLine extends Model
{
    #[Required]
    public ?string $Sku = null;
    public ?int $Quantity = null;
}

class PeerOrder extends Model
{
    /** @var PeerLine[] */
    #[ListOf(PeerLine::class)]
    public array $Lines = [];
}

class PeerController extends ApiController
{
    public function invalid(): JsonResponse
    {
        throw new ValidationException(ValidationResult::failure(['Name' => ['This field is required']]));
    }
}

/** Refresh-token repository backed by a real table, so rollbacks are real. */
class SqliteRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(private readonly DbContext $db) {}

    public function findByTokenHash(string $hash): ?RefreshToken
    {
        $row = $this->db->queryFirst(\stdClass::class, 'SELECT * FROM tokens WHERE hash = :h', ['h' => $hash]);

        return $row === null ? null : new RefreshToken(
            (int) $row->id, (int) $row->user_id, $row->hash, $row->family, (int) $row->generation,
            new \DateTimeImmutable($row->expires_at),
            $row->revoked_at !== null ? new \DateTimeImmutable($row->revoked_at) : null,
            new \DateTimeImmutable($row->created_at),
        );
    }

    public function findLatestGenerationByFamily(string $familyId): int
    {
        return (int) $this->db->scalar('SELECT MAX(generation) FROM tokens WHERE family = :f', ['f' => $familyId]);
    }

    public function store(RefreshToken $token): void
    {
        $this->db->command(
            'INSERT INTO tokens (user_id, hash, family, generation, expires_at, created_at) VALUES (:u, :h, :f, :g, :e, :c)',
            ['u' => $token->userId, 'h' => $token->tokenHash, 'f' => $token->familyId, 'g' => $token->generation,
             'e' => $token->expiresAt, 'c' => $token->createdAt],
        );
    }

    public function revokeByFamily(string $familyId): void
    {
        $this->db->command(
            "UPDATE tokens SET revoked_at = '2026-01-01 00:00:00' WHERE family = :f AND revoked_at IS NULL",
            ['f' => $familyId],
        );
    }

    public function revokeByUserId(int $userId): void {}

    public function deleteExpired(): int { return 0; }
}

class PeerReportedFixesTest extends TestCase
{
    public function testRefreshTokenReuseRevocationSurvivesTransactionRollback(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE tokens (id INTEGER PRIMARY KEY, user_id INTEGER, hash TEXT, family TEXT, generation INTEGER, expires_at TEXT, revoked_at TEXT, created_at TEXT)');
        $db = new DbContext($pdo);
        $service = new RefreshTokenService(new SqliteRefreshTokenRepository($db), new RefreshTokenConfig(), $db);

        $first = $service->create(1);
        $second = $service->validateAndRotate($first['token']);

        // Replaying the already-rotated token is reuse: the whole family
        // (including the live successor) must end up revoked.
        try {
            $service->validateAndRotate($first['token']);
            $this->fail('Expected reuse detection');
        } catch (RefreshTokenReuseException $e) {
            $this->assertSame($first['model']->familyId, $e->familyId);
        }

        $live = (int) $pdo->query('SELECT COUNT(*) FROM tokens WHERE revoked_at IS NULL')->fetchColumn();
        $this->assertSame(0, $live, 'the family must stay revoked after the rollback');

        $this->expectException(RefreshTokenReuseException::class);
        $service->validateAndRotate($second['token']);
    }

    public function testErrorResponsesCarryCorsAndSecurityHeaders(): void
    {
        $app = new Application(sys_get_temp_dir());
        $app->addMiddleware(new CorsMiddleware(['allowedOrigins' => ['https://app.test']]));
        $app->addMiddleware(new SecurityHeadersMiddleware());
        $app->routes(fn(Router $r) => $r->get('/invalid', PeerController::class, 'invalid'));

        $request = fn(string $uri) => new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $uri],
            query: [], body: [],
            headers: ['Origin' => 'https://app.test', 'Accept' => 'application/json'],
            cookies: [], files: [],
        );

        $notFound = $app->handle($request('/missing'));
        $this->assertSame(404, $notFound->getStatusCode());
        $this->assertSame('https://app.test', $notFound->getHeader('Access-Control-Allow-Origin'));
        $this->assertSame('nosniff', $notFound->getHeader('X-Content-Type-Options'));

        // ValidationException thrown by application code → 400 with field errors
        $invalid = $app->handle($request('/invalid'));
        $this->assertSame(400, $invalid->getStatusCode());
        $this->assertSame('{"Name":["This field is required"]}', $invalid->getBody());
        $this->assertSame('https://app.test', $invalid->getHeader('Access-Control-Allow-Origin'));
    }

    public function testListOfHydratesAndValidatesElements(): void
    {
        $order = PeerOrder::fromArray(['lines' => [['sku' => 'A', 'quantity' => '2'], ['quantity' => 1]]]);

        $this->assertInstanceOf(PeerLine::class, $order->Lines[0]);
        $this->assertSame(2, $order->Lines[0]->Quantity);

        $result = (new Validator())->validate($order);
        $this->assertSame(['Lines.1.Sku'], array_keys($result->errors));

        try {
            PeerOrder::fromArray(['lines' => [['quantity' => 'x']]]);
            $this->fail('Expected a binding error');
        } catch (ModelBindingException $e) {
            $this->assertSame('lines.0.quantity', $e->field);
        }
    }

    public function testMappedExceptionIsLoggedWithMappedStatus(): void
    {
        $logger = new class extends NullLogger {
            /** @var string[] */
            public array $levels = [];

            public function log(LogLevel $level, string $message, array $context = []): void
            {
                $this->levels[] = $level->value . ':' . $context['status'];
            }

            public function error(string $message, array $context = []): void { $this->log(LogLevel::ERROR, $message, $context); }
            public function warning(string $message, array $context = []): void { $this->log(LogLevel::WARNING, $message, $context); }
        };

        $handler = new ExceptionHandler($logger);
        $handler->registerMapper(\DomainException::class, fn() => new JsonResponse(['error' => 'conflict'], 409));

        $response = $handler->handle(new \DomainException('dup'), new Request(
            server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], query: [], body: [], headers: [], cookies: [], files: [],
        ));

        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(['warning:409'], $logger->levels);
    }

    public function testEnvPlaceholderDefaultsAndBooleans(): void
    {
        $file = sys_get_temp_dir() . '/melodic_env2_' . uniqid() . '.json';
        file_put_contents($file, json_encode([
            'empty' => '${MELODIC_T_EMPTY:-fallback}',
            'debug' => '${MELODIC_T_BOOL}',
            'embedded' => 'x-${MELODIC_T_BOOL}',
        ]));
        putenv('MELODIC_T_EMPTY=');
        putenv('MELODIC_T_BOOL=false');

        try {
            $config = new Configuration();
            $config->loadFile($file);
        } finally {
            putenv('MELODIC_T_EMPTY');
            putenv('MELODIC_T_BOOL');
            unlink($file);
        }

        $this->assertSame('fallback', $config->get('empty'));
        $this->assertFalse($config->get('debug'));
        $this->assertSame('x-false', $config->get('embedded'));
    }

    public function testPascalCaseKeepsInnerCapitals(): void
    {
        $this->assertSame('BlogPost', Stub::pascalCase('BlogPost'));
        $this->assertSame('BlogPost', Stub::pascalCase('blog_post'));
        $this->assertSame('User', Stub::pascalCase('USER'));
    }
}
