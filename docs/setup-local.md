# Local (Email/Password) Authentication Setup

This guide walks you through adding username/password login to your Melodic PHP app. Unlike the social providers, local auth doesn't require any external service — your app handles everything.

## How It Works

1. The user visits the login page and enters their email and password
2. The framework checks the form's CSRF token, then passes the credentials to your authenticator class
3. Your authenticator checks the credentials against your database (or any other source)
4. If valid, the framework creates a signed JWT and stores it in a cookie
5. The user is now logged in

## Step 1: Add the Signing Key Config

The framework needs a secret key to sign the JWTs it creates. Open your `config/config.json`:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
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

Replace `replace-with-a-random-secret...` with a random string of **at least 32 characters** — a shorter (or empty) key is rejected at startup. You can generate one with:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

> **Plain-HTTP local development:** the auth and session cookies are `Secure` by default, and browsers only reliably accept `Secure` cookies over HTTPS (some exempt `localhost`), so over `http://localhost` the login may not stick (typical symptom: `Invalid or expired form submission.` every time you submit the form). Put this in `config/config.dev.json` — the gitignored local override file loaded by `$app->loadEnvironmentConfig()` — and never in production config:
> ```json
> { "auth": { "cookieSecure": false }, "session": { "cookieSecure": false } }
> ```

### Config explained

| Key | What it does |
|---|---|
| `signingKey` | Secret used to sign the JWT (HS256 by default). Keep this private. At least 32 characters. |
| `issuer` | Identifies who created the token. Can be any string. |
| `audience` | Identifies who the token is for. Can be any string. |
| `tokenLifetime` | How long the token is valid, in seconds. `3600` = 1 hour. |
| `label` | The text shown on the login button/form. |

## Step 2: Create Your Authenticator

Create a class that implements `LocalAuthenticatorInterface`. This is where you check the username and password against your user store.

Create the file `src/Security/AppAuthenticator.php` (adjust the namespace to match your app):

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
        // TODO: Replace this with a real database lookup
        //
        // This is just an example. In a real app you would:
        // 1. Look up the user by email/username in your database
        // 2. Verify the password with password_verify()
        // 3. Return their info or throw an exception

        $users = [
            'admin@example.com' => [
                'password' => password_hash('password123', PASSWORD_DEFAULT),
                'id' => '1',
                'username' => 'admin',
                'email' => 'admin@example.com',
                'roles' => ['admin'],
            ],
        ];

        $user = $users[$username] ?? null;

        if ($user === null || !password_verify($password, $user['password'])) {
            throw new SecurityException('Invalid email or password.');
        }

        // Return the claims to embed in the JWT
        return [
            'sub' => $user['id'],
            'username' => $user['username'],
            'email' => $user['email'],
            'entitlements' => $user['roles'],
        ];
    }
}
```

### What to return

The `authenticate` method must return an array with these keys:

| Key | Type | Description |
|---|---|---|
| `sub` | string | A unique user ID |
| `username` | string | The user's display name |
| `email` | string | The user's email |
| `entitlements` | array | List of roles/permissions (can be empty `[]`) |

If the credentials are invalid, throw a `SecurityException` with a user-facing error message — it is shown on the login page as-is.

## Step 3: Register Your Authenticator

Tell the DI container about your authenticator. In your `public/index.php` (or a service provider), add:

```php
use Melodic\Security\LocalAuthenticatorInterface;
use App\Security\AppAuthenticator;

$app->services(function ($container) {
    $container->bind(LocalAuthenticatorInterface::class, AppAuthenticator::class);
});
```

The binding is resolved lazily, so it does not matter whether this runs before or after `$app->register(new SecurityServiceProvider())` — but that provider must be registered.

### Using a Service Provider

If you prefer, create a service provider instead:

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

Then register it in `index.php`:

```php
$app->register(new App\Providers\AppServiceProvider());
$app->register(new SecurityServiceProvider());
```

## Step 4: Set Up Routes

In your `config/routes.php`, add the auth routes. The local provider uses `POST` for the form submission:

```php
use Melodic\Security\AuthCallbackMiddleware;

$router->group('/auth', function (Router $router) {
    $router->get('/login', HomeController::class, 'index');
    $router->post('/callback/{provider}', HomeController::class, 'index');
    $router->post('/logout', HomeController::class, 'index');
}, middleware: [AuthCallbackMiddleware::class]);
```

The built-in login form posts to `/auth/callback/local` and includes a hidden `csrf_token` field, which the framework verifies before calling your authenticator. Logout is a CSRF-protected `POST /auth/logout`, not a link — see [Logout](authentication.md#logout).

## Step 5: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t public
   ```
2. Visit `http://localhost:8080/auth/login`
3. You should see a form with **Email or Username** and **Password** fields
4. Enter `admin@example.com` and `password123` (or whatever you set up in your authenticator)
5. Click **Sign in with Email**
6. You should be redirected to your app with an auth cookie set

## Step 6: Use a Real Database (Production)

Replace the hardcoded user list with a database lookup. Here's an example using the framework's `DbContext`:

```php
<?php

declare(strict_types=1);

namespace App\Security;

use Melodic\Data\DbContextInterface;
use Melodic\Security\LocalAuthenticatorInterface;
use Melodic\Security\SecurityException;

class AppAuthenticator implements LocalAuthenticatorInterface
{
    public function __construct(
        private readonly DbContextInterface $db,
    ) {
    }

    public function authenticate(string $username, string $password): array
    {
        $sql = "SELECT id, username, email, password_hash, roles FROM users WHERE email = :email";
        $row = $this->db->queryFirst(\stdClass::class, $sql, ['email' => $username]);

        if ($row === null || !password_verify($password, $row->password_hash)) {
            throw new SecurityException('Invalid email or password.');
        }

        return [
            'sub' => (string) $row->id,
            'username' => $row->username,
            'email' => $row->email,
            'entitlements' => json_decode($row->roles, true) ?: [],
        ];
    }
}
```

Since the constructor type-hints `DbContextInterface`, the DI container will automatically inject it — no changes needed to your binding.

## Troubleshooting

- **"Username and password are required"**: The form fields must be named `username` and `password`. If you're building a custom form, make sure the `name` attributes match.
- **"Invalid or expired form submission. Please try again."**: The `csrf_token` field was missing or did not match the session. A custom form must include `<input type="hidden" name="csrf_token">` with the value from `CsrfToken::getToken()`. If it happens with the built-in form, the session cookie is not being sent back — over plain HTTP, set `session.cookieSecure` to `false` (see the note in Step 1).
- **A 401 "Authentication failed." on the auth routes**: You're missing the `local` section in your auth config (the one with `signingKey`). Outside debug mode the real message — `Local provider requires a "local" signing config in the auth section.` — is hidden; set `app.debug` to `true` locally to see it.
- **A 500 with "Local auth signing key must be at least 32 characters for HS256"** (visible with `app.debug`): Your `signingKey` is empty or too short.
- **A 500 with "Unable to resolve 'Melodic\Security\LocalAuthenticatorInterface'"** (visible with `app.debug`): You forgot to bind your authenticator in the DI container (Step 3).
- **Passwords**: Always store passwords with `password_hash()` and verify with `password_verify()`. Never store plain text passwords.
