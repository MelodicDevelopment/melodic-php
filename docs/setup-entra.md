# Microsoft Entra (Azure AD) Authentication Setup

This guide walks you through adding "Sign in with Microsoft Entra" to your Melodic
PHP app. Entra is a standard OpenID Connect provider, so it uses the `oidc`
provider type with automatic discovery.

## Step 1: Register an Application

1. Go to the [Entra admin center](https://entra.microsoft.com)
2. Navigate to **Identity > Applications > App registrations > New registration**
3. Give it a name (e.g. "My App")
4. Under **Supported account types**, choose **Accounts in this organizational
   directory only (single tenant)** — this keeps the token issuer a single concrete
   value, which is what Melodic's strict issuer check expects
5. Under **Redirect URI**, select platform **Web** and enter:
   ```
   http://localhost:8080/auth/callback/entra
   ```
6. Click **Register**

## Step 2: Collect Your IDs

From the application's **Overview** page, copy:
- **Application (client) ID** — your client ID
- **Directory (tenant) ID** — used to build the discovery URL

## Step 3: Create a Client Secret

1. Go to **Certificates & secrets > Client secrets > New client secret**
2. Add a description and expiry, then click **Add**
3. Copy the secret **Value** immediately (not the Secret ID — it is only shown once)

## Step 4: Configure Your App

Open your `config/config.json` and add the Entra provider. Replace
`YOUR_TENANT_ID` in the discovery URL with the **Directory (tenant) ID** from
Step 2:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "providers": {
            "entra": {
                "type": "oidc",
                "label": "Sign in with Microsoft Entra",
                "discoveryUrl": "https://login.microsoftonline.com/YOUR_TENANT_ID/v2.0/.well-known/openid-configuration",
                "clientId": "PASTE_YOUR_CLIENT_ID_HERE",
                "clientSecret": "PASTE_YOUR_CLIENT_SECRET_VALUE_HERE",
                "redirectUri": "http://localhost:8080/auth/callback/entra",
                "scopes": "openid profile email"
            }
        }
    }
}
```

> **Use the tenant-specific v2.0 discovery URL** shown above — not `/common/` or
> `/organizations/`. The multi-tenant issuer is templated (`{tenantid}`) and
> Melodic's issuer check will (correctly) reject tokens whose `iss` does not match
> the discovered issuer exactly.

## Step 5: Set Up Routes

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

## Step 6: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t example/public
   ```
2. Visit `http://localhost:8080/auth/login`
3. Click **Sign in with Microsoft Entra**
4. Sign in with your Microsoft account
5. You should be redirected back to your app with an auth cookie set

## Troubleshooting

- **`AADSTS50011` / redirect URI mismatch**: The redirect URI in your config must
  exactly match the one registered in Entra, including protocol (`http` vs `https`)
  and port. Entra allows plain `http` only for the `localhost` loopback.
- **`Invalid token: JWK must contain an "alg" parameter`**: Entra's JWKS omits the
  per-key `alg`. Melodic handles this by parsing keys with a default algorithm
  (`RS256`). If your provider signs with a different algorithm, set `signingAlg` on
  the provider config (e.g. `"signingAlg": "ES256"`).
- **`Invalid token issuer`**: You are almost certainly using the `/common/`
  discovery URL with a single-tenant app. Switch to the tenant-specific v2.0 URL.
- **For production**: Change `redirectUri` to your production URL (must be `https`)
  and add it to the app registration's redirect URIs.
