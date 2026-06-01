# Live OIDC test app (Microsoft Entra)

A throwaway, single-page Melodic app for manually click-testing the OAuth2/OIDC
login flow end to end against **Microsoft Entra ID** (Azure AD). It is **not** part
of the PHPUnit suite — the suite only runs `*Test.php` files, and nothing here
matches that. The automated security coverage lives in `tests/Security/`.

What a real Entra run proves that unit tests can't: discovery + JWKS over real
HTTPS, PKCE round-trip through a browser redirect, and a genuine signed token
passing issuer / audience / `exp` / signature validation — then re-validating on
the next request from the auth cookie.

## The redirect URI to register in Entra

```
http://localhost:8080/auth/callback/entra
```

Entra allows plain `http` for the `localhost` loopback, so no HTTPS is needed for
local testing. Register it under the **Web** platform (we use a client secret and
do a server-side code exchange).

## 1. Register the app in Entra

1. [Entra admin center](https://entra.microsoft.com) → **Identity → Applications →
   App registrations → New registration**.
2. Name it (e.g. "Melodic Live Test"). For **Supported account types**, pick
   **single tenant** (accounts in this org only) — that keeps the token issuer a
   single concrete value, which is what the framework's strict issuer check wants.
3. Under **Redirect URI**, choose platform **Web** and enter:
   `http://localhost:8080/auth/callback/entra`. Click **Register**.
4. From the **Overview** page copy the **Application (client) ID** and the
   **Directory (tenant) ID**.
5. **Certificates & secrets → New client secret** → copy the secret **Value**
   (not the Secret ID).

> Use the **tenant-specific v2.0** discovery URL, not `/common/` or
> `/organizations/`. The multi-tenant issuer is templated (`{tenantid}`) and the
> framework's `iss` check will (correctly) reject it.

## 2. Configure this app

```bash
cp config/config.dev.json.example config/config.dev.json
```

Edit `config/config.dev.json` and replace:
- `REPLACE_TENANT_ID` — Directory (tenant) ID
- `REPLACE_APPLICATION_CLIENT_ID` — Application (client) ID
- `REPLACE_CLIENT_SECRET_VALUE` — the client secret **Value**

`config/config.dev.json` is gitignored, so your secrets never get committed.

## 3. Run it

From the repo root:

```bash
php -S localhost:8080 -t tests/livetest/public
```

Then:
1. Open <http://localhost:8080/> — you're not authenticated, so you're redirected
   to `/auth/login`.
2. Click **Sign in with Microsoft Entra** → authenticate at Microsoft.
3. You're redirected back to `/auth/callback/entra`, then to `/`, which shows your
   ID-token claims. ✅ The flow validated end to end.
4. Click **Log out** to clear the auth cookie (CSRF-protected POST).

## Notes / troubleshooting

- **Port matters.** The redirect URI must match exactly, including `:8080`. If you
  run on another port, update both Entra and `redirectUri` in config.
- **Cookies over http.** `config.json` sets `cookieSecure: false` for both the
  session and auth cookies so they work on plain-HTTP localhost. Never do this in
  production — secure defaults are on for a reason.
- **OIDC discovery/JWKS cache.** Cached in the system temp dir
  (`sys_get_temp_dir()/melodic_oidc_cache`) for 1 hour. Delete that folder if you
  change tenants and want a clean fetch.
- **AADSTS error in the browser** = Entra-side config (redirect URI mismatch,
  wrong secret). **`SecurityException` redirect back to the login page** = the
  framework rejected the token (issuer/audience/exp) — exactly the paths under test.
