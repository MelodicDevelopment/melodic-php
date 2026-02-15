# Google Authentication Setup

This guide walks you through adding "Sign in with Google" to your Melodic PHP app.

## Step 1: Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click the project dropdown at the top and select **New Project**
3. Give it a name (e.g. "My App") and click **Create**
4. Make sure your new project is selected in the dropdown

## Step 2: Enable the Google Identity API

1. In the left sidebar, go to **APIs & Services > Library**
2. Search for **Google Identity** or **Google+ API**
3. Click on it and press **Enable**

## Step 3: Set Up the OAuth Consent Screen

1. Go to **APIs & Services > OAuth consent screen**
2. Choose **External** (unless you have a Google Workspace org) and click **Create**
3. Fill in the required fields:
   - **App name**: Your app's name
   - **User support email**: Your email
   - **Developer contact email**: Your email
4. Click **Save and Continue**
5. On the **Scopes** page, click **Add or Remove Scopes** and select:
   - `openid`
   - `email`
   - `profile`
6. Click **Save and Continue** through the remaining steps

## Step 4: Create OAuth Credentials

1. Go to **APIs & Services > Credentials**
2. Click **+ Create Credentials > OAuth client ID**
3. Set **Application type** to **Web application**
4. Give it a name (e.g. "Melodic App")
5. Under **Authorized redirect URIs**, click **+ Add URI** and enter:
   ```
   http://localhost:8080/auth/callback/google
   ```
6. Click **Create**
7. A dialog will show your **Client ID** and **Client Secret** — copy both

## Step 5: Configure Your App

Open your `config/config.json` and add the Google provider:

```json
{
    "auth": {
        "api": { "enabled": true },
        "web": { "enabled": true },
        "providers": {
            "google": {
                "type": "oidc",
                "label": "Sign in with Google",
                "discoveryUrl": "https://accounts.google.com/.well-known/openid-configuration",
                "clientId": "PASTE_YOUR_CLIENT_ID_HERE",
                "clientSecret": "PASTE_YOUR_CLIENT_SECRET_HERE",
                "redirectUri": "http://localhost:8080/auth/callback/google",
                "scopes": "openid profile email"
            }
        }
    }
}
```

Replace `PASTE_YOUR_CLIENT_ID_HERE` and `PASTE_YOUR_CLIENT_SECRET_HERE` with the values from Step 4.

## Step 6: Set Up Routes

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

## Step 7: Test It

1. Start your app:
   ```bash
   php -S localhost:8080 -t example/public
   ```
2. Visit `http://localhost:8080/auth/login`
3. Click **Sign in with Google**
4. Sign in with your Google account
5. You should be redirected back to your app with an auth cookie set

## Troubleshooting

- **"redirect_uri_mismatch" error**: The redirect URI in your config must exactly match what you entered in the Google Cloud Console, including the protocol (`http` vs `https`) and port.
- **"access_denied" error**: Your app is in testing mode. Go to the OAuth consent screen and add your Google account as a test user.
- **For production**: Change `redirectUri` to your production URL (must be `https`) and update the Google Cloud Console to match.
