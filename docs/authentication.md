# Authentication

Melodic PHP supports three authentication strategies that can be used individually or combined. All strategies produce the same `UserContext` object and store the session as a JWT in an HTTP cookie.

| Strategy | Use Case | Token Type |
|---|---|---|
| **OIDC** | Enterprise SSO (Okta, Azure AD, Auth0, Google) | Provider-issued JWT validated via JWKS |
| **OAuth2** | Social login (GitHub, Facebook) | Framework-issued JWT (provider token is opaque) |
| **Local** | Username/password against your own user store | Framework-issued JWT |

## Configuration

Authentication is configured in the `auth` section of your `config.json`. A minimal setup with one provider:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "loginPath": "/auth/login",
        "callbackPath": "/auth/callback",
        "postLoginRedirect": "/",
        "cookieName": "melodic_auth",
        "cookieLifetime": 3600,
        "providers": {
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "your-client-id",
                "clientSecret": "your-client-secret",
                "redirectUri": "http://localhost:8080/auth/callback/google",
                "scopes": "openid profile email"
            }
        }
    }
}
```

### Top-Level Settings

| Key | Type | Default | Description |
|---|---|---|---|
| `api.enabled` | bool | `true` | Enable Bearer token authentication for API routes |
| `web.enabled` | bool | `true` | Enable cookie-based authentication for web routes |
| `loginPath` | string | `/auth/login` | Path that renders the login page |
| `callbackPath` | string | `/auth/callback` | Base path for provider callbacks |
| `postLoginRedirect` | string | `/` | Where to redirect after successful login |
| `cookieName` | string | `melodic_auth` | Name of the authentication cookie |
| `cookieLifetime` | int | `3600` | Cookie lifetime in seconds |

### Local JWT Signing Config

When using **OAuth2** or **Local** providers, the framework issues its own JWTs. These require a `local` signing configuration:

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600
        }
    }
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `signingKey` | string | *(required)* | Secret key for signing JWTs (HS256) |
| `issuer` | string | `melodic-app` | Value of the `iss` claim in issued tokens |
| `audience` | string | `melodic-app` | Value of the `aud` claim in issued tokens |
| `tokenLifetime` | int | `3600` | Token expiration in seconds |

> **Important:** The `local` config is required whenever you configure an `oauth2` or `local` type provider. OIDC-only setups do not need it.

---

## OIDC Providers

OIDC providers (Okta, Azure AD, Auth0, Google) use OpenID Connect discovery to automatically fetch authorization endpoints and JWKS keys. The provider issues a JWT that the framework validates directly against the provider's public keys.

### Config

```json
{
    "auth": {
        "providers": {
            "okta": {
                "type": "oidc",
                "label": "Sign in with Okta",
                "discoveryUrl": "https://dev-123456.okta.com/.well-known/openid-configuration",
                "clientId": "0oa...",
                "redirectUri": "http://localhost:8080/auth/callback/okta",
                "audience": "api://default",
                "scopes": "openid profile email"
            }
        }
    }
}
```

### Provider Settings

| Key | Type | Required | Description |
|---|---|---|---|
| `type` | string | yes | Must be `"oidc"` |
| `label` | string | no | Button text on the login page |
| `discoveryUrl` | string | yes | OIDC discovery document URL (`.well-known/openid-configuration`) |
| `clientId` | string | yes | OAuth2 client ID from the provider |
| `clientSecret` | string | no | Client secret (required by some providers like Google) |
| `redirectUri` | string | yes | Must match the callback URL registered with the provider |
| `audience` | string | no | Expected `aud` claim for token validation |
| `scopes` | string | no | Space-separated scopes (defaults to `openid profile email`) |

### Flow

1. User clicks "Sign in with Okta" on the login page
2. Browser redirects to `GET /auth/login/okta`
3. Framework generates PKCE challenge, stores state in session, redirects to Okta's authorization endpoint
4. User authenticates with Okta
5. Okta redirects back to `GET /auth/callback/okta?code=...&state=...`
6. Framework validates state, exchanges code for tokens (including `client_secret` if configured)
7. Framework validates the JWT against Okta's JWKS keys
8. JWT is stored in the `melodic_auth` cookie, user is redirected to `postLoginRedirect`

### Example: Google

```json
{
    "auth": {
        "providers": {
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "123456789.apps.googleusercontent.com",
                "clientSecret": "GOCSPX-...",
                "redirectUri": "http://localhost:8080/auth/callback/google",
                "scopes": "openid profile email"
            }
        }
    }
}
```

Google requires a `clientSecret` for the token exchange. Register `http://localhost:8080/auth/callback/google` as an authorized redirect URI in the Google Cloud Console.

