# Migrating to Melodic 4.0

Version 4.0 completes the security/correctness remediation started in 3.0.
Most changes are internal hardening; the items below alter existing behavior.
See [`CHANGELOG.md`](CHANGELOG.md) for the full list of fixes.

---

## Breaking changes

### 1. `Response::withCookie()` defaults to `secure: true`

Cookies queued with `withCookie()` are now `Secure` by default, matching the
auth/session cookies (which already were). Browsers will not store a `Secure`
cookie delivered over plain HTTP.

**What to change:** for plain-HTTP local development, opt out explicitly:

```php
$response->withCookie('name', 'value', ['secure' => false]);
```

Production HTTPS deployments need no change.

### 2. Default refresh cookie name is now `melodic_refresh`

The default was `kingdom_refresh`. If your deployment relies on the default
name, in-flight refresh cookies stop matching after deploy and users re-login
once.

**What to change (only to avoid the one-time re-login):** pin the old name in
config:

```json
{ "refresh": { "cookieName": "kingdom_refresh" } }
```

### 3. OIDC callback requires an `id_token`

The callback previously fell back to the `access_token` when no `id_token` was
returned. It now requires an `id_token`, verifies the new **nonce** claim
against the login attempt, and requires the discovery document's issuer host
to match the discovery URL host.

Standards-compliant IdPs (Entra ID, Auth0, Okta, Keycloak, Google) are
unaffected as long as your scopes include `openid` (the default). If you
relied on the access-token fallback, use the OAuth2 provider type instead.

### 4. `AuthorizationMiddleware` entitlements always require authentication

An anonymous request could previously pass a route that declared required
entitlements if `requireAuthentication` was set to `false`. Entitlement
checks are now enforced unconditionally: anonymous → **401**, authenticated
but lacking the entitlement → **403**.

**What to change:** if a route should allow anonymous access, don't declare
entitlements on it.

### 5. Configuration lists replace instead of merging by index

Overriding a list (e.g. `cors.allowedOrigins`) in an environment config file
now replaces the base list entirely. Previously a shorter override kept the
trailing base elements — `[a, b]` overridden by `[qa]` produced `[qa, b]`.

**What to change:** environment files must list *every* element the
environment needs, not just the changed positions. See
[`docs/configuration.md`](docs/configuration.md).

### 6. `#[Required]` rejects empty arrays

A required array/collection field now fails validation when `[]` is
submitted, consistent with `''` failing for strings.

### 7. Uncoercible input returns 400 instead of 500

Two classes of malformed client input that previously surfaced as uncaught
`TypeError`s (HTTP 500) now return **400** with a field-keyed error body:

- Route params typed on the action (`show(int $id)` with `/users/abc`)
- Model properties typed as backed enums or `DateTime`/`DateTimeImmutable`

If you had monitoring keyed on those 500s, expect 400s now.

### 8. Smaller edge cases

- **Injected PDO**: `DbContext` now sets `ERRMODE_EXCEPTION` and
  `FETCH_ASSOC` on a PDO instance you pass in (its transaction handling
  always assumed this).
- **`Service::__destruct()`** (empty hook) was removed. Subclasses overriding
  `__destruct` keep working; a `parent::__destruct()` call must be dropped.
- **DI container** failures throw `Melodic\DI\ContainerException` /
  `CircularDependencyException`. Both extend `RuntimeException`, so existing
  catch blocks keep working — but exceptions thrown by *your* factories now
  propagate instead of being silently replaced by a parameter default.
- **SecurityException messages** are masked as `Authentication failed.` in
  production responses (debug mode still shows the real message; the real
  reason is logged).
- **CORS**: `Access-Control-Allow-Methods` / `-Allow-Headers` / `-Max-Age`
  are only sent on preflight (OPTIONS) responses.
- **Login page `customCss`** may not contain the sequence `</`.

---

## New opt-in features

- **`#[Guarded]`** (`Melodic\Data\Guarded`) — mark model properties that must
  never bind from request input (mass-assignment defense). See
  [`docs/validation.md`](docs/validation.md).
- **`RedirectResponse::local($path)`** — redirect that rejects off-site
  targets.
- **`RefreshTokenService::validateAndRotate($token)`** — atomic
  validate+rotate; pass a `DbContextInterface` as the service's third
  constructor argument to run it in a transaction.
- **`EventDispatcher::removeListener()`** — deregister a listener.
