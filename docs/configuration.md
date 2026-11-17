# Configuration

Melodic uses a JSON-based configuration system with dot-notation access and layered environment support. Configuration files are deep-merged in a predictable order, allowing base settings to be overridden per environment.

## Quick Start

```php
$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();
```

This single call loads configuration files from the `config/` directory in the correct order based on the `APP_ENV` environment variable. To use a different directory, pass it (relative to the base path): `$app->loadEnvironmentConfig('settings')`.

## Loading Order

```
config.json  →  config.{APP_ENV}.json  →  config.dev.json
  (base)         (env overrides)          (dev overrides, gitignored)
```

1. **`config/config.json`** — Base configuration, always loaded. Contains defaults shared across all environments. This file is required: a missing file or invalid JSON throws a `RuntimeException`.
2. **`config/config.{APP_ENV}.json`** — Environment-specific overrides. Only loaded when `APP_ENV` is set to something other than `dev` (e.g., `qa`, `pd`, `staging`). If the file for the selected environment does not exist it is silently skipped.
3. **`config/config.dev.json`** — Local developer overrides. Loaded last **whenever the file exists, in every environment** — it also overrides `config.qa.json` / `config.pd.json`. This file should be gitignored so developers can customize settings without affecting others, and it must never be deployed to a server.

There is no `config.local.json` convention; `config.dev.json` is the only local override file the framework looks for.

Each file is deep-merged on top of the previous, so you only need to specify the values that differ from the base.

**Merge semantics (4.0):** objects (maps) merge key-by-key, but **arrays (lists) replace wholesale**. If the base defines `"allowedOrigins": ["https://a.example.com", "https://b.example.com"]` and the QA file defines `["https://qa.example.com"]`, the result in QA is exactly `["https://qa.example.com"]` — base entries never bleed through. An empty `{}`/`[]` override leaves an existing object untouched (JSON decoding cannot distinguish the two), so to clear a list, the base value must be a non-empty list you override. Scalars and `null` always replace the previous value. Before 4.0, lists were merged by index — see [`MIGRATION-4.0.md`](../MIGRATION-4.0.md).

### The APP_ENV Variable

Set the `APP_ENV` environment variable to select which environment file to load:

```bash
# Development (default when unset)
php -S localhost:8080 -t public

# QA
APP_ENV=qa php -S localhost:8080 -t public

# Production
APP_ENV=pd php -S localhost:8080 -t public
```

When `APP_ENV` is unset or empty, it defaults to `dev`. The value is read from the process environment with `getenv()`; the framework does not load `.env` files.

### Checking the Environment at Runtime

The current environment is automatically set in config as `app.environment`:

```php
$env = $app->getEnvironment();           // 'dev', 'qa', 'pd', etc.
$env = $app->config('app.environment');  // same thing
```

`app.environment` is written by `loadEnvironmentConfig()` after all files are loaded, so a value for it in a JSON file is overwritten. If you load files manually with `loadConfig()` instead, the key is not set and `getEnvironment()` returns `'dev'`.

## Configuration Files

### Base Config (`config/config.json`)

```json
{
    "app": {
        "debug": true
    },
    "database": {
        "dsn": "sqlite:storage/database.sqlite"
    },
    "cors": {
        "allowedOrigins": ["*"],
        "allowedMethods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    }
}
```

This is the `config.json` that `make:project` generates. The scaffolded `public/index.php` passes the `cors` section to `CorsMiddleware`; `database` is a conventional place for your own connection settings — the framework does not read it or create a database connection for you.

### Environment Override (`config/config.qa.json`)

Only include values that differ from the base:

```json
{
    "database": {
        "dsn": "mysql:host=qa-db.example.com;dbname=myapp"
    },
    "cors": {
        "allowedOrigins": ["https://qa.example.com"]
    }
}
```

Here `database.dsn` is merged into the base `database` object, while the `allowedOrigins` list replaces the base list entirely.

### Production Override (`config/config.pd.json`)

```json
{
    "app": {
        "debug": false
    },
    "database": {
        "dsn": "mysql:host=prod-db.example.com;dbname=myapp"
    }
}
```

### Local Developer Override (`config/config.dev.json`)

This file is gitignored. Use it for local-only settings:

```json
{
    "database": {
        "dsn": "sqlite:storage/dev.sqlite"
    }
}
```

## Environment Variable Placeholders

String values in a config file may reference environment variables (since 4.1), so secrets can stay out of the JSON files:

```json
{
    "database": {
        "dsn": "mysql:host=${DB_HOST:-localhost};dbname=myapp",
        "password": "${DB_PASSWORD}"
    }
}
```

