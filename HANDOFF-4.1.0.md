# Handoff: Melodic PHP 4.1.0 (written 2026-11-16)

Working note for picking this work up in a new session. **Do not commit this file** — delete it before the release commit.

## State

- Branch: `feature/4.1-review-fixes` (off `main`). **Nothing is committed, tagged or pushed.**
- `Framework::VERSION` is `4.1.0` (`src/Framework.php`).
- `vendor/bin/phpunit`: 813 tests pass (with Xdebug on and with `XDEBUG_MODE=off`).
- `composer analyse` (PHPStan level 6, now `--memory-limit=512M`): clean. The default 128M limit crashes PHPStan.
- `CHANGELOG.md` has the full 4.1.0 entry and is the detailed source of truth for what changed.
- A second Claude session (`melodic-php-ee`) rewrote the docs in the same working tree: `README.md`, `docs/*.md`, `MELODIC_PHP.md`, `CLAUDE.md`, `PUBLISHING.md`, `MIGRATION-*.md`, `.claude/agents`, `.claude/skills`. It reports them reconciled with the final source. Those edits are also uncommitted on this branch.

## What was done

Origin: a full review of the framework, then "run through all the fixes" with the instruction to be careful about removing anything external code might use. Nothing was removed.

### Bugs fixed
- `ViewEngine`: templates render in an isolated scope (a view variable named `path` used to replace the included file).
- Model binding: nested `Model` properties hydrate; wrong-shaped input for `array`/nested/union properties is a 400, not a 500; integer overflow rejected; `#[ListOf(Foo::class)]` hydrates lists of models.
- Enums and `DateTime` round-trip to the database (`toPascalArray()`/`toUpdateArray()`); JSON renders `DateTime` as ISO-8601.
- `DbContext` normalizes params (`false` → 0, enum → value, `DateTime` → string).
- Routing: params URL-decoded; trailing slash matches.
- `Request`: `//`-prefixed targets parse correctly; `Bearer` is case-insensitive.
- Unknown HTTP methods return 501 instead of crashing.
- Scaffolded MVC projects work: `Application` binds a default `ViewEngine` (`{basePath}/views`, config `views.path`).
- `WebAuthenticationMiddleware` no longer swallows app-thrown `SecurityException`s.
- `AuthorizationMiddleware` reads a route's `entitlements` attribute.
- `CorsMiddleware` appends to `Vary`.
- Refresh-token reuse detection survives the transaction rollback (`RefreshTokenReuseException`).
- `ExceptionHandler` logs mapped exceptions with the mapped status; `ValidationException`/`ModelBindingException` map to 400.
- `make:entity` keeps inner capitals (`BlogPost`); `make:project` requires `^4.1`; unused `jwt` config block removed from stubs.

### Security
- New `CsrfMiddleware`, `RateLimitMiddleware`, `SecurityHeadersMiddleware`.
- Sessions use strict mode.
- Login error text comes from the session, not the `?error=` URL parameter.
- Logout destroys the session and clears the refresh cookie.
- OIDC cache directory ownership/permissions checked under the system temp dir.
- `SecurityServiceProvider` passes the bound `DbContext` and logger to the refresh-token classes.
- Error responses now carry CORS and security headers (`ResponseDecoratorInterface`).

### Features
- `Application::handle(Request): Response` and `boot()`.
- `Request`: `ip()`, `host()`, `scheme()`, `isSecure()`, `queryString()`, `uri()`, `server()`, `file()`.
- Routing: constraints (`{id:\d+}`), named routes + `Router::url()`, PATCH in `apiResource()`, `#[FromQuery]`.
- Validation: `RuleInterface`, `#[Url]`, `#[Uuid]`, `#[Date]`, nested validation with dotted keys.
- Database: `Migrator` + `migrate`, `migrate:rollback`, `migrate:status`, `make:migration`; `Paginator`/`PaginatedResult`; `DbContext::cursor()`.
- Config: `${VAR}` / `${VAR:-default}` placeholders; `app.errorsAsExceptions` (opt-in); `logging.maxFiles`.
- OIDC: `claimMap` applies to OIDC providers; key set refetched on unknown key id; stale documents served when the provider is down.
- PSR: container implements PSR-11 (`psr/container` is a new required dependency); `PsrLoggerAdapter`, `Psr16CacheAdapter`.
- CI runs PHPStan as well as tests.

### Deprecated, not removed (removal planned for 5.0)
- `Security\SafeRedirect`, `Utilities::kill()`, `OAuthClient` instance methods.
- `SessionManager` now extends `NativeSession` instead of duplicating it.

## Behaviour changes consumers may notice
- `apiResource()` registers a sixth route (PATCH → `update`).
- Route params arrive URL-decoded.
- `json_encode($model)` renders `DateTime` as ISO-8601.
- Failed logins redirect to `{loginPath}?error=1`.
- `AuthorizationMiddleware` enforces a route's `entitlements` attribute.
- 4xx log lines have no stack trace unless `app.debug` is on.

## Deliberately not done
- Validation errors are still 400 with `{field: [messages]}` (422 / unified format would break clients).
- `removeListener()` not added to `EventDispatcherInterface` (would break implementers).
- Relative `logging.path` / `auth.oidcCacheDir` still resolve against the working directory.
- One OIDC `audience` setting still covers both login tokens and API bearer tokens.
- No router index or compiled config cache.
- No direct tests for the OIDC `claimMap` and unknown-key refetch paths in `OidcAuthProvider`.

## Open follow-ups (suggested as a separate PR)
- `make:project`: service provider stub binds no database, nothing reads `database.dsn`, `bin/console` registers no commands, base/QA configs ship `"debug": true`.
- `make:entity`: Create/Update SQL are TODOs, controller `store()` ignores the body, ids are `string $id`; `APIKey` yields `apikeys` / `$aPIKeys`.
- Binding/validation failures are always JSON 400, including on MVC form routes.
- App-thrown `ModelBindingException` renders `{"error": message}` rather than a field-keyed body.

## Remaining steps to release
1. Delete this file.
2. Commit on `feature/4.1-review-fixes` (no `Co-Authored-By` or any AI attribution — see `CLAUDE.md`).
3. Push and open a PR to `main`; merge after review.
4. `git tag v4.1.0 && git push origin v4.1.0` (Packagist picks up the tag; see `PUBLISHING.md`).

The user has not yet approved committing, pushing or tagging.
