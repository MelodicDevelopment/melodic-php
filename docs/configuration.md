# Configuration

Melodic uses a JSON-based configuration system with dot-notation access and layered environment support. Configuration files are deep-merged in a predictable order, allowing base settings to be overridden per environment.

## Quick Start

```php
$app = new Application(__DIR__ . '/..');
$app->loadEnvironmentConfig();
```

This single call loads configuration files from the `config/` directory in the correct order based on the `APP_ENV` environment variable.

## Loading Order

```
config.json  →  config.{APP_ENV}.json  →  config.dev.json
  (base)         (env overrides)          (dev overrides, gitignored)
```

1. **`config/config.json`** — Base configuration, always loaded. Contains defaults shared across all environments.
2. **`config/config.{APP_ENV}.json`** — Environment-specific overrides. Only loaded when `APP_ENV` is set to something other than `dev` (e.g., `qa`, `pd`, `staging`).
3. **`config/config.dev.json`** — Local developer overrides. Always loaded last if present. This file should be gitignored so developers can customize settings without affecting others.

Each file is deep-merged on top of the previous, so you only need to specify the values that differ from the base.

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

When `APP_ENV` is unset, it defaults to `dev`.

### Checking the Environment at Runtime

The current environment is automatically set in config as `app.environment`:

```php
$env = $app->getEnvironment();           // 'dev', 'qa', 'pd', etc.
$env = $app->config('app.environment');  // same thing
```

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
    "jwt": {
        "secret": "change-me",
        "algorithm": "HS256"
    },
    "cors": {
        "allowedOrigins": ["*"],
        "allowedMethods": ["GET", "POST", "PUT", "DELETE", "OPTIONS"],
        "allowedHeaders": ["Content-Type", "Authorization"],
        "maxAge": 3600
    }
}
```

### Environment Override (`config/config.qa.json`)

Only include values that differ from the base:

```json
{
    "database": {
        "dsn": "mysql:host=qa-db.example.com;dbname=myapp"
    },
    "jwt": {
        "secret": "qa-secret-key"
    }
}
```

### Production Override (`config/config.pd.json`)

```json
{
    "app": {
        "debug": false
    },
    "database": {
        "dsn": "mysql:host=prod-db.example.com;dbname=myapp"
    },
    "jwt": {
        "secret": "production-secret-key"
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

## Accessing Configuration Values

Use dot-notation to access nested values:

```php
// In a controller or anywhere with access to the Application instance
$debug = $app->config('app.debug');
$dsn = $app->config('database.dsn');
$secret = $app->config('jwt.secret');

// With a default value
$timeout = $app->config('http.timeout', 30);
```

### The Configuration Object

For direct access to the `Configuration` instance:

```php
$config = $app->getConfiguration();

$config->get('database.dsn');
$config->has('jwt.secret');       // true/false
$config->set('app.custom', 42);   // set a value at runtime
$config->all();                    // get the entire config array
```

## Manual Config Loading

If you need more control, use `loadConfig()` directly:

```php
$app->loadConfig('config/config.json');
$app->loadConfig('config/config.qa.json');
```

`loadConfig()` accepts paths relative to the application base path or absolute paths.

## Scaffolding Config Files

Generate a new environment config file:

```bash
vendor/bin/melodic make:config staging
```

This creates `config/config.staging.json` with a minimal template. The command refuses to overwrite an existing file.

## Gitignore

Projects scaffolded with `make:project` include a `.gitignore` that excludes `config/config.dev.json`. Environment configs like `config.qa.json` and `config.pd.json` are tracked in version control since they contain non-secret settings (secrets should come from environment variables or a secrets manager).