- `${VAR}` is replaced with the value of the environment variable `VAR` (looked up with `getenv()`, then `$_ENV`, then `$_SERVER`).
- `${VAR:-default}` uses `default` when the variable is not set **or is empty** (shell semantics). `${VAR}` with no default keeps an empty value as an empty string.
- A placeholder whose variable is not set and has no default is left in the value untouched (`"${DB_PASSWORD}"` stays literally that).
- Placeholders are resolved when a file is loaded (`loadEnvironmentConfig()` / `loadConfig()` / `Configuration::loadFile()`), in string values at any depth. Values added with `set()` or `merge()` are not processed.
- The result is a **string**, with one exception: a value that consists of exactly one placeholder and resolves to `true` or `false` (case-insensitive) becomes a real boolean, so `"debug": "${APP_DEBUG:-false}"` works as expected. Anything else stays a string — `"${PORT}"` yields `"8080"`, and `"1"`/`"0"` are not converted.

## Framework Configuration Keys

Keys read by the framework itself and the providers covered in these docs:

| Key | Default | Used by |
|---|---|---|
| `app.debug` | `false` | `Application` — when true, error responses include exception details. See [Error Handling](error-handling.md). Keep it `false` in production. |
| `app.environment` | — | Set by `loadEnvironmentConfig()` (see above). |
| `app.errorsAsExceptions` | `false` | `Application::run()` — convert PHP warnings/notices into exceptions (since 4.1). See [Error Handling](error-handling.md). |
| `views.path` | `views` | Template directory of the default `ViewEngine`, relative to the base path unless absolute (since 4.1). |
| `logging.path`, `logging.level`, `logging.maxFiles` | `{basePath}/logs`, `debug`, `0` | `LoggingServiceProvider`. See [Logging](logging.md). |
| `session.cookieSecure`, `session.cookieSameSite`, `session.cookiePath`, `session.cookieDomain` | `true`, `Lax`, `/`, `""` | Session cookie attributes. See [Session](session.md). |
| `auth.*` | — | `SecurityServiceProvider`. See [Authentication](authentication.md). |
| `csrf.exceptPaths` | `["/api"]` | Path prefixes that `CsrfMiddleware` (as bound by `SecurityServiceProvider`) does not check (since 4.1). |

Everything else is yours to define.

## Accessing Configuration Values

Use dot-notation to access nested values:

```php
// In a controller or anywhere with access to the Application instance
$debug = $app->config('app.debug');
$dsn = $app->config('database.dsn');
$origins = $app->config('cors.allowedOrigins');

// With a default value
$timeout = $app->config('http.timeout', 30);
```

The default is returned only when the key is absent. A key that is present with a JSON `null` value returns `null`.

Keys are split on `.`, so a dot is always a path separator. A JSON key that itself contains a literal dot (e.g. `{ "app.name": "x" }` as a single key) cannot be addressed with `get()`/`has()` — nest such values instead.

### The Configuration Object

For direct access to the `Configuration` instance:

```php
$config = $app->getConfiguration();   // or $app->config() with no arguments

$config->get('database.dsn');
$config->has('database.dsn');      // true/false (true even if the value is null)
$config->set('app.custom', 42);    // set a value at runtime (creates intermediate objects)
$config->merge(['app' => ['name' => 'My App']]);  // deep-merge an array, same rules as files
$config->all();                    // get the entire config array
```

The `Configuration` instance is also registered in the DI container, so services and providers can type-hint `Melodic\Core\Configuration`.

## Manual Config Loading

If you need more control, use `loadConfig()` directly:

```php
$app->loadConfig('config/config.json');
$app->loadConfig('config/config.qa.json');
```

`loadConfig()` accepts paths relative to the application base path or absolute paths (POSIX, Windows drive, or UNC). Each call deep-merges the file over what is already loaded, and throws a `RuntimeException` if the file is missing, unreadable, not valid JSON, or holds a bare scalar or `null` instead of a JSON object.

## Scaffolding Config Files

Generate a new environment config file:

```bash
vendor/bin/melodic make:config staging
```

This creates `config/config.staging.json` in the current directory with a minimal template (`app.debug: false` and an empty `database.dsn`). The environment name is lower-cased and may only contain letters, digits, dashes, and underscores. The command refuses to overwrite an existing file. See [Console](console.md#makeconfig).

## Gitignore

`make:project` generates `config/config.json`, `config/config.qa.json`, and `config/config.pd.json` (with `app.debug` set to `true`, `true`, and `false` respectively), plus a `.gitignore` that excludes `config/config.dev.json`. Environment configs like `config.qa.json` and `config.pd.json` are tracked in version control since they contain non-secret settings (secrets should come from environment variables — see [placeholders](#environment-variable-placeholders) — or a secrets manager).
