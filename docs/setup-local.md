# Local (Email/Password) Authentication Setup

This guide walks you through adding username/password login to your Melodic PHP app. Unlike the social providers, local auth doesn't require any external service — your app handles everything.

## How It Works

1. The user visits the login page and enters their email and password
2. The framework passes the credentials to your authenticator class
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

Replace `replace-with-a-random-secret...` with a random string. You can generate one with:

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

### Config explained

| Key | What it does |
|---|---|
| `signingKey` | Secret used to sign the JWT. Keep this private. |
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

If the credentials are invalid, throw a `SecurityException` with a user-facing error message.

## Step 3: Register Your Authenticator

Tell the DI container about your authenticator. In your `public/index.php` (or a service provider), add:

```php
use Melodic\Security\LocalAuthenticatorInterface;
use App\Security\AppAuthenticator;

$app->services(function ($container) {
    $container->bind(LocalAuthenticatorInterface::class, AppAuthenticator::class);
});
```

Make sure this runs **before** `$app->register(new SecurityServiceProvider())`.

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
    $router->get('/logout', HomeController::class, 'index');
}, middleware: [AuthCallbackMiddleware::class]);
```

## Step 5: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t example/public
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
- **"Local provider requires a 'local' signing config"**: You're missing the `local` section in your auth config (the one with `signingKey`).
- **"Target [LocalAuthenticatorInterface] is not instantiable"**: You forgot to bind your authenticator in the DI container (Step 3).
- **Passwords**: Always store passwords with `password_hash()` and verify with `password_verify()`. Never store plain text passwords.