### Example: Azure AD

```json
{
    "auth": {
        "providers": {
            "azure": {
                "type": "oidc",
                "label": "Sign in with Microsoft",
                "discoveryUrl": "https://login.microsoftonline.com/{tenant-id}/v2.0/.well-known/openid-configuration",
                "clientId": "your-application-id",
                "clientSecret": "your-client-secret",
                "redirectUri": "http://localhost:8080/auth/callback/azure",
                "audience": "your-application-id",
                "scopes": "openid profile email"
            }
        }
    }
}
```

Replace `{tenant-id}` with your Azure AD tenant ID. The `audience` is typically the same as the `clientId` for Azure AD.

---

## OAuth2 Providers

OAuth2 providers (GitHub, Facebook) don't support OIDC discovery. Endpoints are configured manually. Since these providers issue opaque access tokens (not JWTs), the framework exchanges the token for user info, then issues its own JWT with the normalized claims.

### Config

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "your-github-client-id",
                "clientSecret": "your-github-client-secret",
                "redirectUri": "http://localhost:8080/auth/callback/github",
                "scopes": "user:email",
                "claimMap": {
                    "sub": "id",
                    "username": "login",
                    "email": "email"
                }
            }
        }
    }
}
```

### Provider Settings

| Key | Type | Required | Description |
|---|---|---|---|
| `type` | string | yes | Must be `"oauth2"` |
| `label` | string | no | Button text on the login page |
| `authorizeUrl` | string | yes | Provider's authorization endpoint |
| `tokenUrl` | string | yes | Provider's token exchange endpoint |
| `userInfoUrl` | string | yes | Endpoint that returns user profile data |
| `clientId` | string | yes | OAuth2 client ID |
| `clientSecret` | string | yes | OAuth2 client secret |
| `redirectUri` | string | yes | Must match the callback URL registered with the provider |
| `scopes` | string | no | Space-separated scopes |
| `claimMap` | object | no | Maps provider's user info fields to standard claims |

### Claim Mapping

Different providers return user data in different formats. The `claimMap` tells the framework how to map the provider's fields to standard claim names:

```json
{
    "claimMap": {
        "sub": "id",
        "username": "login",
        "email": "email"
    }
}
```

This maps GitHub's `id` field to `sub`, `login` to `username`, and `email` to `email`. If no `claimMap` is provided, the framework looks for fields named `sub`, `username` (or `preferred_username`), `email`, and `entitlements` directly.

### Flow

1. User clicks "Sign in with GitHub" on the login page
2. Browser redirects to `GET /auth/login/github`
3. Framework generates state, stores in session, redirects to GitHub's authorization page
4. User authorizes the app on GitHub
5. GitHub redirects back to `GET /auth/callback/github?code=...&state=...`
6. Framework validates state, exchanges code for an opaque access token
7. Framework calls `userInfoUrl` with the access token to fetch user data
8. User data is mapped through `claimMap` to standard claims
9. Framework issues its own JWT (signed with `local.signingKey`) containing the mapped claims
10. JWT is stored in the `melodic_auth` cookie, user is redirected

### Example: Facebook

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret"
        },
        "providers": {
            "facebook": {
                "type": "oauth2",
                "label": "Sign in with Facebook",
                "authorizeUrl": "https://www.facebook.com/v18.0/dialog/oauth",
                "tokenUrl": "https://graph.facebook.com/v18.0/oauth/access_token",
                "userInfoUrl": "https://graph.facebook.com/me?fields=id,name,email",
                "clientId": "your-facebook-app-id",
                "clientSecret": "your-facebook-app-secret",
                "redirectUri": "http://localhost:8080/auth/callback/facebook",
                "scopes": "email public_profile",
                "claimMap": {
                    "sub": "id",
                    "username": "name",
                    "email": "email"
                }
            }
        }
    }
}
```

---

## Local Authentication

Local authentication lets users sign in with a username and password validated against your own user store. You implement the credential checking; the framework handles the JWT, cookie, and login form.

