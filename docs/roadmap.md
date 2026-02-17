# Melodic PHP Framework — Feature Roadmap

A prioritized list of missing features and improvements, organized by impact.

---

## High Priority

### Request Validation

Every controller action that accepts input needs validation, and there's no built-in support. Controllers currently rely on ad-hoc manual checks.

- Declarative rule definitions (`required`, `string`, `email`, `min`, `max`, `in`, `regex`, etc.)
- Structured validation error responses (field-level error messages, 422 status code)
- Integration with `BadRequestException` / `HttpException` hierarchy
- Reusable rule sets (e.g., `CreateUserRequest` classes that define rules and can be type-hinted in controller actions)
- Custom rule support

### Caching Abstraction

No caching layer exists. OIDC discovery documents are cached to `sys_get_temp_dir()` with no expiry management.

- `CacheInterface` with `get`, `set`, `has`, `delete`, `clear`
- File-based implementation with TTL support
- In-memory (array) implementation for testing
- `CacheServiceProvider` with configurable driver and path
- Useful for query result caching in services, OIDC discovery, and rate limiting storage

### Rate Limiting Middleware

API endpoints have no protection against abuse.

- Token bucket or fixed window algorithm
- Configurable limits per route or route group
- `Retry-After` and `X-RateLimit-*` response headers
- Throws 429 `HttpException` (new `TooManyRequestsException`)
- Pluggable storage backend (file-based, cache-based)

---

## Medium Priority

### Event Dispatcher

No mechanism for decoupled cross-cutting communication between services. Adding side effects (logging, notifications, cache invalidation) currently requires direct coupling.

- `EventDispatcherInterface` with `dispatch()` and `listen()`
- Listener registration via DI container (supports auto-wiring)
- `EventServiceProvider` for grouping listener registrations
- Useful for audit trails, notifications, and keeping services focused

### CLI Tooling

No command-line interface for common development tasks. Everything is manual.

- Console application runner with command registration
- Built-in commands: `serve` (wraps PHP built-in server), `routes:list`, `cache:clear`
- Code generation: `make:controller`, `make:service`, `make:query`, `make:command`, `make:middleware`
- Extensible command interface for app-specific commands

### Database Migrations

Schema management is entirely manual. The example app creates tables inline during bootstrap.

- Migration files with `up()` and `down()` methods
- Migration runner tracking applied migrations in a `migrations` table
- CLI commands: `migrate`, `migrate:rollback`, `migrate:status`
- Timestamped migration file generation

### Pagination

No built-in support for paginating query results.

- `PaginatedResult` DTO with items, total, page, per-page, and page count
- Helper methods on `DbContext` or a `Paginator` class
- Standard query parameter conventions (`?page=1&per_page=25`)
- Link header or metadata generation for API responses

---

## Lower Priority

### CSRF Protection

MVC form submissions have no CSRF token verification.

- Token generation and session storage
- `CsrfMiddleware` for validating tokens on state-changing requests
- Template helper for embedding tokens in forms
- Automatic exclusion of API routes (already use JWT)

### File Storage Abstraction

No abstraction for file operations.

- `StorageInterface` with `put`, `get`, `delete`, `exists`, `url`
- Local filesystem driver
- Configurable disk definitions
- Upload handling utilities

### Queue / Background Jobs

No support for deferring work to background processing.

- `JobInterface` with `handle()` method
- Database-backed queue driver (uses existing `DbContext`)
- Synchronous driver for testing
- CLI worker command for processing queued jobs
- Retry and failure handling

### Testing Utilities

No framework-level testing support.

- `TestCase` base class with app bootstrapping
- HTTP testing helpers (simulated requests without a server)
- In-memory `DbContext` seeding utilities
- Middleware testing helpers
- `NullLogger` is already available as a test double

### Response Caching / ETags

No HTTP-level caching support.

- `ETag` generation middleware
- `Cache-Control` header helpers
- Conditional response handling (`304 Not Modified`)

---

## Notes

- Features should follow existing conventions: PascalCase classes, camelCase methods, ServiceProvider pattern for registration, interface-first design
- Each feature should work independently — no feature should require another unreleased feature
- PSR compatibility should be maintained where applicable (PSR-3 logging is already in place, validation could align with common conventions)
