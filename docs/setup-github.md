# GitHub Authentication Setup

This guide walks you through adding "Sign in with GitHub" to your Melodic PHP app.

## Step 1: Create a GitHub OAuth App

1. Go to [GitHub Developer Settings](https://github.com/settings/developers)
2. Click **OAuth Apps** in the left sidebar
3. Click **New OAuth App**
4. Fill in the form:
   - **Application name**: Your app's name (e.g. "My App")
   - **Homepage URL**: `http://localhost:8080`
   - **Authorization callback URL**: `http://localhost:8080/auth/callback/github`
5. Click **Register application**

## Step 2: Get Your Credentials

1. After creating the app, you'll see the app's settings page
2. Copy the **Client ID** shown on the page
3. Click **Generate a new client secret**
4. Copy the **Client Secret** immediately — it won't be shown again

## Step 3: Configure Your App

GitHub uses OAuth2 (not OIDC), so the framework needs a signing key to issue its own JWT after login. Open your `config/config.json`:

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
            "github": {
                "type": "oauth2",
                "label": "Sign in with GitHub",
                "authorizeUrl": "https://github.com/login/oauth/authorize",
                "tokenUrl": "https://github.com/login/oauth/access_token",
                "userInfoUrl": "https://api.github.com/user",
                "clientId": "PASTE_YOUR_CLIENT_ID_HERE",
                "clientSecret": "PASTE_YOUR_CLIENT_SECRET_HERE",
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

Replace the placeholder values:
- `PASTE_YOUR_CLIENT_ID_HERE` — your GitHub Client ID
- `PASTE_YOUR_CLIENT_SECRET_HERE` — your GitHub Client Secret
- `replace-with-a-random-secret...` — any random string of 32+ characters (this signs the JWT your app creates)

### Why is `local.signingKey` needed?

GitHub's access tokens are opaque strings, not JWTs. After getting the access token, the framework calls GitHub's API to fetch your profile, then creates its own JWT containing your user info. The `signingKey` is used to sign that JWT.

### What does `claimMap` do?

GitHub returns user data with field names like `login` and `id`. The `claimMap` tells the framework how to translate these to the standard names used internally:

| GitHub field | Maps to | Used for |
|---|---|---|
| `id` | `sub` | User ID |
| `login` | `username` | Display name |
| `email` | `email` | Email address |

## Step 4: Set Up Routes

In your `config/routes.php`, add the auth routes:

```php
use Melodic\Security\AuthCallbackMiddleware;

$router->group('/auth', function (Router $router) {
    $router->get('/login', HomeController::class, 'index');
    $router->get('/login/{provider}', HomeController::class, 'index');
    $router->get('/callback/{provider}', HomeController::class, 'index');
    $router->get('/logout', HomeController::class, 'index');
}, middleware: [AuthCallbackMiddleware::class]);
```

## Step 5: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t example/public
   ```
2. Visit `http://localhost:8080/auth/login`
3. Click **Sign in with GitHub**
4. Authorize the app on GitHub
5. You should be redirected back to your app with an auth cookie set

## Troubleshooting

- **"redirect_uri_mismatch" error**: The callback URL in your config must exactly match what you entered on GitHub, including protocol and port.
- **Email is null**: Some GitHub users have their email set to private. Request the `user:email` scope (already included above) and the email should come through. If the user has no public email, `email` may still be empty.
- **For production**: Update the Homepage URL and callback URL on GitHub to your production domain, and update `redirectUri` in your config to match.