### Config

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "local": {
                "type": "local",
                "label": "Sign in with Email"
            }
        }
    }
}
```

The `local` provider config only needs `type` and optionally `label`. All JWT settings come from the top-level `local` signing config.

### Implementing LocalAuthenticatorInterface

You must provide an implementation of `LocalAuthenticatorInterface` and bind it in the DI container. This is how the framework delegates credential validation to your app:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use Melodic\Security\LocalAuthenticatorInterface;
use Melodic\Security\SecurityException;
use App\Services\UserService;

class AppAuthenticator implements LocalAuthenticatorInterface
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    public function authenticate(string $username, string $password): array
    {
        $user = $this->userService->findByEmail($username);

        if ($user === null || !password_verify($password, $user->passwordHash)) {
            throw new SecurityException('Invalid email or password.');
        }

        // Return claims that will be embedded in the JWT
        return [
            'sub' => (string) $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'entitlements' => $user->roles, // e.g. ['admin', 'editor']
        ];
    }
}
```

### Registering the Authenticator

Bind your implementation in the DI container before the `SecurityServiceProvider` resolves it:

```php
$app->services(function (Container $container) {
    $container->bind(
        LocalAuthenticatorInterface::class,
        AppAuthenticator::class,
    );
});
```

Or register it in a service provider:

```php
class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(
            LocalAuthenticatorInterface::class,
            AppAuthenticator::class,
        );
    }
}
```

### Flow

1. User visits `/auth/login` and sees the login form (username + password fields)
2. User submits the form, which POSTs to `/auth/callback/local`
3. Framework reads `username` and `password` from the POST body
4. Framework calls your `LocalAuthenticatorInterface::authenticate()` method
5. If authentication succeeds, the returned claims are embedded in a JWT signed with `local.signingKey`
6. JWT is stored in the `melodic_auth` cookie, user is redirected
7. If authentication fails (your method throws `SecurityException`), the user is redirected back to the login page with the error message displayed

---

## Combining Multiple Providers

You can configure any combination of providers. Each provider key is its URL slug:

```json
{
    "auth": {
        "local": {
            "signingKey": "your-256-bit-secret",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "okta": {
                "type": "oidc",
                "label": "Sign in with Okta",
                "discoveryUrl": "https://dev-123456.okta.com/.well-known/openid-configuration",
                "clientId": "...",
                "redirectUri": "http://localhost:8080/auth/callback/okta",
                "audience": "api://default",
                "scopes": "openid profile email"
            },
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "...",
                "clientSecret": "...",
                "redirectUri": "http://localhost:8080/auth/callback/google",
                "scopes": "openid profile email"
            },
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "...",
                "clientSecret": "...",
                "redirectUri": "http://localhost:8080/auth/callback/github",
                "scopes": "user:email",
                "claimMap": { "sub": "id", "username": "login", "email": "email" }
            },
            "local": {
                "type": "local",
                "label": "Sign in with Email"
            }
        }
    }
}
```

The login page (`/auth/login`) automatically renders buttons for each external provider and a username/password form for the local provider.

---

## Routes Setup

Register the auth routes in your routes file. The `AuthCallbackMiddleware` intercepts these paths and handles the authentication flows:

```php
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\WebAuthenticationMiddleware;
use Melodic\Security\ApiAuthenticationMiddleware;

return function (Router $router): void {
    // Public routes
    $router->get('/', HomeController::class, 'index');

    // Auth endpoints
    $router->group('/auth', function (Router $router) {
        $router->get('/login', HomeController::class, 'index');
        $router->get('/login/{provider}', HomeController::class, 'index');
        $router->get('/callback/{provider}', HomeController::class, 'index');
        $router->post('/callback/{provider}', HomeController::class, 'index');
        $router->get('/logout', HomeController::class, 'index');
    }, middleware: [AuthCallbackMiddleware::class]);

    // Protected web routes (cookie auth)
    $router->group('/admin', function (Router $router) {
        $router->get('/dashboard', DashboardController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);

    // Protected API routes (Bearer token auth)
    $router->group('/api', function (Router $router) {
        $router->apiResource('/users', UserApiController::class);
    }, middleware: [ApiAuthenticationMiddleware::class]);
};
```

The controller specified in the auth routes (e.g. `HomeController::class, 'index'`) is never actually invoked -- the `AuthCallbackMiddleware` handles all requests to these paths and returns its own responses. The controller is only required because the router needs a controller/action pair for route registration.

### Auth Route Summary

| Method | Path | Handled By | Purpose |
|---|---|---|---|
| GET | `/auth/login` | `AuthCallbackMiddleware` | Render login page with all providers |
| GET | `/auth/login/{provider}` | `AuthCallbackMiddleware` | Redirect to external provider (OIDC/OAuth2) |
| GET | `/auth/callback/{provider}` | `AuthCallbackMiddleware` | Handle OAuth callback (code exchange) |
| POST | `/auth/callback/{provider}` | `AuthCallbackMiddleware` | Handle local login form submission |
| GET | `/auth/logout` | `AuthCallbackMiddleware` | Clear auth cookie, redirect to `/` |

