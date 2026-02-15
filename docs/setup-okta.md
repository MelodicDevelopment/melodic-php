# Okta Authentication Setup

This guide walks you through adding "Sign in with Okta" to your Melodic PHP app.

## Step 1: Create an Okta Developer Account

1. Go to [Okta Developer Signup](https://developer.okta.com/signup/)
2. Fill in the form and create your account
3. Check your email and activate your account
4. You'll get an Okta domain like `https://dev-12345678.okta.com` — save this

## Step 2: Create an Application in Okta

1. Log into your [Okta Admin Dashboard](https://developer.okta.com/login/)
2. In the left sidebar, go to **Applications > Applications**
3. Click **Create App Integration**
4. Select:
   - **Sign-in method**: OIDC - OpenID Connect
   - **Application type**: Web Application
5. Click **Next**

## Step 3: Configure the Application

1. Fill in the settings:
   - **App integration name**: Your app's name (e.g. "My App")
   - **Grant type**: Make sure **Authorization Code** is checked
   - **Sign-in redirect URIs**: `http://localhost:8080/auth/callback/okta`
   - **Sign-out redirect URIs**: `http://localhost:8080` (optional)
2. Under **Assignments**, choose who can access the app:
   - **Allow everyone in your organization to access** is easiest for testing
3. Click **Save**

## Step 4: Get Your Credentials

After saving, you'll be on the application's settings page:

1. Copy the **Client ID**
2. Copy the **Client Secret** (click the eye icon to reveal it)
3. Note your **Okta domain** (shown in the browser URL bar, e.g. `https://dev-12345678.okta.com`)

## Step 5: Find Your Discovery URL

Your OIDC discovery URL follows this pattern:

```
https://dev-12345678.okta.com/.well-known/openid-configuration
```

Replace `dev-12345678.okta.com` with your actual Okta domain.

If you're using a custom authorization server, the URL is:

```
https://dev-12345678.okta.com/oauth2/default/.well-known/openid-configuration
```

You can paste either URL into your browser to verify it returns a JSON document.

## Step 6: Configure Your App

Open your `config/config.json`:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "providers": {
            "okta": {
                "type": "oidc",
                "label": "Sign in with Okta",
                "discoveryUrl": "https://dev-12345678.okta.com/.well-known/openid-configuration",
                "clientId": "PASTE_YOUR_CLIENT_ID_HERE",
                "clientSecret": "PASTE_YOUR_CLIENT_SECRET_HERE",
                "redirectUri": "http://localhost:8080/auth/callback/okta",
                "audience": "",
                "scopes": "openid profile email"
            }
        }
    }
}
```

Replace the placeholder values:
- `https://dev-12345678.okta.com` — your Okta domain
- `PASTE_YOUR_CLIENT_ID_HERE` — your Client ID from Step 4
- `PASTE_YOUR_CLIENT_SECRET_HERE` — your Client Secret from Step 4

### About the `audience` field

- If you're using the **org authorization server** (discovery URL without `/oauth2/`), leave `audience` as `""` or remove it
- If you're using a **custom authorization server** (discovery URL with `/oauth2/default`), set `audience` to your Client ID

## Step 7: Set Up Routes

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

## Step 8: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t example/public
   ```
2. Visit `http://localhost:8080/auth/login`
3. Click **Sign in with Okta**
4. Log in with your Okta account
5. You should be redirected back to your app with an auth cookie set

## Troubleshooting

- **"redirect_uri_mismatch" error**: The redirect URI in your config must exactly match what's configured in Okta. Check for trailing slashes and matching protocols.
- **"login_required" error**: You may need to sign in to Okta first, or your session has expired.
- **"access_denied" error**: Your user may not be assigned to the application. Go to **Applications > Your App > Assignments** and add your user.
- **Discovery URL returns 404**: Double-check your Okta domain. Try both the org URL (`/.well-known/openid-configuration`) and the custom auth server URL (`/oauth2/default/.well-known/openid-configuration`).
- **For production**: Update `redirectUri` to your production `https` URL and update the redirect URI in Okta to match.
