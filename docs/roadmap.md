# Melodic PHP Framework — Feature Roadmap

What has shipped, and what is still open, as of **4.1.0**. Items are grouped by
status and then by impact. See [`CHANGELOG.md`](../CHANGELOG.md) for the detail
behind each shipped item.

---

## Shipped

| Feature | Since | Where |
|---|---|---|
| Request validation — attribute rules on DTOs, automatic model binding, 400 with field-level errors | 1.x; nullable-by-default 3.0; `RuleInterface`, `#[Url]`, `#[Uuid]`, `#[Date]`, nested validation, `#[FromQuery]` 4.1 | [validation.md](validation.md) |
| Caching abstraction — `CacheInterface`, `FileCache`, `ArrayCache`, `CacheServiceProvider`; PSR-16 adapter | 1.x; adapter 4.1 | [cache.md](cache.md) |
| Event dispatcher — priorities, propagation stop, `EventServiceProvider` | 1.x | [events.md](events.md) |
| CLI — console runner, `route:list`, `cache:clear`, `make:project`, `make:entity`, `make:config`, `claude:install` | 1.x | [console.md](console.md) |
| CSRF protection — login/logout forms (3.0); generic `CsrfMiddleware` with `$csrfField` view helper (4.1) | 3.0 / 4.1 | [authentication.md](authentication.md) |
| Rate limiting — `RateLimitMiddleware` (fixed window, cache-backed, `Retry-After` / `X-RateLimit-*`) | 4.1 | [`MELODIC_PHP.md`](../MELODIC_PHP.md#12-middleware) |
| Security headers — `SecurityHeadersMiddleware` (nosniff, frame options, referrer policy, optional HSTS/CSP) | 4.1 | [`MELODIC_PHP.md`](../MELODIC_PHP.md#12-middleware) |
| Database migrations — plain-SQL `Migrator`, `migrate`, `migrate:rollback`, `migrate:status`, `make:migration` | 4.1 | [console.md](console.md) |
| Pagination — `Paginator` / `PaginatedResult` | 4.1 | [`MELODIC_PHP.md`](../MELODIC_PHP.md#9-data-access-queries-commands-services) |
| In-process request handling for tests — `Application::handle(Request): Response` | 4.1 | [`MELODIC_PHP.md`](../MELODIC_PHP.md#15-testing) |
| Named routes, URL generation, parameter constraints, `PATCH` in `apiResource()` | 4.1 | [`README.md`](../README.md) |
| Environment placeholders in config (`${VAR}`, `${VAR:-default}`) | 4.1 | [configuration.md](configuration.md) |
| PSR interop — PSR-11 container, PSR-3 and PSR-16 adapters | 4.1 | [`CHANGELOG.md`](../CHANGELOG.md) |
| Lists of models in request bodies — `#[ListOf(X::class)]`, hydrated and validated with indexed keys | 4.1 | [validation.md](validation.md) |
| CORS / security headers on error responses — `ResponseDecoratorInterface` | 4.1 | [error-handling.md](error-handling.md) |

---

## Open — higher priority

### Trusted-proxy support

`Request::ip()`, `isSecure()` and `host()` deliberately ignore forwarded
headers. Behind a load balancer the application must read them itself.

- Configurable trusted proxy list
- `X-Forwarded-For` / `-Proto` / `-Host` honoured only from trusted peers

### First-party test utilities

`Application::handle()` and the `ArrayCache` / `ArraySession` / `NullLogger`
doubles exist; convenience on top of them does not.

- `TestCase` base class that boots the application
- Request builder (`get()`, `postJson()`, `actingAs()`) and response assertions
- Database seeding helpers

---

## Open — medium priority

### Finer-grained code generation

- `make:controller`, `make:service`, `make:query`, `make:command`,
  `make:middleware`
- `make:entity` options for fields, so generated commands contain real SQL
  and controllers take request DTOs
- `serve` command wrapping PHP's built-in server

### Auth

- A public helper for issuing the application's own access JWT after a
  refresh (`LocalJwtIssuer` is internal)
- Separate audiences for the login `id_token` and API bearer tokens
- Configurable logout path and post-logout redirect

### Path resolution

- Resolve relative `logging.path` and `auth.oidcCacheDir` against the project
  root instead of the working directory (needs a migration path for existing
  apps)

---

## Open — lower priority

### File storage abstraction

- `StorageInterface` with `put`, `get`, `delete`, `exists`, `url`
- Local filesystem driver, configurable disks
- An uploaded-file object on top of `Request::file()`

### Queue / background jobs

- `JobInterface` with `handle()`
- Database-backed driver, synchronous driver for tests
- CLI worker with retry and failure handling

### Response caching / ETags

- `ETag` generation middleware
- `Cache-Control` helpers and `304 Not Modified` handling

---

## Notes

- Features follow existing conventions: PascalCase classes, camelCase methods,
  the ServiceProvider pattern for registration, interface-first design.
- Each feature works independently — none requires another unreleased feature.
- PSR compatibility is maintained where it costs nothing (PSR-11 container;
  PSR-3 and PSR-16 through adapters).
- A shipped feature is documented in [`MELODIC_PHP.md`](../MELODIC_PHP.md) in
  the same release, because that file is what consumer projects read.