---

## JWT Validation

The `JwtValidator` automatically handles tokens from any configured provider. When validating a token, it:

1. Peeks at the unverified `iss` (issuer) claim in the JWT payload
2. If the issuer matches `local.issuer`, validates with the symmetric signing key
3. Otherwise, tries each OIDC provider's JWKS keys until one succeeds

This means `WebAuthenticationMiddleware` and `ApiAuthenticationMiddleware` work without changes regardless of which provider issued the token.

---

## Accessing Provider Info

After authentication, the `UserContext` includes which provider the user authenticated with:

```php
$userContext = $this->getUserContext();
$provider = $userContext->getProvider(); // "okta", "github", "local", etc.

$user = $userContext->getUser();
$user->id;           // from 'sub' claim
$user->username;     // from 'username' or 'preferred_username' claim
$user->email;        // from 'email' claim
$user->entitlements; // from 'entitlements' claim
```

---

## Full Working Example

### 1. Config (`config/config.json`)

```json
{
    "app": { "name": "My App" },
    "database": {
        "dsn": "sqlite:data/app.db"
    },
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "local": {
            "signingKey": "replace-with-a-random-256-bit-key",
            "issuer": "my-app",
            "audience": "my-app",
            "tokenLifetime": 3600
        },
        "providers": {
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "Iv1.abc123",
                "clientSecret": "secret123",
                "redirectUri": "http://localhost:8080/auth/callback/github",
                "scopes": "user:email",
                "claimMap": { "sub": "id", "username": "login", "email": "email" }
            },
            "local": {
                "type": "local",
                "label": "Sign in with Email"
            }
        }
    }
}
```

### 2. Authenticator (`src/Security/AppAuthenticator.php`)

```php
<?php

declare(strict_types=1);

namespace App\Security;

use Melodic\Security\LocalAuthenticatorInterface;
use Melodic\Security\SecurityException;

class AppAuthenticator implements LocalAuthenticatorInterface
{
    public function authenticate(string $username, string $password): array
    {
        // Replace with your actual user lookup and password verification
        if ($username === 'admin@example.com' && $password === 'password') {
            return [
                'sub' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'entitlements' => ['admin'],
            ];
        }

        throw new SecurityException('Invalid credentials.');
    }
}
```

### 3. Service Provider (`src/Providers/AppServiceProvider.php`)

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Melodic\DI\Container;
use Melodic\DI\ServiceProvider;
use Melodic\Security\LocalAuthenticatorInterface;
use App\Security\AppAuthenticator;

class AppServiceProvider extends ServiceProvider
{
    public function register(Container $container): void
    {
        $container->bind(LocalAuthenticatorInterface::class, AppAuthenticator::class);
    }
}
```

### 4. Routes (`config/routes.php`)

```php
<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\DashboardController;
use Melodic\Routing\Router;
use Melodic\Security\AuthCallbackMiddleware;
use Melodic\Security\WebAuthenticationMiddleware;

return function (Router $router): void {
    $router->get('/', HomeController::class, 'index');

    $router->group('/auth', function (Router $router) {
        $router->get('/login', HomeController::class, 'index');
        $router->get('/login/{provider}', HomeController::class, 'index');
        $router->get('/callback/{provider}', HomeController::class, 'index');
        $router->post('/callback/{provider}', HomeController::class, 'index');
        $router->get('/logout', HomeController::class, 'index');
    }, middleware: [AuthCallbackMiddleware::class]);

    $router->group('/admin', function (Router $router) {
        $router->get('/dashboard', DashboardController::class, 'index');
    }, middleware: [WebAuthenticationMiddleware::class]);
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

$app->register(new AppServiceProvider());
$app->register(new SecurityServiceProvider());

$app->routes(require __DIR__ . '/../config/routes.php');

$app->run();
```

### 6. Verify

```bash
php -S localhost:8080 -t public
```

1. Visit `http://localhost:8080/auth/login` -- login page shows "Sign in with GitHub" button and email/password form
2. Submit `admin@example.com` / `password` -- redirects to `/` with auth cookie set
3. Visit `http://localhost:8080/admin/dashboard` -- passes through (cookie is valid)
4. Visit `http://localhost:8080/auth/logout` -- clears cookie, redirects to `/`
