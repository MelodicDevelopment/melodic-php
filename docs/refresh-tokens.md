# Refresh Tokens

Melodic PHP supports secure refresh token rotation for long-lived sessions. The framework provides the model, service logic, middleware, and cookie helper. Your application provides the database repository.

## How It Works

```
Login → Issue JWT (short-lived, ~15min) + Refresh Token (cookie, ~7 days)
         ↓
JWT expires → Client hits /auth/refresh → Middleware validates cookie
         ↓
Controller rotates refresh token → New JWT + new refresh token cookie
         ↓
Old refresh token is revoked — if anyone replays it, the entire family is revoked
```

**Key security properties:**
- Refresh tokens are opaque random strings, never JWTs
- Stored as SHA-256 hashes in the database (the raw value is never persisted)
- Sent only as HTTP-only cookies (never in response bodies, never accessible to JavaScript)
- Rotated on every use — each refresh issues a new token and revokes the old one
- Family-based reuse detection — if a revoked token is presented, all tokens in the family are revoked

---

## Configuration

Add a `refreshToken` section inside `auth` in your `config.json`:

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret",
            "issuer": "my-app",
            "audience": "my-app",
            "tokenLifetime": 900
        },
        "refreshToken": {
            "tokenLifetime": 604800,
            "cookieName": "kingdom_refresh",
            "cookieDomain": ".example.com",
            "cookiePath": "/auth/refresh",
            "cookieSecure": true,
            "cookieSameSite": "Lax"
        },
        "providers": { ... }
    }
}
```

### Settings

| Key | Type | Default | Description |
|---|---|---|---|
| `tokenLifetime` | int | `604800` | Refresh token lifetime in seconds (default: 7 days) |
| `cookieName` | string | `kingdom_refresh` | Name of the HTTP-only cookie |
| `cookieDomain` | string | `""` | Cookie domain (e.g. `.example.com` for subdomains) |
| `cookiePath` | string | `/auth/refresh` | Cookie path — scoped to the refresh endpoint |
| `cookieSecure` | bool | `true` | Require HTTPS (`false` for local development) |
| `cookieSameSite` | string | `Lax` | SameSite attribute (`Lax`, `Strict`, or `None`) |

> **Tip:** For local development, set `cookieSecure` to `false` in `config.dev.json`:
> ```json
> { "auth": { "refreshToken": { "cookieSecure": false } } }
> ```

---

## Database Table

The framework does not create tables. Create this table in your application's database (adjust syntax for your RDBMS):

### SQL Server

```sql
CREATE TABLE RefreshTokens (
    Id          INT IDENTITY(1,1) PRIMARY KEY,
    UserId      INT NOT NULL,
    TokenHash   VARCHAR(64) NOT NULL,
    FamilyId    VARCHAR(36) NOT NULL,
    Generation  INT NOT NULL DEFAULT 1,
    ExpiresAt   DATETIME2 NOT NULL,
    RevokedAt   DATETIME2 NULL,
    CreatedAt   DATETIME2 NOT NULL DEFAULT GETUTCDATE(),

    INDEX IX_RefreshTokens_TokenHash (TokenHash),
    INDEX IX_RefreshTokens_FamilyId (FamilyId),
    INDEX IX_RefreshTokens_UserId (UserId)
);
```

### MySQL

```sql
CREATE TABLE refresh_tokens (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    token_hash  VARCHAR(64) NOT NULL,
    family_id   VARCHAR(36) NOT NULL,
    generation  INT NOT NULL DEFAULT 1,
    expires_at  DATETIME NOT NULL,
    revoked_at  DATETIME NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token_hash (token_hash),
    INDEX idx_family_id (family_id),
    INDEX idx_user_id (user_id)
);
```

---

## Implementing the Repository

Your application must implement `RefreshTokenRepositoryInterface` and bind it in the DI container. The framework never touches your database directly.

### Repository Interface

```php
interface RefreshTokenRepositoryInterface
{
    public function findByTokenHash(string $hash): ?RefreshToken;
    public function findLatestGenerationByFamily(string $familyId): int;
    public function store(RefreshToken $token): void;
    public function revokeByFamily(string $familyId): void;
    public function revokeByUserId(int $userId): void;
    public function deleteExpired(): int;
}
```

### Example Implementation

```php
<?php

declare(strict_types=1);

namespace App\Data\RefreshToken;

