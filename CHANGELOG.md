# Changelog

All notable changes to the Melodic PHP framework are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/), and this project
adheres to [Semantic Versioning](https://semver.org/).

## [3.0.0] — Security & correctness hardening

A focused hardening release addressing issues found in a cross-checked code
review. See [`MIGRATION-3.0.md`](MIGRATION-3.0.md) for upgrade steps.

### Security

- **JWT/OIDC validation** now requires a matching issuer (`iss`) and a present
  `exp`, and validates audience (falling back to `client_id`). Local HS256 tokens
  also require `exp`.
- **`LocalAuthConfig`** rejects empty or short (<32 char) HMAC signing keys.
- **Generic OAuth2 login** now sends `response_type=code` and uses **PKCE (S256)**
  (previously it sent neither, so the authorization-code flow could not work with
  most providers).
- **Cookies**: auth and session cookies default to `Secure` + `HttpOnly` +
  `SameSite=Lax`, sourced from config; the auth cookie now clears with matching
  attributes.
- **Logout** is a CSRF-protected `POST` (was a CSRF-able `GET`).
- **Session fixation**: ID regeneration after login goes through the session
  abstraction, covering non-native drivers.
- **OAuth/OIDC HTTP** consolidated into one TLS-verifying helper that also checks
  HTTP status, so error responses are no longer treated as success.
- **`FileCache`** hardened: `0700` directory, atomic writes with `LOCK_EX`,
  corruption-safe reads, and a `clear()` scoped to its own files.
- **OIDC** discovery/JWKS cache directory created `0700` and configurable.
- **Log injection**: CR/LF stripped from interpolated log context values.
- **`ApiAuthenticationMiddleware`** returns a generic 401 instead of leaking the
  internal validation reason.
- **CSRF token** is reused across login-page renders instead of churning.
- **Views**: added `$this->e()` HTML-escaping helper; `Utilities::kill()` escapes
  its output.

### Fixed

- **OIDC JWKS** are now parsed with a configurable default signing algorithm
  (`RS256`), so providers that omit the per-key `alg` (notably **Microsoft
  Entra**) validate instead of failing with "JWK must contain an alg parameter".
  Verified end to end against a live Entra tenant.
- **Validation** is nullable-by-default: optional fields with format rules no
  longer fail when omitted (only `#[Required]` rejects a missing value).
- **HEAD** requests no longer emit a response body (RFC 9110).
- **Model binding** coerces scalars to declared types and returns a **400** on
  uncoercible input instead of an uncaught `TypeError` (500); non-`Model` action
  parameters resolve from the container.
- **Routing** returns **405** with an `Allow` header when a path exists under a
  different method (was 404).
- **DI** `singleton()` drops a cached instance so re-registration takes effect.
- **DbContext** bool hydration handles driver string forms (`"f"`, `"false"`,
  `"0"`, `""` → false).
- **Scaffolding**: `make:project` pins the framework constraint to the running
  major version; `make:config` reports the correct path; `make:entity` emits
  clearly-marked TODO SQL instead of silently-invalid statements.

### Changed

- New config keys: `auth.cookieSecure` / `cookieSameSite` / `cookiePath` /
  `cookieDomain`, `auth.oidcCacheDir`, `auth.providers.*.signingAlg` (default
  `RS256`), and `session.*` equivalents.
- CORS wildcard origins match multi-level subdomains.

### Tooling

- **Static analysis is now clean**: `composer analyse` (PHPStan level 6) reports
  0 errors. The ~143 previously-open `missingType.iterableValue` warnings were
  resolved by adding generic `array<K, V>` annotations across the codebase (no
  behavior change).
