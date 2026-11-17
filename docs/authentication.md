# Authentication

Melodic PHP supports three authentication strategies that can be used individually or combined. All strategies produce the same `UserContext` object and store the session as a JWT in an HTTP cookie.

| Strategy | Use Case | Token Type |
|---|---|---|
| **OIDC** | Enterprise SSO (Okta, Microsoft Entra, Auth0, Google) | Provider-issued `id_token` validated via JWKS |
| **OAuth2** | Social login (GitHub, Facebook) | Framework-issued JWT (provider token is opaque) |
| **Local** | Username/password against your own user store | Framework-issued JWT |

The auth services are registered by `Melodic\Security\SecurityServiceProvider` — add `$app->register(new SecurityServiceProvider())` to your entry point (see the [full example](#full-working-example)).

## Configuration

Authentication is configured in the `auth` section of your `config/config.json`. A minimal setup with one provider:

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

Keep secrets out of the JSON files with environment placeholders (since 4.1): a string value may contain `${VAR}` or `${VAR:-default}`, resolved when the file is loaded. The default applies when the variable is unset or empty, and a value that is exactly one placeholder resolving to `true` or `false` becomes a real boolean:

```json
{
    "auth": {
        "cookieSecure": "${AUTH_COOKIE_SECURE:-true}",
        "local": { "signingKey": "${AUTH_SIGNING_KEY}" }
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
| `cookieLifetime` | int | `3600` | Cookie lifetime in seconds. Independent of the JWT's own `exp` — an expired token in a still-live cookie is rejected |
| `cookieSecure` | bool | `true` | Send the auth cookie over HTTPS only |
| `cookieSameSite` | string | `Lax` | `SameSite` attribute of the auth cookie |
| `cookiePath` | string | `/` | Path of the auth cookie |
| `cookieDomain` | string | `""` | Domain of the auth cookie (empty = current host) |
| `oidcCacheDir` | string | `{sys temp dir}/melodic_oidc_cache` | Where OIDC discovery documents and JWKS are cached |
| `loginPage` | object | — | Branding for the built-in login page (see [Customizing the Login Page](#customizing-the-login-page)) |

When `api.enabled` (or `web.enabled`) is `false`, the corresponding middleware does not authenticate at all: it attaches an anonymous `UserContext` and lets every request through.

The auth cookie is always `HttpOnly`.

### Cookies and Local Development

The auth cookie and the PHP session cookie are **`Secure` by default**. The session cookie matters too: the login flow keeps the OAuth `state`, PKCE verifier, OIDC `nonce` and the CSRF token in the session. Over plain HTTP a `Secure` cookie is not sent back, so login appears not to work (typical symptoms: `Invalid OAuth state parameter.` or `Invalid or expired form submission.`). Some browsers exempt `localhost`; don't rely on it.

For plain-HTTP local development, turn the flag off for **both** cookies in `config/config.dev.json` — the gitignored local override file that `$app->loadEnvironmentConfig()` loads after `config.json` and `config.{APP_ENV}.json`:

```json
{
    "auth":    { "cookieSecure": false },
    "session": { "cookieSecure": false }
}
```

The session cookie is configured in a top-level `session` section, with the same four keys and defaults as the auth cookie:

| Key | Type | Default | Description |
|---|---|---|---|
| `session.cookieSecure` | bool | `true` | Send the session cookie over HTTPS only |
| `session.cookieSameSite` | string | `Lax` | `SameSite` attribute of the session cookie |
| `session.cookiePath` | string | `/` | Path of the session cookie |
| `session.cookieDomain` | string | `""` | Domain of the session cookie |

The session cookie is always `HttpOnly`, and the session is started with PHP's `session.use_strict_mode` and `session.use_only_cookies` enabled (since 4.1), so a session ID the server did not issue is never adopted.

Never disable `cookieSecure` in production.

### Local JWT Signing Config

When using **OAuth2** or **Local** providers, the framework issues its own JWTs. These require a `local` signing configuration:

```json
{
    "auth": {
        "local": {
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long",
            "issuer": "melodic-app",
            "audience": "melodic-app",
            "tokenLifetime": 3600
        }
    }
}
```

| Key | Type | Default | Description |
|---|---|---|---|
| `signingKey` | string | *(required)* | Secret key for signing JWTs. Must be at least 32 characters for `HS*` algorithms |
| `issuer` | string | `melodic-app` | Value of the `iss` claim in issued tokens |
| `audience` | string | `melodic-app` | Value of the `aud` claim in issued tokens |
| `tokenLifetime` | int | `3600` | Token expiration in seconds |
| `algorithm` | string | `HS256` | JWT signing algorithm |

Generate a signing key with `openssl rand -base64 48`. An empty or short (under 32 characters) key for an `HS*` algorithm makes `LocalAuthConfig` throw an `InvalidArgumentException` — the app answers 500 on every request that touches authentication until it is fixed.

> **Important:** The `local` config is required whenever you configure an `oauth2` or `local` type provider — without it the provider registry throws a `SecurityException`. OIDC-only setups do not need it.

---

## OIDC Providers

OIDC providers (Okta, Microsoft Entra, Auth0, Google) use OpenID Connect discovery to automatically fetch authorization endpoints and JWKS keys. The provider issues an `id_token` (a JWT) that the framework validates directly against the provider's public keys and stores in the auth cookie.

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
                "clientSecret": "your-client-secret",
                "redirectUri": "http://localhost:8080/auth/callback/okta",
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
| `clientSecret` | string | no | Client secret, sent in the token exchange when set (required by confidential "web" clients — Google, Entra, Okta web apps) |
| `redirectUri` | string | yes | Must match the callback URL registered with the provider |
| `audience` | string | no | Expected `aud` claim. Defaults to `clientId`, so the audience check is never skipped. When set, it is also sent as an `audience` parameter on the authorization request |
| `scopes` | string | no | Space-separated scopes (defaults to `openid profile email`). Must include `openid`, or the provider returns no `id_token` |
| `signingAlg` | string | no | Algorithm assumed for JWKS keys that carry no `alg` of their own (default `RS256`). Microsoft Entra needs this default |
| `claimMap` | object | no | Maps the provider's claim names onto the standard ones (since 4.1) — see below |

> **Leave `audience` unset for browser login.** The `id_token` a provider returns at login has the client ID as its `aud`. The same `audience` value is used to validate that `id_token` *and* any Bearer token presented to the API, so setting it to an API identifier (e.g. Okta's `api://default`) makes the login callback fail with `Invalid token audience.`

The user is built from the token's `sub`, `username` (falling back to `preferred_username`, then `name`), `email` and `entitlements` claims. When the provider uses other names, add a `claimMap` in the same `"standard name": "provider claim"` form as for [OAuth2 providers](#claim-mapping). The mapped values are copied onto `sub`, `username`, `email` and `entitlements`; the original claims stay in place and remain available through `getClaim()`. For example, to turn Entra app roles into entitlements:

```json
{ "claimMap": { "entitlements": "roles" } }
```

Without a `claimMap` the claims are used exactly as issued, so entitlements must arrive in a claim literally named `entitlements`.

### Flow

1. User clicks "Sign in with Okta" on the login page
2. Browser redirects to `GET /auth/login/okta`
3. Framework generates a `state`, a PKCE verifier (S256 challenge) and a `nonce`, stores them in the session, and redirects to Okta's authorization endpoint
4. User authenticates with Okta
5. Okta redirects back to `GET /auth/callback/okta?code=...&state=...`
6. Framework validates state, exchanges code for tokens (including `client_secret` if configured)
7. Framework takes the `id_token` from the token response and validates it: signature against Okta's JWKS keys, `exp` present and not expired, `iss` equal to the issuer in the discovery document, `aud`, and `nonce` equal to the one sent in step 3
8. The `id_token` is stored in the `melodic_auth` cookie, user is redirected to the page they originally requested (or `postLoginRedirect`)

If any step fails, the user is redirected back to the login page with the error shown.

### Validation Rules

- **`id_token` required.** The callback only accepts an `id_token`; there is no fallback to the `access_token`. A provider that returns only an access token is rejected with `No id_token received from authorization server.` — use the `oauth2` provider type for those.
- **Issuer.** The token's `iss` must equal the discovery document's `issuer` exactly, and that issuer's host must match the host of `discoveryUrl`.
- **Expiry.** A token without an `exp` claim is rejected.
- **Audience.** `aud` must equal (or contain) `audience`, or `clientId` when no audience is configured.
- **Nonce.** The `id_token` must carry the `nonce` generated for this login attempt.

### Discovery Cache

Discovery documents and JWKS are cached on disk for one hour, in a per-provider subdirectory of `auth.oidcCacheDir`. The default is `{sys temp dir}/melodic_oidc_cache`; the directory is created with mode `0700` and cache files are written atomically with mode `0600`. On shared hosting, point it at app-owned storage:

```json
{ "auth": { "oidcCacheDir": "/var/www/my-app/storage/cache/oidc" } }
```

Use an absolute path: a relative one is resolved against PHP's working directory, not the application base path. Delete the directory to force a fresh fetch (for example after you change tenants).

Since 4.1:

- **Key rotation.** A token signed with a key ID the cached JWKS does not contain triggers a JWKS refetch (at most once a minute), so a provider rotating its keys does not have to wait for the hourly expiry.
- **Provider outages.** If the provider cannot be reached, the last cached copy keeps being served and the fetch is retried a minute later, instead of failing every request.
- **Ownership check.** A cache directory under the system temp directory (the default) must be owned by the PHP process user and is tightened to `0700`; one owned by another user is refused with a `SecurityException` telling you to set `auth.oidcCacheDir`. A directory configured elsewhere is trusted as deployed.

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

### Example: Microsoft Entra ID (Azure AD)

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

Replace `{tenant-id}` with your Entra tenant ID. Use the tenant-specific URL — the `/common/` and `/organizations/` endpoints advertise a templated issuer that never matches a real token's `iss`. The `audience` equals the `clientId` here, which is also the default, so it can be omitted.

---

## OAuth2 Providers

OAuth2 providers (GitHub, Facebook) don't support OIDC discovery. Endpoints are configured manually. Since these providers issue opaque access tokens (not JWTs), the framework exchanges the token for user info, then issues its own JWT with the normalized claims. That JWT is signed with the [`local` signing config](#local-jwt-signing-config), which is therefore required.

### Config

```json
{
    "auth": {
        "local": {
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long",
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

Only these four claims are carried into the issued JWT (`entitlements` can be mapped the same way); any other field in the provider's user-info response is dropped. A missing field becomes an empty string (or an empty list for `entitlements`).

### Flow

1. User clicks "Sign in with GitHub" on the login page
2. Browser redirects to `GET /auth/login/github`
3. Framework generates a `state` and a PKCE verifier, stores them in the session, and redirects to GitHub's authorization page with `response_type=code` and an S256 `code_challenge`
4. User authorizes the app on GitHub
5. GitHub redirects back to `GET /auth/callback/github?code=...&state=...`
6. Framework validates state, exchanges code (with `client_secret` and the PKCE `code_verifier`) for an opaque access token
7. Framework calls `userInfoUrl` with the access token (as a `Bearer` header) to fetch user data
8. User data is mapped through `claimMap` to standard claims
9. Framework issues its own JWT (signed with `local.signingKey`) containing `iss`, `aud`, `iat`, `exp`, the mapped claims and `provider`
10. JWT is stored in the `melodic_auth` cookie, user is redirected

Token and user-info requests are made over verified TLS; a 4xx/5xx status, a non-JSON body, or a body carrying an OAuth `error` fails the login.

### Example: Facebook

```json
{
    "auth": {
        "local": {
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long"
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
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long",
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

Bind your implementation in the DI container. The binding is resolved lazily, on the first request that needs it, so it does not matter whether it is registered before or after `SecurityServiceProvider`:

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

1. User visits `/auth/login` and sees the login form (username + password fields, plus a hidden `csrf_token`)
2. User submits the form, which POSTs to `/auth/callback/local`
3. Framework checks the `csrf_token` field against the session; a missing or wrong token redirects back to the login page, which shows `Invalid or expired form submission. Please try again.`
4. Framework reads `username` and `password` from the POST body
5. Framework calls your `LocalAuthenticatorInterface::authenticate()` method
6. If authentication succeeds, the returned claims are embedded in a JWT signed with `local.signingKey`
7. JWT is stored in the `melodic_auth` cookie, user is redirected
8. If authentication fails (your method throws `SecurityException`), the user is redirected back to the login page with the exception message displayed — keep that message safe to show to users

The login endpoint consumes the CSRF token when it accepts it, and a new one is issued on the next page render. A mismatched token is *not* consumed, so a forged POST cannot invalidate the form the user has open. If you build your own login form, include the token as a `csrf_token` field (see [CSRF Protection](#csrf-protection)).

To slow down password guessing, put a `RateLimitMiddleware` (since 4.1) in front of the callback path — it needs a `CacheInterface` implementation:

```php
// 10 attempts per minute per client address
$app->addMiddleware(new RateLimitMiddleware($cache, 10, 60, paths: ['/auth/callback']));
```

---

## Combining Multiple Providers

You can configure any combination of providers. Each provider key is its URL slug:

```json
{
    "auth": {
        "local": {
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long",
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
                "clientSecret": "...",
                "redirectUri": "http://localhost:8080/auth/callback/okta",
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

### Customizing the Login Page

The built-in page is branded through `auth.loginPage`:

| Key | Default | Description |
|---|---|---|
| `title` | `Sign In` | Page title and heading |
| `primaryColor` | `#4a90d9` | Submit button and focus color |
| `primaryHoverColor` | `#357abd` | Submit button hover color |
| `backgroundColor` | `#f5f5f5` | Page background |
| `cardBackground` | `#ffffff` | Login card background |
| `textColor` | `#333333` | Heading and button text |
| `subtextColor` | `#555555` | Field label text |
| `logoUrl` | *(none)* | Logo image shown above the heading |
| `logoAlt` | `title` | Alt text for the logo |
| `faviconUrl` | *(none)* | Favicon |
| `customCss` | *(none)* | Extra CSS appended in its own `<style>` block. Must not contain the sequence `</` — the renderer throws a `SecurityException` if it does |

To replace the page entirely, bind your own `AuthLoginRendererInterface` implementation **after** registering `SecurityServiceProvider` (which binds the default renderer). `render(?string $error, ?string $csrfToken)` returns the full HTML; include the `$csrfToken` as a hidden `csrf_token` field in the local login form.

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
        $router->post('/logout', HomeController::class, 'index');
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

Route middleware is given as class names and resolved from the DI container; `SecurityServiceProvider` registers `AuthCallbackMiddleware`, `WebAuthenticationMiddleware` and `ApiAuthenticationMiddleware` there.

Alternatively, add `AuthCallbackMiddleware` as global middleware and skip the placeholder routes — it passes every request that is not an auth path straight through:

```php
$app->register(new SecurityServiceProvider());
$app->addMiddleware($app->getContainer()->get(AuthCallbackMiddleware::class));
```

### Auth Route Summary

| Method | Path | Handled By | Purpose |
|---|---|---|---|
| GET | `/auth/login` | `AuthCallbackMiddleware` | Render login page with all providers |
| GET | `/auth/login/{provider}` | `AuthCallbackMiddleware` | Redirect to external provider (OIDC/OAuth2) |
| GET | `/auth/callback/{provider}` | `AuthCallbackMiddleware` | Handle OAuth callback (code exchange) |
| POST | `/auth/callback/{provider}` | `AuthCallbackMiddleware` | Handle local login form submission (requires `csrf_token`) |
| POST | `/auth/logout` | `AuthCallbackMiddleware` | End the session, clear the auth cookie, redirect to `/` (requires `csrf_token`) |

The login and callback paths follow `loginPath` and `callbackPath`. The logout path is fixed at `/auth/logout`, and logout always redirects to `/`. An unknown `{provider}` produces a 401.

### Logout

Logout is a state-changing action, so it is a **CSRF-protected `POST`**:

- Any other method on `/auth/logout` returns **405** (with `Allow: POST`).
- A `POST` without a valid `csrf_token` field returns **403**.
- A valid `POST` clears the auth cookie, destroys the PHP session (and expires its cookie), clears the [refresh-token](refresh-tokens.md) cookie, and redirects to `/`.

Render a small form instead of a logout link:

```html
<form method="post" action="/auth/logout">
    <input type="hidden" name="csrf_token" value="<?= $this->e($csrfToken) ?>">
    <button type="submit">Log out</button>
</form>
```

Where `$csrfToken` comes from is covered in the next section.

Logout does not sign the user out of the identity provider and does not revoke stored refresh tokens — call `RefreshTokenService::revokeAllForUser()` yourself for that. (Destroying the session and clearing the refresh cookie are new in 4.1; 4.0 only cleared the auth cookie.)

### CSRF Protection

The login form and the logout endpoint share one CSRF token, stored in the session and managed by `CsrfToken`:

| Method | Behaviour |
|---|---|
| `getToken()` | Returns the session's current token, creating one if there is none. Use it when rendering forms |
| `check($token)` | Compares without consuming the token (since 4.1) |
| `validate($token)` | Compares and, on a match, consumes the token. A mismatch leaves the stored token intact |

The login and logout endpoints use `validate()`, so the token is replaced once one of them accepts it; a form rendered before that needs a fresh page load.

**With `CsrfMiddleware` (since 4.1).** `CsrfMiddleware` extends the same protection to every cookie-authenticated form. `SecurityServiceProvider` registers it; add it globally (or to a route group):

```php
$app->addMiddleware($app->getContainer()->get(CsrfMiddleware::class));
```

- Every request other than `GET`, `HEAD` or `OPTIONS` must carry the session's token, as a `csrf_token` body field or an `X-CSRF-Token` header; otherwise it gets a **403** (`Invalid or missing CSRF token.`). The token is not consumed, so it keeps working across tabs and forms.
- Not checked: paths under the `csrf.exceptPaths` config key (a list of path prefixes, default `["/api"]`) and requests carrying an `Authorization: Bearer` header.
- The token is exposed as the `csrfToken` request attribute, and `MvcController::view()` passes `$csrfToken` and a ready-made `$csrfField` hidden input to every template — so the logout form above can simply contain `<?= $csrfField ?>`.

**Without it.** Inject `CsrfToken` and pass the token to the view yourself:

```php
use Melodic\Controller\MvcController;
use Melodic\Http\Response;
use Melodic\Security\CsrfToken;
use Melodic\View\ViewEngine;

class DashboardController extends MvcController
{
    public function __construct(
        ViewEngine $viewEngine,
        private readonly CsrfToken $csrf,
    ) {
        parent::__construct($viewEngine);
    }

    public function index(): Response
    {
        return $this->view('dashboard/index', ['csrfToken' => $this->csrf->getToken()]);
    }
}
```

`CsrfToken` is built on a `SessionInterface`. `SecurityServiceProvider` binds that interface to the auth `SessionManager` unless your application has already bound it (a `SessionServiceProvider` binding takes precedence), so `CsrfToken` auto-wires with only the security provider registered.

---

## Authentication Middleware

| Middleware | Reads | On success | On failure |
|---|---|---|---|
| `ApiAuthenticationMiddleware` | `Authorization: Bearer <jwt>` header | Sets the `userContext` request attribute | **401** JSON: `Authentication required.` (no token) or `Authentication failed.` (invalid token) |
| `WebAuthenticationMiddleware` | Auth cookie | Sets the `userContext` request attribute | Remembers the requested URL in the session and redirects to `loginPath` |
| `OptionalWebAuthMiddleware` | Auth cookie | Sets the `userContext` request attribute | Sets an anonymous `userContext` and continues |

- `ApiAuthenticationMiddleware` never reveals *why* a token was rejected (bad signature, wrong audience, expired, ...). When a `LoggerInterface` is bound in the container, the real reason is logged as a warning (`API token rejected: {reason}`).
- `WebAuthenticationMiddleware` only remembers local paths (a single leading `/`, not `//` or `/\`) as the post-login target, and the callback re-checks the stored value — anything else falls back to `postLoginRedirect`. The query string is kept (since 4.1), so the user lands back on the same page.
- `WebAuthenticationMiddleware` only turns a *bad token* into a login redirect. A `SecurityException` thrown by your own controller or a later middleware reaches the error handler (since 4.1) instead of being mistaken for one.
- `OptionalWebAuthMiddleware` is for pages that render for everyone but show extra content to signed-in users. It is not pre-registered by `SecurityServiceProvider`; the container auto-wires it.

---

## Authorization

`AuthorizationMiddleware` checks the `userContext` set by one of the authentication middleware above, so it must run after it:

```php
new AuthorizationMiddleware(array $requiredEntitlements = [], bool $requireAuthentication = true)
```

- No authenticated user → **401** `Authentication required.`
- Authenticated but holding none of the required entitlements → **403** `Insufficient permissions.` (holding *any one* of them is enough)
- Entitlements are enforced even when `requireAuthentication` is `false`: an anonymous request to a route that requires entitlements gets a 401.

Both responses are JSON, whatever kind of route they protect.

Route middleware is resolved from the container by name, so `AuthorizationMiddleware::class` on a route always gets the default constructor arguments — on its own it requires authentication but no entitlements. Declare the required entitlements as a route (or group) attribute named `entitlements` (since 4.1); the user needs at least one of them:

```php
$router->group('/api', function (Router $router) {
    $router->apiResource('/users', UserApiController::class);
    $router->delete('/users/{id}/purge', UserApiController::class, 'purge',
        middleware: [AuthorizationMiddleware::class],
        attributes: ['entitlements' => ['admin']],
    );
}, middleware: [ApiAuthenticationMiddleware::class]);
```

Entitlements passed to the constructor are combined with the route's. To use constructor arguments on a route, bind a configured instance under a name of your choosing and use that name:

```php
$app->services(function (Container $container) {
    $container->bind('auth.admin', fn() => new AuthorizationMiddleware(['admin']));
});

$router->group('/api', function (Router $router) {
    $router->apiResource('/users', UserApiController::class);
    $router->delete('/users/{id}/purge', UserApiController::class, 'purge', middleware: ['auth.admin']);
}, middleware: [ApiAuthenticationMiddleware::class]);
```

A subclass that fixes the entitlements in its constructor works too (`middleware: [RequireAdmin::class]`). For checks inside an action, use the user context directly:

```php
if (!$this->getUserContext()?->hasEntitlement('admin')) {
    return $this->forbidden();
}
```

---

## JWT Validation

The `JwtValidator` automatically handles tokens from any configured provider. When validating a token, it:

1. Peeks at the unverified `iss` (issuer) claim in the JWT payload
2. If the issuer matches `local.issuer`, validates with the local signing key and requires `aud` to match `local.audience`
3. Otherwise, tries each OIDC provider in turn until one accepts the token — signature against its JWKS, `iss` equal to its discovery issuer, and `aud` equal to its `audience` (or `clientId`)

Every token must carry an `exp` claim; one without it is rejected rather than treated as never expiring.

This means `WebAuthenticationMiddleware` and `ApiAuthenticationMiddleware` work without changes regardless of which provider issued the token. If your API accepts tokens you issue yourself, sign them with the `local` config and include `iss`, `aud` and `exp`.

### Error Messages

A `SecurityException` that reaches the framework's exception handler becomes a **401**. Outside debug mode (`app.debug` false) its message is replaced with a generic `Authentication failed.`, so a client cannot learn why authentication failed; with `app.debug` true the real message is shown. This also applies to configuration mistakes that surface as a `SecurityException` — a missing `local` signing config, for example, shows up in production as a 401 `Authentication failed.` on the auth routes.

Error responses produced by the exception handler still carry the headers of global `CorsMiddleware` and `SecurityHeadersMiddleware`, so a cross-origin API client can read the 401.

The exception is the login flow itself: when a provider callback fails, the user is redirected to `{loginPath}?error=1` and the failure message is displayed on the login page. The message is kept in the session, never in the URL — the `error` query parameter is only a flag, and a link carrying its own `error` text shows a generic `Sign-in failed. Please try again.` (Before 4.1 the message itself travelled in the `error` parameter.) A custom login renderer still receives the message text.

---

## Accessing Provider Info

After authentication, the `UserContext` includes which provider the user authenticated with:

```php
$userContext = $this->getUserContext();
$provider = $userContext->getProvider(); // "okta", "github", "local", etc.

$user = $userContext->getUser();
$user->id;           // from 'sub' claim
$user->username;     // from 'username', 'preferred_username' or 'name' claim
$user->email;        // from 'email' claim
$user->entitlements; // from 'entitlements' claim

$userContext->getClaim('tid');  // any other claim in the token
$userContext->getClaims();      // all claims
```

`getUserContext()` is available in every controller (since 4.1 it is defined on the base `Controller`; before that only on `ApiController`). It returns `null` on routes with no authentication middleware.

`getProvider()` is a method of the concrete `UserContext` class; it is not part of `UserContextInterface`. Framework-issued JWTs (OAuth2 and Local) carry a `provider` claim; for OIDC tokens the name of the provider that validated the token is filled in on every request.

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
            "signingKey": "replace-with-a-random-secret-at-least-32-characters-long",
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

For plain-HTTP local development, also create `config/config.dev.json` (gitignored) so the cookies are sent without HTTPS:

```json
{
    "auth":    { "cookieSecure": false },
    "session": { "cookieSecure": false }
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
        $router->post('/logout', HomeController::class, 'index');
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
$app->loadEnvironmentConfig(); // config.json → config.{APP_ENV}.json → config.dev.json

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
4. Submit a logout form (`POST /auth/logout` with a `csrf_token` field -- see [Logout](#logout)) -- clears cookie, redirects to `/`. A plain `GET /auth/logout` returns 405