use Melodic\Data\DbContextInterface;
use Melodic\Security\RefreshToken;
use Melodic\Security\RefreshTokenRepositoryInterface;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function __construct(
        private readonly DbContextInterface $context,
    ) {
    }

    public function findByTokenHash(string $hash): ?RefreshToken
    {
        $row = $this->context->queryFirst(
            null,
            'SELECT * FROM RefreshTokens WHERE TokenHash = :hash',
            ['hash' => $hash],
        );

        return $row ? $this->hydrate($row) : null;
    }

    public function findLatestGenerationByFamily(string $familyId): int
    {
        return (int) $this->context->scalar(
            'SELECT MAX(Generation) FROM RefreshTokens WHERE FamilyId = :familyId',
            ['familyId' => $familyId],
        );
    }

    public function store(RefreshToken $token): void
    {
        $this->context->command(
            'INSERT INTO RefreshTokens (UserId, TokenHash, FamilyId, Generation, ExpiresAt, RevokedAt, CreatedAt)
             VALUES (:userId, :tokenHash, :familyId, :generation, :expiresAt, :revokedAt, :createdAt)',
            [
                'userId' => $token->userId,
                'tokenHash' => $token->tokenHash,
                'familyId' => $token->familyId,
                'generation' => $token->generation,
                'expiresAt' => $token->expiresAt->format('Y-m-d H:i:s'),
                'revokedAt' => $token->revokedAt?->format('Y-m-d H:i:s'),
                'createdAt' => $token->createdAt->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function revokeByFamily(string $familyId): void
    {
        $this->context->command(
            'UPDATE RefreshTokens SET RevokedAt = :now WHERE FamilyId = :familyId AND RevokedAt IS NULL',
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'familyId' => $familyId],
        );
    }

    public function revokeByUserId(int $userId): void
    {
        $this->context->command(
            'UPDATE RefreshTokens SET RevokedAt = :now WHERE UserId = :userId AND RevokedAt IS NULL',
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'userId' => $userId],
        );
    }

    public function deleteExpired(): int
    {
        return $this->context->command(
            'DELETE FROM RefreshTokens WHERE ExpiresAt < :now',
            ['now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')],
        );
    }

    private function hydrate(object $row): RefreshToken
    {
        return new RefreshToken(
            id: (int) $row->Id,
            userId: (int) $row->UserId,
            tokenHash: $row->TokenHash,
            familyId: $row->FamilyId,
            generation: (int) $row->Generation,
            expiresAt: new \DateTimeImmutable($row->ExpiresAt),
            revokedAt: $row->RevokedAt ? new \DateTimeImmutable($row->RevokedAt) : null,
            createdAt: new \DateTimeImmutable($row->CreatedAt),
        );
    }
}
```

---

## Registering the Repository

Bind your repository in the DI container. The `SecurityServiceProvider` registers `RefreshTokenService` and `RefreshTokenMiddleware` automatically, but they depend on `RefreshTokenRepositoryInterface` being bound by your app.

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Security\RefreshTokenRepositoryInterface;
use App\Data\RefreshToken\RefreshTokenRepository;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(
            RefreshTokenRepositoryInterface::class,
            RefreshTokenRepository::class,
        );
    }
}
```

> **Important:** Register `AppServiceProvider` **before** `SecurityServiceProvider` in your entry point, so the binding is available when the security services are resolved.

---

## Routes and Controller

### Refresh Endpoint

Add a refresh route with `RefreshTokenMiddleware` applied:

```php
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\RefreshTokenMiddleware;

$router->group('/auth', function (Router $router) {
    $router->post('/refresh', AuthController::class, 'refresh');
}, middleware: [RefreshTokenMiddleware::class]);

$router->group('/api', function (Router $router) {
    $router->apiResource('/users', UserController::class);
}, middleware: [ApiAuthenticationMiddleware::class]);
```

### Auth Controller

```php
<?php

declare(strict_types=1);

namespace App\Controllers;

use Melodic\Controller\ApiController;
use Melodic\Http\Response;
use Melodic\Security\RefreshToken;
use Melodic\Security\RefreshTokenConfig;
use Melodic\Security\RefreshTokenCookieHelper;
use Melodic\Security\RefreshTokenService;

class AuthController extends ApiController
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokenService,
        private readonly RefreshTokenConfig $refreshTokenConfig,
        private readonly JwtIssuer $jwtIssuer,  // your app's JWT issuing logic
    ) {
    }

    public function login(): Response
    {
        // Validate credentials (your logic)
        $user = $this->authenticateUser();

        // Issue short-lived JWT
        $jwt = $this->jwtIssuer->issue($user);

        // Create refresh token
        $result = $this->refreshTokenService->create($user->id);

        // Attach refresh token cookie to response
        $response = $this->json(['token' => $jwt]);
        return RefreshTokenCookieHelper::attach($response, $result['token'], $this->refreshTokenConfig);
    }

    public function refresh(): Response
    {
        // RefreshTokenMiddleware already validated the cookie and set attributes
        /** @var RefreshToken $currentToken */
        $currentToken = $this->request->getAttribute('refreshToken');
        $userId = $this->request->getAttribute('refreshTokenUserId');

        // Rotate the refresh token
        $result = $this->refreshTokenService->rotate($currentToken);

        // Issue a new short-lived JWT
        $jwt = $this->jwtIssuer->issueForUserId($userId);

        // Respond with new JWT + new refresh token cookie
        $response = $this->json(['token' => $jwt]);
        return RefreshTokenCookieHelper::attach($response, $result['token'], $this->refreshTokenConfig);
    }

    public function logout(): Response
    {
        // Revoke all refresh tokens for this user
        $userContext = $this->getUserContext();
        if ($userContext?->isAuthenticated()) {
            $this->refreshTokenService->revokeAllForUser((int) $userContext->getUser()->id);
        }

        // Clear the refresh token cookie
        $response = $this->json(['message' => 'Logged out.']);
        return RefreshTokenCookieHelper::clear($response, $this->refreshTokenConfig);
    }
}
```

