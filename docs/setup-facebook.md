# Facebook Authentication Setup

This guide walks you through adding "Sign in with Facebook" to your Melodic PHP app.

## Step 1: Create a Facebook App

1. Go to [Facebook Developers](https://developers.facebook.com/)
2. Click **My Apps** in the top right, then **Create App**
3. Select **Consumer** (or **None** if Consumer isn't shown) and click **Next**
4. Fill in:
   - **App name**: Your app's name
   - **App contact email**: Your email
5. Click **Create App**

## Step 2: Add Facebook Login

1. On your app's dashboard, find **Facebook Login** and click **Set Up**
2. Choose **Web**
3. Enter your site URL: `http://localhost:8080` and click **Save**
4. In the left sidebar, go to **Facebook Login > Settings**
5. Under **Valid OAuth Redirect URIs**, add:
   ```
   http://localhost:8080/auth/callback/facebook
   ```
6. Click **Save Changes**

## Step 3: Get Your Credentials

1. In the left sidebar, go to **Settings > Basic**
2. Copy your **App ID** — this is your client ID
3. Click **Show** next to **App Secret** and copy it — this is your client secret

## Step 4: Configure Your App

Facebook uses OAuth2 (not OIDC), so the framework needs a signing key to issue its own JWT after login. Open your `config/config.json`:

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
            "facebook": {
                "type": "oauth2",
                "label": "Sign in with Facebook",
                "authorizeUrl": "https://www.facebook.com/v21.0/dialog/oauth",
                "tokenUrl": "https://graph.facebook.com/v21.0/oauth/access_token",
                "userInfoUrl": "https://graph.facebook.com/me?fields=id,name,email",
                "clientId": "PASTE_YOUR_APP_ID_HERE",
                "clientSecret": "PASTE_YOUR_APP_SECRET_HERE",
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

Replace the placeholder values:
- `PASTE_YOUR_APP_ID_HERE` — your Facebook App ID
- `PASTE_YOUR_APP_SECRET_HERE` — your Facebook App Secret
- `replace-with-a-random-secret...` — any random string of 32+ characters

### Why is `local.signingKey` needed?

Facebook's access tokens are opaque, not JWTs. After login, the framework calls Facebook's Graph API to fetch your profile, then creates its own signed JWT. The `signingKey` is used to sign that token.

### What does `claimMap` do?

Facebook returns user data with its own field names. The `claimMap` tells the framework how to translate them:

| Facebook field | Maps to | Used for |
|---|---|---|
| `id` | `sub` | User ID |
| `name` | `username` | Display name |
| `email` | `email` | Email address |

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
3. Click **Sign in with Facebook**
4. Log in with your Facebook account and authorize the app
5. You should be redirected back to your app with an auth cookie set

## Important: Development Mode

New Facebook apps start in **Development Mode**. While in this mode:
- Only people listed as app admins, developers, or testers can log in
- To add test users, go to **App Roles > Roles** in the Facebook developer dashboard

To let anyone log in:
1. Go to **Settings > Basic** and make sure all required fields are filled
2. Toggle the switch at the top of the dashboard from **In Development** to **Live**
3. You may need to complete Facebook's **App Review** to access certain permissions

## Troubleshooting

- **"URL blocked" error**: Your redirect URI doesn't match. Check that `redirectUri` in your config exactly matches what's in **Facebook Login > Settings > Valid OAuth Redirect URIs**.
- **Email is missing**: The user may not have an email on their Facebook account, or they may have declined the email permission. The `email` scope is requested but Facebook lets users opt out.
- **"App not set up" error**: Make sure you've added the **Facebook Login** product to your app (Step 2).
- **For production**: Use `https` URLs, update the redirect URI in both your config and the Facebook dashboard, and switch the app to Live mode.
