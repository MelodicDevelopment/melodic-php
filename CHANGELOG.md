# Changelog

All notable changes to the Melodic PHP framework are documented here. The format
is based on [Keep a Changelog](https://keepachangelog.com/), and this project
adheres to [Semantic Versioning](https://semver.org/).

## [4.1.0] — Review fixes and parity features

Backwards compatible with 4.0. Nothing was removed; a few leftovers are
deprecated for removal in 5.0. See "Behavior changes" for the handful of
fixes that change observable behavior.

### Fixed

- **`ViewEngine`**: templates render in an isolated scope. A view variable
  named `path` previously replaced the template file being included (and could
  disclose an arbitrary local file if view data keys came from user input).
- **Model binding**: nested `Model` properties hydrate from JSON objects; a
  wrong-shaped value for an `array`, nested-model, or union-typed property is
  a **400** instead of an uncaught `TypeError` (500); integer strings that
  overflow `PHP_INT_MAX` are rejected instead of silently clamped.
- **Enums / dates round-trip**: `toPascalArray()` / `toUpdateArray()` convert
  backed enums to their value and `DateTime` objects to `Y-m-d H:i:s` (PDO
  cannot bind objects); JSON output renders `DateTime` as ISO-8601.
- **`DbContext`** normalizes parameters: `false` binds as `0` (was `''`, which
  strict MySQL rejects), enums and dates bind as scalars.
- **Routing**: captured route parameters are URL-decoded; a trailing slash
  matches the same route (`/users/` ≡ `/users`).
- **`Request`**: a request target starting with `//` no longer loses its first
  path segment; `Bearer` scheme matching is case-insensitive.
- **Unsupported HTTP methods** (`PROPFIND`, …) get a clean **501** instead of
  an uncaught `ValueError`.
- **Scaffolding**: `make:project` MVC/full projects work out of the box —
  `Application` now binds a default `ViewEngine` (`{basePath}/views`, or the
  `views.path` config key). Stubs no longer emit an unused `jwt` config block,
  and views use `$this->e()`.
- **`WebAuthenticationMiddleware`**: a `SecurityException` thrown by the
  application is no longer swallowed and turned into a login redirect; the
  post-login target keeps its query string.
- **`AuthorizationMiddleware`** can be used as a route middleware with
  per-route requirements: `attributes: ['entitlements' => ['admin']]`.
- **`CorsMiddleware`** appends to an existing `Vary` header instead of
  replacing it, and sends `Vary: Origin` for disallowed origins too.
- **Refresh tokens**: reuse detection survives the transaction —
  `validateAndRotate()` re-applies the family revocation after the rollback
  its own exception causes (`RefreshTokenReuseException`).
- **`OAuthClient`** sends a `User-Agent` (GitHub's API rejects requests
  without one).
- **`ExceptionHandler`** logs a mapped exception with the status its mapper
  returned instead of as a 500.
- **`make:entity`** keeps inner capitals (`BlogPost` no longer becomes
  `Blogpost`) and generates a camelCase list variable; `make:project` requires
  the generating framework's minor version (`^4.1`).
- `mbstring` is optional again (`#[MinLength]`/`#[MaxLength]` and console
  tables fall back to byte length).
- Array-valued `?error[]=` / refresh-token cookie no longer cause a 500.
- **`SessionServiceProvider`** honors the `session.cookie*` config keys.
- **`SecurityServiceProvider`** passes the bound `DbContext` to
  `RefreshTokenService` (so `validateAndRotate()` is actually transactional)
  and the bound logger to `RefreshTokenMiddleware`.

### Security

- **`CsrfMiddleware`** (new): CSRF protection for all cookie-authenticated
  state-changing requests, with a per-session token (`csrf_token` field or
  `X-CSRF-Token` header). `MvcController` passes `$csrfToken` / `$csrfField`
  to views. API paths and bearer-token requests are exempt.
- **`RateLimitMiddleware`** (new): cache-backed fixed-window limiter with
  `Retry-After` / `X-RateLimit-*` headers — e.g. throttle local login.
- **`SecurityHeadersMiddleware`** (new): `nosniff`, frame options, referrer
  policy, optional HSTS / CSP / Permissions-Policy.
- **Sessions** start with `session.use_strict_mode` and `use_only_cookies`
  enabled (attacker-supplied session ids are no longer adopted).
- **Login page**: the error text is read from the session, never from the
  `?error=` URL parameter (prevented arbitrary text on the real login page).
- **Logout** also destroys the server-side session and clears the refresh
  cookie.
- **OIDC cache**: an existing cache directory under the system temp dir must
  be owned by the current user and is tightened to `0700`; files are written
  atomically with `0600`.
- **`UserContext::fromClaims()`** coerces unexpected claim shapes instead of
  raising a `TypeError`.

### Added

- **`Application::handle(Request): Response`** and `boot()` — run a request
  without sending output (tests, long-running runtimes).
- **`Request`**: `ip()`, `host()`, `scheme()`, `isSecure()`, `queryString()`,
  `uri()`, `server()`, `file()`, `attributes()`, `isJson()`, `wantsJson()`.
- **`Response`**: `getHeader()`, `withoutHeader()`.
- **Routing**: parameter constraints (`{id:\d+}`), named routes with
  `Router::url()`, `PATCH` in `apiResource()`, and `#[FromQuery]` to bind a
  model from the query string.
- **Validation**: `RuleInterface`; `#[Url]`, `#[Uuid]`, `#[Date]` rules;
  nested models (and lists of models) are validated with dotted error keys.
- **Database**: plain-SQL migrations (`Migrator`, `migrate`,
  `migrate:rollback`, `migrate:status`, `make:migration`), `Paginator` /
  `PaginatedResult`, and `DbContext::cursor()` for streaming large results.
- **Configuration**: `${ENV_VAR}` / `${ENV_VAR:-default}` placeholders.
- **`app.errorsAsExceptions`** (opt-in): convert PHP warnings/notices to
  exceptions.
- **OIDC**: `claimMap` is applied to OIDC providers (e.g. Entra `roles` →
  entitlements); the key set is refetched when a token names an unknown key
  id (key rotation); a stale discovery/JWKS document is served when the
  provider is unreachable.
- **Logging**: `logging.maxFiles` retention; client errors (4xx) are logged
  as one line without a stack trace.
- **Views**: `$this->partial()`; `MvcController::redirect()`.
- **PSR interop**: the container implements PSR-11 (`psr/container` is now a
  dependency); `PsrLoggerAdapter` and `Psr16CacheAdapter` plug any PSR-3
  logger or PSR-16 cache into the framework.
- `Container::isBound()`; `claude:install` points projects at
  `MELODIC_PHP.md`.

- **Model binding**: `#[ListOf(Foo::class)]` hydrates (and validates) lists
  of nested models.
- **`ResponseDecoratorInterface`**: CORS and security headers are now also
  applied to error responses (router 404/405, thrown `HttpException`s), which
  previously carried none — cross-origin clients could not read error bodies.
- `ValidationException` thrown by application code maps to **400** with the
  same body as automatic validation failures.
- `ApiAuthenticationMiddleware` logs why a token was rejected (when a logger
  is bound); `UserContext::getProvider()` is populated for OIDC users.

### Behavior changes to be aware of

- `apiResource()` registers a sixth route (`PATCH {path}/{id}` → `update`).
- Route parameters arrive URL-decoded (`John%20Doe` → `John Doe`).
- `json_encode($model)` renders `DateTime` properties as ISO-8601 strings
  (`toArray()` still returns the objects).
- 4xx log lines no longer include a stack trace (unless `app.debug` is on).
- Failed logins redirect to `{loginPath}?error=1`; custom login renderers
  still receive the message text.
- `AuthorizationMiddleware` enforces a route's `entitlements` attribute.

### Deprecated (removal in 5.0)

- `Security\SafeRedirect` — use `RedirectResponse::isLocalPath()`.
- `Utilities::kill()`.
- `OAuthClient` instance methods (`getAuthorizationUrl()`, `exchangeCode()`);
  the static helpers stay.

## [4.0.0] — Review remediation (Phases 1–3)

Remediation of the remaining findings from the multi-agent code review.
Several defaults tightened toward "secure by default" — see
[`MIGRATION-4.0.md`](MIGRATION-4.0.md) for upgrade steps.

### Security

- **`FileCache`** tightens an *existing* cache directory to `0700` (previously
  only freshly created directories were hardened), writes entry files `0600`,
  and `clear()` sweeps orphaned `*.tmp` files.
- **Open redirect** on the post-login bounce closed: only local paths (single
  leading `/`, not `//` or `/\`) are stored and honored as the
  redirect-after-login target (`/\evil.com` previously bounced off-site).
- **Error detail masking**: `SecurityException` messages are replaced with a
  generic `Authentication failed.` outside debug mode; the refresh-token
  endpoint no longer lets a client distinguish "reuse detected" from "invalid
  token" (real reason is logged server-side).
- **Session logout**: `NativeSession::destroy()` and `SessionManager::destroy()`
  also expire the browser session cookie, so the old session id is not reused.
- **Scaffolding** (`make:entity` / `make:project` / `make:config`) rejects
  non-identifier names before writing any file (path-traversal guard).
- **OIDC**: full `nonce` round-trip (sent at login, verified against the
  id_token on callback); the callback **requires an `id_token`** and no longer
  falls back to `access_token`; the discovery issuer host must match the
  discovery URL host.
- **CSRF**: a mismatched token no longer consumes the stored token (forged
  POSTs cannot invalidate the user's open form).
- **`AuthorizationMiddleware`**: entitlement requirements are enforced even
  when `requireAuthentication` is `false` — anonymous requests now get 401.
- **`RefreshTokenService::validateAndRotate()`** runs validation + rotation in
  one DB transaction when the service is constructed with a `DbContext`.
- **`Response::withCookie()`** defaults to `secure: true`.
- **Mass assignment**: new `#[Guarded]` attribute — guarded model properties
  never bind from `fromArray()` input and never enter `toUpdateArray()`.
- **`FileLogger`**: `0750` log directory, `0640` log files, and whole-line
  log-injection sanitization; write failures are detected via return values.
- **Login page** `customCss` must not contain `</` (style-element breakout).
- **`RedirectResponse::local()`** rejects non-local redirect targets.

### Fixed

- Backed-enum and `DateTime`/`DateTimeImmutable` properties now hydrate from
  the database and coerce from request bodies; bad wire input returns **400**
  instead of an uncaught `TypeError` (500).
- Typed route params coerce to the action's declared type — `show(int $id)`
  with a non-numeric id returns **400** instead of 500; backed enums supported.
- `Configuration` merges: **lists replace wholesale** instead of merging by
  index, so a shorter override list no longer inherits stale base elements
  (e.g. CORS `allowedOrigins`).
- Route patterns escape literal segments (`/v1.0/` no longer treats `.` as a
  regex wildcard); route regexes compile once per route.
- `ExceptionHandler` treats only `/api` and `/api/…` as API paths (`/apiary`
  no longer gets JSON errors).
- DI `Container`: exceptions thrown by factories/constructors propagate instead
  of silently becoming a parameter's default value; resolution failures throw
  `ContainerException` / `CircularDependencyException` (both extend
  `RuntimeException`); `singleton(Interface, Concrete)` aliases the concrete
  class so direct `Concrete` type-hints share the singleton.
- `ViewEngine` unwinds all output buffers it opened when a template throws
  mid-section (previously leaked one buffer level per failed render).
- `#[Required]` rejects empty arrays; `#[In]` strict-comparison contract
  documented.
- `DbContext` applies `ERRMODE_EXCEPTION` + `FETCH_ASSOC` to injected `PDO`
  instances (transactions and hydration already assumed both).
- `ArrayCache` treats non-positive TTLs as already expired (parity with
  `FileCache`); `CorsMiddleware` emits preflight-only headers exclusively on
  `OPTIONS` responses.

### Changed (breaking)

- `Response::withCookie()` default is now `secure: true` — pass
  `'secure' => false` for plain-HTTP local development.
- Default refresh cookie name `kingdom_refresh` → `melodic_refresh` — set
  `auth.refreshToken.cookieName` explicitly to keep existing sessions valid.
- OIDC callback requires an `id_token`; IdPs returning only an `access_token`
  are rejected, and the issuer host must match the discovery host.
- `AuthorizationMiddleware` no longer lets anonymous requests through routes
  that declare entitlements, regardless of `requireAuthentication`.
- Config list values replace instead of merging by index.
- `#[Required]` now fails on `[]`.
- Empty `Service::__destruct()` removed — drop any `parent::__destruct()` call.

### Added

- `#[Guarded]` attribute (`Melodic\Data\Guarded`).
- `RedirectResponse::local()` / `RedirectResponse::isLocalPath()`.
- `RefreshTokenService::validateAndRotate()` (optional `DbContextInterface`
  constructor argument).
- `EventDispatcher::removeListener()`.
- Reflection caches in `Model` and `Container` (perf).

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
