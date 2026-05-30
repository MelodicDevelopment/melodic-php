# Melodic PHP Framework — Status & Review

An honest assessment of the framework's architecture, security posture, and known
limitations. Updated for the **3.0 hardening pass**, which fixed a set of security
and correctness issues found in a cross-checked code review. Where this document
makes a claim, it reflects what the code actually does today — not aspirations.

---

## Strengths

- **Clean layering.** Controller → Service → Query/Command → DbContext is explicit
  and traceable, with no hidden magic.
- **Modern PHP 8.2+.** Enums, readonly properties, constructor promotion, `match`,
  `declare(strict_types=1)` throughout.
- **Pragmatic CQRS.** Direct Query/Command instantiation in services — structure
  without a mediator or event bus.
- **Small, capable DI container.** Auto-wiring, circular-dependency detection,
  interface bindings, singletons, ServiceProviders.
- **Composable middleware pipeline**, immutable Request/Response, attribute-based
  validation, a priority event dispatcher, structured exception handling, and
  pluggable cache/session/logging.

## Security posture (as of 3.0)

These are **enforced in code**, not just intended:

- **JWT/OIDC:** signature verified against the provider JWKS; **issuer (`iss`)
  checked** against the discovery document; **`exp` required**; audience validated
  (falling back to `client_id`). Local HS256 tokens also require `exp`.
- **Signing keys:** `LocalAuthConfig` rejects empty/short (<32 char) HMAC secrets.
- **OAuth2/OIDC authorization-code flow** uses `response_type=code` and **PKCE
  (S256)**; `state` and the PKCE verifier are compared with `hash_equals()` and
  consumed once.
- **Cookies:** the auth cookie and the PHP session cookie default to
  `Secure` + `HttpOnly` + `SameSite=Lax` (configurable; disable `Secure` only for
  local HTTP dev).
- **Sessions:** ID regenerated after login through the session abstraction
  (covers non-native drivers).
- **CSRF:** logout is a CSRF-protected `POST`; login forms reuse a single stored
  token (`hash_equals` validation, single-use).
- **Transport:** all OAuth/OIDC HTTP goes through one helper with TLS peer
  verification **and** HTTP status checking.
- **Other:** parameterized SQL throughout, `$this->e()` HTML-escaping helper for
  templates, CR/LF stripping in log interpolation, private (`0700`) cache dirs.

## Known limitations / open items

Honest gaps a consumer should be aware of:

- **Static analysis is not clean.** `composer analyse` (PHPStan level 6) currently
  reports ~143 `missingType.iterableValue`-style warnings. These are type-annotation
  gaps, not runtime bugs, and are being driven to zero in a dedicated pass.
- **No OIDC `nonce` validation.**
- **No rate limiting** on local authentication.
- **No Content-Security-Policy** header helper.
- **No database migrations** or schema tooling.
- **No first-party test utilities** (mock request builders, integration base class).
- **Templates are not auto-escaped** — use `$this->e()` for user data (by design).
- **`Container::has()` answers "resolvable", not "registered"** (returns true for
  any existing class).
- **`Configuration` dot-notation** cannot address keys that contain a literal dot.

## Testing & tooling

- Test suite: **654+ tests** (`composer test`, PHPUnit 11), SQLite-backed
  DbContext tests, process-isolated tests for session/OAuth flows.
- CI matrix: PHP 8.2 / 8.3 / 8.4.
- `phpstan.neon` at level 6 (`composer analyse`) — see "Known limitations".

## Overall

The architecture and code quality are genuinely strong — clean layering, pragmatic
CQRS, exemplary modern PHP. After the 3.0 hardening pass the security defaults are
sound and enforced. The main outstanding work is finishing the PHPStan cleanup and
the optional feature gaps above. This is a credible, lightweight framework — between
Slim and Laravel — suitable for production use when configured with the secure
defaults it now ships.

See [`MIGRATION-3.0.md`](../MIGRATION-3.0.md) for the breaking changes in 3.0.
