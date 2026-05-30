# Migrating to Melodic 3.0

Version 3.0 is a security and correctness hardening release. Most changes are
internal, but a few alter existing behavior. This guide lists everything an
application may need to change.

---

## Breaking changes

### 1. Logout is now a CSRF-protected POST

Previously `GET /auth/logout` logged the user out — a CSRF-able state change.
Logout is now **`POST /auth/logout` with a CSRF token**.

- A non-POST request to `/auth/logout` returns **405**.
- A POST without a valid `csrf_token` returns **403**.

**What to change:** replace logout links with a small form:

```html
<form method="post" action="/auth/logout">
    <input type="hidden" name="csrf_token" value="<?= $this->e($csrfToken) ?>">
    <button type="submit">Log out</button>
</form>
```

Obtain the token from `CsrfToken::getToken()` (or your login renderer, which is
already passed one).

### 2. Validation is nullable-by-default

Format rules (`#[Email]`, `#[MaxLength]`, `#[MinLength]`, `#[Pattern]`, `#[Min]`,
`#[Max]`, `#[In]`) now **pass when the value is `null`/absent**. Only `#[Required]`
rejects a missing value. This fixes a bug where an optional field with a format
rule failed whenever it was omitted.

**What to change:** if you relied on the old behavior to make a field effectively
required, add `#[Required]` explicitly. Update tests that asserted an omitted
optional field was invalid.

### 3. Local signing key must be non-empty and strong

`LocalAuthConfig` now throws `InvalidArgumentException` when an HMAC algorithm
(`HS*`) is configured with a signing key shorter than 32 characters.

**What to change:** set a strong secret in config, e.g.

```bash
openssl rand -base64 48
```

```json
{ "auth": { "local": { "signingKey": "<your-strong-secret>" } } }
```

### 4. Cookies default to Secure

The auth cookie and the PHP session cookie now default to
`Secure` + `HttpOnly` + `SameSite=Lax`. Over plain HTTP (typical local dev) a
`Secure` cookie is not sent, so **login will appear not to work locally** until
you disable it for that environment.

**What to change:** in your dev config only:

```json
{
  "auth":    { "cookieSecure": false },
  "session": { "cookieSecure": false }
}
```

New config keys: `auth.cookieSecure`, `auth.cookieSameSite`, `auth.cookiePath`,
`auth.cookieDomain`, and the same under `session.*`.

### 5. JWTs must carry `iss` and `exp`

OIDC tokens are now rejected unless their `iss` matches the provider's discovery
issuer and an `exp` claim is present (local tokens also require `exp`). This is
standard, but verify your identity provider issues both.

### 6. HEAD responses no longer include a body

`HEAD` requests now send headers only (per RFC 9110). Any client relying on a body
from a HEAD response must use GET.

---

## Non-breaking, but worth knowing

- **405 with `Allow`**: requests to a known path under an unsupported method now
  return 405 with an `Allow` header (previously 404).
- **Model binding**: malformed scalars in a request body now produce a **400**
  (field error) instead of a 500; non-`Model` typed action parameters are resolved
  from the container (service injection).
- **FileCache**: cache files now use a `.cache` extension; `clear()` only removes
  those. Old cache files are ignored and harmlessly regenerated.
- **OIDC cache**: discovery/JWKS cache dir is created `0700`; override its location
  with `auth.oidcCacheDir`.
- **Views**: use `$this->e($value)` in `.phtml` to HTML-escape output (no
  auto-escaping).

---

## Recommended config review

After upgrading, review your `config.json` per environment:

```json
{
  "auth": {
    "local": { "signingKey": "<strong-secret>" },
    "cookieSecure": true,
    "oidcCacheDir": "storage/cache/oidc"
  },
  "session": {
    "cookieSecure": true,
    "cookieSameSite": "Lax"
  }
}
```

Set the `cookieSecure` flags to `false` only in local HTTP development configs.
