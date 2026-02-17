# Melodic PHP Framework Review

Comprehensive evaluation of the Melodic PHP framework covering architecture, code quality, security, performance, missing features, and Packagist publishing readiness.

---

## Strengths

### Architecture is Solid

The layered Controller → Service → Query/Command → DbContext flow is clean, explicit, and easy to follow. There's no hidden magic — developers can trace a request from entry to database and back without guessing.

### Excellent PHP 8.2+ Usage

Enums (`HttpMethod`, `LogLevel`), readonly properties, constructor promotion, `match` expressions, `declare(strict_types=1)` everywhere. The codebase reads like modern PHP should.

### DI Container is Well-Designed

Auto-wiring with circular dependency detection, interface bindings, singleton support, and ServiceProviders for modular registration. It's simple but covers real-world needs.

### CQRS Without Over-Engineering

No mediator, no event bus — just direct Query/Command instantiation in services. This is the right level of abstraction for most PHP apps. It provides structure without ceremony.

### Middleware Pipeline is Elegant

The recursive anonymous-class approach in `Pipeline.php` is compact and correct. Composable at both global and route-group levels.

### Security Fundamentals are Present

JWT validation with audience/issuer checks, PKCE for OAuth2/OIDC, timing-safe `hash_equals()` for state validation, secure cookie defaults, parameterized SQL queries, `htmlspecialchars()` in templates. CSRF token protection on auth forms with single-use tokens. Session ID regeneration after successful authentication to prevent session fixation.

### Immutable Request/Response

Builder-pattern modifications (`withAttribute()`, `withHeader()`) prevent accidental mutation — a good design choice.

### Comprehensive Example App

The example demonstrates nearly every framework feature with embedded documentation pages. Good for onboarding.

### Attribute-Based Validation

PHP 8.2+ attribute rules (`#[Required]`, `#[Email]`, `#[MinLength]`, etc.) provide clean, declarative validation on DTOs. `Validator` class supports both object and array validation with structured `ValidationResult` and throwable `ValidationException`.

### Event Dispatcher

Simple priority-based event system with `listen()` and `dispatch()`. Supports propagation stopping. Registered via `EventServiceProvider` for container integration.

### Structured Exception Handling

`ExceptionHandler` auto-detects JSON vs HTML based on Accept header and request path. Maps exception types to HTTP status codes (422, 401, 403, 500). Debug mode shows full trace, production mode returns generic messages. Integrated into `Application::run()`.

### Pluggable Cache and Session

`CacheInterface` (PSR-16 style) with `FileCache` and `ArrayCache` drivers. `SessionInterface` with `NativeSession` and `ArraySession` drivers. `SessionManager` implements `SessionInterface` for backward compatibility. All registered via ServiceProviders.

---

## Bugs & Issues (Resolved)

All bugs identified during the initial review have been fixed.

### ~~1. OAuthClient Constructor Mismatch~~ — FIXED

**File:** `src/Security/OAuthClient.php`

Constructor parameter type corrected from `AuthConfig` to `AuthProviderConfig` so the stored config is properly typed and usable.

### ~~2. DbContext Hydration Creates ReflectionClass Per Row~~ — FIXED

**File:** `src/Data/DbContext.php`

`ReflectionClass` is now created once outside the `array_map` loop and reused for all rows. Removed deprecated `setAccessible(true)` calls (unnecessary since PHP 8.1).

### ~~3. ViewEngine State Persists Between Renders~~ — FIXED

**File:** `src/View/ViewEngine.php`

`$bodyContent`, `$sections`, and `$currentSection` are now reset at the start of each `render()` call, preventing cross-contamination between templates.

### ~~4. No Type Casting During Hydration~~ — FIXED

**File:** `src/Data/DbContext.php`

New `castValue()` method uses `ReflectionNamedType` to detect expected property types and casts PDO string values to `int`, `float`, `bool`, or `string` as needed. Handles nullable types gracefully.

### 5. Configuration Dot-Notation Can Fail

**File:** `src/Core/Configuration.php:42-55`

If an intermediate key resolves to a non-array value, the next level of traversal fails silently or errors. No guard against this. *(Not yet addressed)*

---

## Security Gaps

