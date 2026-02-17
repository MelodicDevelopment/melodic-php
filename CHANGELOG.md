# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-02-17

### Added

#### Core
- Application bootstrap with configuration loading, middleware registration, service binding, route registration, and `run()` lifecycle
- Configuration class with JSON file loading, dot-notation access, defaults, `has()`, and `set()`

#### HTTP
- Immutable `Request` class wrapping PHP superglobals with method/path parsing, query parameters, body, headers, and attribute support
- `Response` class with status codes, headers, body, cookie support, and `send()`
- `JsonResponse` for JSON-encoded responses
- `RedirectResponse` for HTTP redirects
- Middleware pipeline with chaining, short-circuit support, and request/response modification
- `CorsMiddleware` for configurable cross-origin resource sharing
- `JsonBodyParserMiddleware` for parsing JSON request bodies with proper error handling
- `ErrorHandlerMiddleware` for catching exceptions and returning structured JSON or HTML error responses

#### Routing
- Route registration with HTTP method and URI pattern matching
- Route parameter extraction with named placeholders
- Route groups with shared prefixes and middleware
- `apiResource()` for RESTful CRUD route generation
- `RoutingMiddleware` for resolving routes through the DI container

#### Dependency Injection
- `Container` with auto-wiring that resolves constructor dependencies automatically
- Singleton registration with factory closures
- Interface-to-implementation bindings
- Circular dependency detection
- `ServiceProvider` base class for modular service registration

#### Data Access
- `DbContext` PDO wrapper with `query()`, `queryFirst()`, `command()`, and `transaction()` methods
- Model hydration with automatic type casting via `ReflectionNamedType`
- CQRS pattern with `QueryInterface` and `CommandInterface`
- `Model` base class with `fromArray()` and `toArray()` methods

#### Security
- JWT validation with `JwtValidator` supporting OIDC/JWKS key discovery
- OAuth2 PKCE flow for secure authorization code exchange
- Multi-provider authentication registry supporting OIDC (Google, Okta, Azure AD), OAuth2 social (GitHub, Facebook), and local username/password strategies
- `UserContext` built from JWT claims with entitlement checks and raw claim access via `getClaim()`/`getClaims()`
- `ApiAuthenticationMiddleware` for Bearer token validation on API routes
- `WebAuthenticationMiddleware` for cookie-based JWT authentication on web routes
- `OptionalWebAuthMiddleware` for public routes with optional user context
- `AuthorizationMiddleware` for entitlement-based access control
- CSRF token protection with single-use tokens and `hash_equals` validation
- Session ID regeneration after successful authentication
- `AuthLoginRendererInterface` for customizable login page rendering
- `LoginPageConfig` for config-driven login page styling (colors, logo, custom CSS)
- `ClaimMapper` for mapping provider claims to a unified user model
- `SecurityServiceProvider` for container registration

#### Validation
- Attribute-based validation rules: `#[Required]`, `#[Email]`, `#[MinLength]`, `#[MaxLength]`, `#[Min]`, `#[Max]`, `#[Pattern]`, `#[In]`
- `Validator` class with `validate(object)` and `validateArray(array, class)` methods
- `ValidationResult` for structured error collection
- `ValidationException` for throwable validation failures

#### Error Handling
- `ExceptionHandler` with JSON and HTML response modes based on `Accept` header
- Debug mode with full stack traces; production mode with generic messages
- Exception-to-status-code mapping: `ValidationException` to 422, `SecurityException` to 401, `HttpException` to custom codes
- `HttpException` hierarchy: `BadRequestException`, `NotFoundException`, `MethodNotAllowedException`
- Last-resort safety net in `Application::run()` for catastrophic failures

#### Logging
- `LoggerInterface` abstraction with log level support
- `FileLogger` with daily log rotation
- `NullLogger` for testing and environments without logging
- `LoggingServiceProvider` for container registration

#### Events
- `EventDispatcherInterface` with `listen()` and `dispatch()` methods
- Priority-based listener execution
- Event propagation stopping
- `EventServiceProvider` for container registration

#### Cache
- `CacheInterface` modeled on PSR-16 `SimpleCacheInterface`
- `FileCache` driver with TTL expiration and key hashing
- `ArrayCache` driver for testing and single-request caching
- `CacheServiceProvider` for container registration

#### Session
- `SessionInterface` with `start()`, `get()`, `set()`, `has()`, `remove()`, `destroy()`, and `regenerate()`
- `NativeSession` wrapping PHP session functions
- `ArraySession` for testing
- `SessionManager` implementing `SessionInterface` for backward compatibility
- `SessionServiceProvider` for container registration

#### View
- `ViewEngine` with `.phtml` template rendering
- Layout system with `setLayout()`, `renderBody()`, and named sections via `beginSection()`/`endSection()`/`renderSection()`
- `ViewBag` dynamic key-value store for passing data to views
- State reset between `render()` calls

#### Controllers
- Abstract `Controller` base class with `json()`, `created()`, `noContent()` response helpers
- `ApiController` with `getUserContext()` from JWT authentication
- `MvcController` with `view()`, `viewBag()`, and layout system integration

#### Services
- `Service` base class holding `DbContext` reference for data access

#### Utilities
- `Utilities` class with `kill()` debug helper

#### Packaging and CI
- MIT license
- PSR-4 autoloading (`Melodic\` to `src/`)
- PHPUnit 11 with 295+ tests and 533+ assertions
- GitHub Actions CI workflow on PHP 8.2, 8.3, and 8.4
- Example application with landing page, documentation section, and dark theme

### Fixed
- `OAuthClient` constructor type corrected from `AuthConfig` to `AuthProviderConfig`
- `ReflectionClass` cached outside hydration loop in `DbContext` for performance
- `ViewEngine` state properly reset between `render()` calls
- Model hydration type casting via `ReflectionNamedType`
- Removed deprecated `setAccessible(true)` calls unnecessary since PHP 8.1
- `JsonBodyParserMiddleware` catches `JsonException` and throws `BadRequestException` instead of raw 500 errors
- `RoutingMiddleware` delegates unmatched routes to fallback handler for consistent 404 formatting
- `Request::withAttribute()` compatibility with PHP 8.2+ readonly properties
- `Response` immutable methods preserve cookies across transformations

### Security
- CSRF token protection on authentication forms with single-use tokens and `hash_equals` validation
- Session ID regeneration after successful authentication to prevent session fixation
- OAuth2 PKCE flow for secure authorization code exchange

[1.0.0]: https://github.com/MelodicDevelopment/melodic-php/releases/tag/v1.0.0