---

## Token Lifecycle

### 1. Login — Create Tokens

```php
// Issue JWT (short-lived, e.g. 15 minutes)
$jwt = $this->jwtIssuer->issue($user);

// Create refresh token (long-lived, e.g. 7 days)
$result = $this->refreshTokenService->create($user->id);
// $result['token'] → raw opaque token (goes in cookie)
// $result['model'] → RefreshToken object (stored as hash in DB)

// Attach cookie
$response = RefreshTokenCookieHelper::attach($response, $result['token'], $config);
```

### 2. Refresh — Rotate Tokens

```php
// RefreshTokenMiddleware validates the cookie automatically
$currentToken = $request->getAttribute('refreshToken');

// Rotate: revokes old, creates new with incremented generation
$result = $this->refreshTokenService->rotate($currentToken);

// Attach new cookie + issue new JWT
$response = RefreshTokenCookieHelper::attach($response, $result['token'], $config);
```

### 3. Logout — Revoke All

```php
// Revoke all refresh tokens for the user
$this->refreshTokenService->revokeAllForUser($userId);

// Clear the cookie
$response = RefreshTokenCookieHelper::clear($response, $config);
```

### 4. Cleanup — Delete Expired

Run periodically (cron job, scheduled task) to clean up expired tokens:

```php
$deleted = $repository->deleteExpired();
```

---

## Reuse Detection

Refresh tokens use **family-based rotation** to detect token theft:

1. On login, a new **family** (UUID) is created with **generation 1**
2. Each refresh increments the generation and revokes all previous tokens in the family
3. If someone presents a revoked token (e.g. an attacker replaying a stolen token), the entire family is revoked — this also invalidates the legitimate user's current token, forcing re-authentication
4. If a token with a stale generation (lower than the latest) is presented, the entire family is also revoked

This means:
- **Normal use:** Each refresh works once, then the old token is invalid
- **Stolen token replayed:** The family is revoked, both attacker and user must re-authenticate
- **Race condition:** If two requests try to refresh simultaneously, the second one triggers reuse detection

---

## Full Working Example

### 1. Config (`config/config.json`)

```json
{
    "app": { "name": "Kingdom" },
    "database": {
        "dsn": "sqlsrv:Server=localhost;Database=Kingdom"
    },
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "local": {
            "signingKey": "replace-with-a-random-256-bit-key",
            "issuer": "kingdom",
            "audience": "kingdom",
            "tokenLifetime": 900
        },
        "refreshToken": {
            "tokenLifetime": 604800,
            "cookieName": "kingdom_refresh",
            "cookieDomain": "",
            "cookiePath": "/auth/refresh",
            "cookieSecure": true,
            "cookieSameSite": "Lax"
        },
        "providers": {
            "local": {
                "type": "local",
                "label": "Sign in"
            }
        }
    }
}
```

### 2. Repository (`src/Data/RefreshToken/RefreshTokenRepository.php`)

See the [example implementation](#example-implementation) above.

### 3. Service Provider (`src/Providers/AppServiceProvider.php`)

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Security\LocalAuthenticatorInterface;
use Melodic\Security\RefreshTokenRepositoryInterface;
use App\Data\RefreshToken\RefreshTokenRepository;
use App\Security\AppAuthenticator;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(LocalAuthenticatorInterface::class, AppAuthenticator::class);
        $container->bind(RefreshTokenRepositoryInterface::class, RefreshTokenRepository::class);
    }
}
```

### 4. Routes

```php
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\ApiAuthenticationMiddleware;
use Melodic\Security\RefreshTokenMiddleware;

return function (Router $router): void {
    // Auth endpoints
    $router->group('/auth', function (Router $router) {
        $router->get('/login', AuthController::class, 'index');
        $router->get('/login/{provider}', AuthController::class, 'index');
        $router->get('/callback/{provider}', AuthController::class, 'index');
        $router->post('/callback/{provider}', AuthController::class, 'index');
        $router->get('/logout', AuthController::class, 'logout');
    }, middleware: [AuthCallbackMiddleware::class]);

    // Refresh endpoint (only RefreshTokenMiddleware, no JWT auth needed)
    $router->post('/auth/refresh', AuthController::class, 'refresh',
        middleware: [RefreshTokenMiddleware::class],
    );

    // Protected API routes
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
};
```

### 5. Entry Point (`public/index.php`)

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Melodic\Core\Application;
use Melodic\Security\SecurityServiceProvider;
use App\Providers\AppServiceProvider;

$app = new Application(__DIR__ . '/..');
$app->loadConfig('config/config.json');

// Register app provider first (binds RefreshTokenRepositoryInterface)
$app->register(new AppServiceProvider());
$app->register(new SecurityServiceProvider());

$app->routes(require __DIR__ . '/../config/routes.php');

$app->run();
```