| Issue | Severity | Status |
|-------|----------|--------|
| ~~No CSRF protection on auth forms~~ | Medium | **FIXED** — `CsrfToken` class added with single-use tokens, `hash_equals()` validation |
| No OIDC `nonce` validation | Medium | Open |
| ~~No session regeneration after login~~ | Medium | **FIXED** — `session_regenerate_id(true)` after successful auth |
| No rate limiting on local auth | Medium | Open |
| Error messages leak provider details in URL params | Low | Open |
| No Content-Security-Policy headers | Low | Open |

---

## Performance Concerns

### ~~1. Reflection Per Row~~ — FIXED

`DbContext::query()` now creates the `ReflectionClass` once and reuses it for all rows in the result set.

### 2. Container Reflection Not Cached for Transients

Every `get()` call on a non-singleton re-reflects the class. Caching constructor parameter metadata per class would help.

### 3. Pipeline Creates N Anonymous Class Instances

One per middleware in the stack. Acceptable for typical stacks (5-10), but an iterative approach would be leaner.

### 4. No Template Compilation/Caching

ViewEngine re-processes `.phtml` files on every request. Fine for small apps, but a compiled template cache would improve throughput.

### 5. OIDC Discovery Fetched via file_get_contents

Works but lacks connection pooling. Consider cURL or a lightweight HTTP client.

---

## Missing Features

### ~~High Priority~~ — COMPLETE

All high-priority features have been implemented:

- ~~**Validation layer**~~ — Attribute-based validation with `#[Required]`, `#[Email]`, `#[MinLength]`, `#[MaxLength]`, `#[Min]`, `#[Max]`, `#[Pattern]`, `#[In]` rules
- ~~**Event/Hook system**~~ — Event dispatcher with priority-based listeners and propagation stopping
- ~~**Error/Exception handler**~~ — `ExceptionHandler` with JSON/HTML auto-detection, debug/production modes, exception-to-status-code mapping
- ~~**Request validation / form requests**~~ — `Validator::validateArray()` supports validating raw input against DTO class definitions

### ~~Medium Priority~~ — MOSTLY COMPLETE

- **Database migrations** — No schema management tool. Not strictly required for a micro-framework, but expected if you're providing a DbContext.
- ~~**CLI / Console component**~~ — `Console` runner with `Command` base class, `RouteListCommand`, and `CacheClearCommand`
- ~~**Caching abstraction**~~ — `CacheInterface` with `FileCache` and `ArrayCache` drivers, `CacheServiceProvider`
- ~~**Session abstraction**~~ — `SessionInterface` with `NativeSession` and `ArraySession` drivers, backward-compatible `SessionManager`
- **Testing utilities** — No test helpers, mock request builders, or integration test base class.

### Lower Priority

- Rate limiting middleware
- Request/response logging middleware
- File upload handling
- Pagination helpers for query results
- Model relationships / query builder (if competing with larger frameworks)

---

## Packagist Publishing Readiness

| Requirement | Status |
|-------------|--------|
| `composer.json` with proper metadata | **Done** — includes `keywords`, `authors`, `require-dev` |
| `LICENSE` file | **Done** — MIT license |
| Unit tests (`tests/`) | **Done** — 310 tests, 566 assertions |
| `phpunit.xml` | **Done** |
| CI/CD (`.github/workflows/`) | **Done** — PHP 8.2, 8.3, 8.4 matrix |
| Static analysis (`phpstan.neon`) | **Done** — level 6, `composer analyse` script |
| `CHANGELOG.md` | **Done** — full 1.0.0 changelog |
| `CONTRIBUTING.md` | **Missing** |
| Git tags / semantic versioning | **Not tagged** |
| README.md | Present and comprehensive |

### Test Coverage

