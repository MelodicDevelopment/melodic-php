# Melodic PHP Framework — Status & Review

An honest assessment of the framework's architecture, security posture, and known
limitations, as of **4.1.0**. Where this document makes a claim, it reflects what
the code actually does today — not aspirations. The limitations listed here were
each confirmed against the source or by running the code.

---

## Strengths

- **Clean layering.** Controller → Service → Query/Command → DbContext is explicit
  and traceable, with no hidden magic.
- **Modern PHP 8.2+.** Enums, readonly properties, constructor promotion, `match`,
  `declare(strict_types=1)` throughout.
- **Pragmatic CQRS.** Direct Query/Command instantiation in services — structure
  without a mediator or event bus.
- **Small, capable DI container.** Auto-wiring, circular-dependency detection,
  interface bindings, singletons, ServiceProviders, PSR-11 compatible.
- **Typed input.** Model binding coerces and validates request bodies, query
  strings (`#[FromQuery]`) and route parameters; malformed input is a 400 with
  field-level errors, never an uncaught `TypeError`.
- **Composable middleware pipeline**, immutable Request/Response, a priority event
  dispatcher, structured exception handling, and pluggable cache/session/logging
  (with PSR-3 / PSR-16 adapters).
- **Batteries for the common cases** (4.1): plain-SQL migrations, pagination,
  CSRF, rate limiting and security-header middleware, named routes, environment
  placeholders in config.
- **Refresh-token rotation with reuse detection** that holds up inside a
  transaction (the family revocation is re-applied after a rollback).
- **Testable in-process.** `Application::handle()` returns the `Response` for a
  `Request` without touching output.

## Security posture

These are **enforced in code**, not just intended:

- **JWT/OIDC:** signature verified against the provider JWKS; **issuer (`iss`)
  checked** against the discovery document, whose host must match the discovery
  URL; **`exp` required**; audience validated (falling back to `client_id`);
  **`nonce` round-trip** on login; the callback accepts only an `id_token`. The
  key set is refetched when a token names an unknown key id. Local HS256 tokens
  also require `exp`.
- **Signing keys:** `LocalAuthConfig` rejects empty/short (<32 char) HMAC secrets.
- **OAuth2/OIDC authorization-code flow** uses `response_type=code` and **PKCE
  (S256)**; `state`, nonce and the PKCE verifier are compared with `hash_equals()`
  and consumed once.
- **Cookies:** the auth cookie, the PHP session cookie, the refresh cookie and
  `Response::withCookie()` all default to `Secure` + `HttpOnly` + `SameSite=Lax`
  (configurable; disable `Secure` only for local HTTP dev).
- **Sessions:** strict mode and cookies-only; ID regenerated after login through
  the session abstraction; logout destroys the session and expires its cookie.
- **CSRF:** logout is a CSRF-protected `POST`; the local login form carries a
  token; `CsrfMiddleware` protects every other cookie-authenticated
  state-changing request.
- **Authorization:** entitlement requirements are enforced regardless of the
  `requireAuthentication` flag (anonymous → 401, lacking → 403).
- **Open redirects:** post-login targets and `RedirectResponse::local()` accept
  only same-origin paths.
- **Error detail:** 5xx messages and `SecurityException` reasons are masked
  outside debug mode; the login page never renders text taken from the URL.
- **Mass assignment:** `#[Guarded]` properties never bind from input.
- **Transport:** all OAuth/OIDC HTTP goes through one helper with TLS peer
  verification **and** HTTP status checking.
- **Files:** cache directory `0700` / entries `0600` with atomic writes; OIDC
  cache `0700`; log directory `0750` / files `0640`; CR/LF stripped from log
  lines.
- **Other:** parameterized SQL throughout, `$this->e()` HTML-escaping helper for
  templates, templates rendered in an isolated scope, scaffolding names validated
  before any file is written.

## Known limitations / open items

Honest gaps a consumer should be aware of.

**Behaviour that can surprise**

- **An exception skips a middleware's "after" code.** Error responses get CORS
  and security headers because those middleware implement
  `ResponseDecoratorInterface`; other response-modifying middleware (including
  `RateLimitMiddleware`'s `X-RateLimit-*` headers) do not apply to them unless
  they implement it too.
- **Templates are not auto-escaped** — use `$this->e()` for every dynamic value
  (by design).
- **Lists of models are hydrated only when declared** with `#[ListOf(X::class)]`;
  a plain `array` property keeps the raw decoded arrays.
- **Environment placeholders yield strings** (only a whole-value `true`/`false`
  becomes a boolean) — numeric config read through a placeholder needs a cast.
- **`config.dev.json` is loaded in every environment** if the file is present.
- **Relative `logging.path` and `auth.oidcCacheDir`** resolve against PHP's
  working directory, not the project root.
- **`Container::has()` answers "resolvable", not "registered"** — use
  `isBound()`.
- **`Configuration` dot-notation** cannot address keys that contain a literal dot.
- **Without `ext-mbstring`**, `#[MinLength]` / `#[MaxLength]` count bytes, not
  characters.

**Missing pieces**

- **No trusted-proxy handling.** `Request::ip()` is `REMOTE_ADDR`; forwarded
  headers are ignored (so `RateLimitMiddleware` needs a `keyResolver` behind a
  proxy).
- **One OIDC `audience`** validates both the login `id_token` and API bearer
  tokens.
- **No first-party test helpers** beyond `Application::handle()` and the
  in-memory cache/session/logger doubles.
- **No file storage abstraction, queue, or HTTP response caching.**
- **`make:entity` output is a skeleton**: `Create`/`Update` commands contain TODO
  SQL and controllers take no request DTOs.
- A few methods exist only on concrete classes: `EventDispatcher::removeListener()`,
  `DbContext::cursor()`, `UserContext::getProvider()`.

See [roadmap.md](roadmap.md) for what is planned.

## Testing & tooling

- Test suite: **810+ tests** (`composer test`, PHPUnit 11), SQLite-backed
  DbContext tests, process-isolated tests for session/OAuth flows.
- CI matrix: PHP 8.2 / 8.3 / 8.4, running the tests and static analysis.
- `phpstan.neon` at level 6 (`composer analyse`) — **clean, 0 errors**.
- [`MELODIC_PHP.md`](../MELODIC_PHP.md)'s worked example is exercised end to end
  against the framework when the reference is revised.

## Overall

The architecture and code quality are genuinely strong — clean layering, pragmatic
CQRS, exemplary modern PHP. The 3.0 and 4.0 hardening passes made the security
defaults sound and enforced; 4.1 closed most of the feature gaps a small
application runs into (migrations, pagination, CSRF, rate limiting, in-process
testing). What remains is a short list of sharp edges, documented above, and the
optional subsystems on the roadmap. This is a credible, lightweight framework —
between Slim and Laravel — suitable for production use when configured with the
secure defaults it ships.

See [`MIGRATION-3.0.md`](../MIGRATION-3.0.md) and
[`MIGRATION-4.0.md`](../MIGRATION-4.0.md) for the breaking changes in each major
version; 4.1 is backwards compatible with 4.0.