| Test File | Coverage Area |
|-----------|--------------|
| `tests/DI/ContainerTest.php` | Auto-wiring, singletons, bindings, circular dependency detection |
| `tests/Routing/RouterTest.php` | Route registration, matching, parameters, groups, apiResource |
| `tests/Http/Middleware/PipelineTest.php` | Middleware chaining, short-circuit, request/response modification |
| `tests/Data/DbContextTest.php` | Query, queryFirst, command, transactions, hydration with type casting |
| `tests/Core/ConfigurationTest.php` | JSON loading, dot-notation, defaults, has/set |
| `tests/Http/RequestTest.php` | Method/path parsing, query params, body, headers, immutability |
| `tests/Http/ResponseTest.php` | Status codes, headers, body, immutability |
| `tests/Http/JsonResponseTest.php` | JSON encoding, content-type, nested data |
| `tests/Validation/ValidatorTest.php` | All attribute rules, object and array validation, ValidationResult |
| `tests/Error/ExceptionHandlerTest.php` | JSON/HTML detection, debug/production modes, status code mapping |
| `tests/Event/EventDispatcherTest.php` | Dispatch, priority ordering, propagation stopping |
| `tests/Cache/ArrayCacheTest.php` | In-memory cache get/set/TTL/delete/clear |
| `tests/Cache/FileCacheTest.php` | File-based cache get/set/TTL/delete/clear |
| `tests/Session/ArraySessionTest.php` | Session get/set/has/remove/destroy |
| `tests/Console/ConsoleTest.php` | Command registration, execution, arguments, help output, table formatting |
| `tests/View/ViewEngineTest.php` | Template rendering, layouts, cached rendering, cache fallback |

---

## Recommendations

### ~~Phase 1 — Fix Before Publishing~~ — COMPLETE

All Phase 1 items have been addressed:

1. ~~Fix the OAuthClient constructor bug~~ — Fixed type to `AuthProviderConfig`
2. ~~Cache ReflectionClass in DbContext hydration loop~~ — Moved outside loop
3. ~~Reset ViewEngine state between renders~~ — Reset at start of `render()`
4. ~~Add type casting during model hydration~~ — `castValue()` with `ReflectionNamedType`
5. ~~Add LICENSE file, `authors`, and `keywords` to composer.json~~ — Done
6. ~~Write unit tests for Container, Router, Pipeline, DbContext, Configuration, Request/Response~~ — 173 tests passing
7. ~~Set up PHPUnit + GitHub Actions CI~~ — PHPUnit 11, PHP 8.2/8.3/8.4 matrix
8. ~~Add CSRF protection to auth forms~~ — `CsrfToken` class with single-use tokens
9. ~~Regenerate session ID after successful authentication~~ — `session_regenerate_id(true)`

### ~~Phase 2 — Feature Gaps~~ — COMPLETE

All Phase 2 items have been addressed:

1. ~~Build a validation service~~ — Attribute-based with 8 rule types, `Validator`, `ValidationResult`, `ValidationException`
2. ~~Proper exception handler with JSON/HTML response modes~~ — `ExceptionHandler` integrated into `Application::run()`
3. ~~Add a simple event dispatcher~~ — `EventDispatcher` with priority and propagation stopping, `EventServiceProvider`
4. ~~Cache abstraction (PSR-16 SimpleCache interface)~~ — `CacheInterface`, `FileCache`, `ArrayCache`, `CacheServiceProvider`
5. ~~Session abstraction with pluggable drivers~~ — `SessionInterface`, `NativeSession`, `ArraySession`, backward-compatible `SessionManager`

### ~~Phase 3 — Polish~~ — MOSTLY COMPLETE

1. ~~PSR-7 compatibility (or at least document why you chose not to)~~ — Documented in README.md under "Architecture Decisions"
2. ~~Add phpstan at level 6+ and fix all issues~~ — `phpstan.neon` at level 6, `composer analyse` script
3. ~~Template caching in ViewEngine~~ — `renderCached()` with `CacheInterface` integration and TTL support
4. ~~CLI component for common tasks~~ — `Console` runner, `Command` base class, `RouteListCommand`, `CacheClearCommand`
5. ~~CHANGELOG.md~~ — Full 1.0.0 changelog added. Semantic version tagging not yet done.

---

## Overall Assessment

The architecture and code quality are genuinely strong — this isn't a toy framework. The layering is clean, the CQRS approach is pragmatic, the auth system is comprehensive, and the PHP 8.2+ usage is exemplary. The codebase is consistent and readable.

With all three phases largely complete, the framework is ready for publishing: bugs fixed, security hardened, 310 passing tests across 16 test files, CI pipeline in place, PHPStan at level 6, proper licensing, CHANGELOG, and a full feature set including validation, events, caching, session management, structured error handling, view caching, and a CLI console. The remaining items — database migrations, testing utilities, semantic version tagging, and the lower-priority features — are enhancements for future releases.

This is a credible, lightweight alternative to the larger frameworks — positioned somewhere between Slim and Laravel, with better CQRS support than either.
