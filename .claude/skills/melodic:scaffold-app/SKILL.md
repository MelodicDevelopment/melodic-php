---
name: melodic:scaffold-app
description: Scaffold a new Melodic PHP application. Use when the user wants to create a new web app, API, or combined application.
disable-model-invocation: true
argument-hint: [app-directory]
---

# Scaffold a New Melodic PHP Application

You are scaffolding a new application using the Melodic PHP framework. The target directory is `$ARGUMENTS` (if no argument was provided, ask the user where to create the app).

## Step 1: Ask the User What They Want

Use AskUserQuestion to gather requirements before generating any files. Ask all questions in a single call:

1. **App type**: What kind of app?
    - **Web + API (Recommended)** — MVC views with layouts, plus JSON API endpoints
    - **API only** — JSON API with Bearer token auth, no views
    - **Web only** — MVC views with cookie-based auth, no API

2. **Authentication**: What auth providers?
    - **Google + GitHub (Recommended)** — Social login with Google (OIDC) and GitHub (OAuth2)
    - **Google only** — OIDC with Google
    - **Local only** — Username/password against app's own user store
    - **None** — No authentication

3. **Database**: Which database?
    - **SQLite (Recommended)** — File-based, no setup needed
    - **MySQL** — MySQL/MariaDB
    - **PostgreSQL** — PostgreSQL

4. **Domain entity**: What's the primary resource? (e.g. "Post", "Product", "Task") — this will be used to scaffold the first CRUD resource as a working example.

## Step 2: Create the Directory Structure

Based on the answers, generate the full application. Use the framework's conventions from CLAUDE.md.

### Base structure (always created):

```
{app-directory}/
├── composer.json              # PSR-4 autoload, require melodic-php
├── config/
│   ├── config.json            # App config with auth, database, cors
│   └── routes.php             # Route definitions
├── public/
│   └── index.php              # Entry point — bootstrap Application
├── src/
│   ├── Controllers/           # Controller classes
│   ├── Services/              # Service classes + interfaces
│   ├── Models/                # Model DTOs
│   ├── Queries/               # CQRS query classes
│   ├── Commands/              # CQRS command classes
│   └── Providers/             # Service providers
└── .gitignore
```

### If Web is included, also create:

```
├── views/
│   ├── layouts/
│   │   └── main.phtml         # Base layout with nav, CSS, sections
│   └── home/
│       ├── index.phtml        # Home page
│       └── about.phtml        # About page
└── src/
    └── Controllers/
        └── HomeController.php # MVC controller with index + about
```

### If API is included, also create:

```
└── src/
    └── Controllers/
        └── {Entity}ApiController.php  # CRUD API controller for the domain entity
```

### If Auth is included, also create:

```
└── src/
    └── Providers/
        └── AppServiceProvider.php     # Registers SecurityServiceProvider + bindings
```

### If Local auth, also create:

```
└── src/
    └── Security/
        └── AppAuthenticator.php       # LocalAuthenticatorInterface implementation
```

## Step 3: File Contents

### composer.json

```json
{
	"name": "app/{app-name}",
	"type": "project",
	"require": {
		"php": ">=8.2",
		"melodicdev/framework": "*"
	},
	"autoload": {
		"psr-4": {
			"App\\": "src/"
		}
	}
}
```

### public/index.php

Bootstrap the Application:

- `require_once __DIR__ . '/../vendor/autoload.php'`
- Create `new Application(dirname(__DIR__))`
- `loadConfig('config/config.json')`
- Register service providers (AppServiceProvider if auth, SecurityServiceProvider)
- Register services: DbContext (singleton), ViewEngine (if web)
- Add global middleware: RequestTimingMiddleware (optional), CorsMiddleware, JsonBodyParserMiddleware
- Load routes from `config/routes.php`
- Call `$app->run()`

### config/config.json

Build based on the user's choices:

- `app.name` — use the app directory name
- `database` — DSN based on chosen database type
- `auth` — providers based on chosen auth. Include `local` signing config if OAuth2 or Local auth is used. Use placeholder client IDs/secrets with comments indicating they need to be replaced.
- `cors` — standard permissive config for development

### config/routes.php

- Public routes: `GET /` and `GET /about` (if web)
- Auth routes group with `AuthCallbackMiddleware` (if auth)
- Protected web routes with `WebAuthenticationMiddleware` (if web + auth)
- API routes group with `ApiAuthenticationMiddleware` (if api + auth) or no middleware (if api without auth)
- Use `apiResource()` for the domain entity

### Domain entity files

For the user's chosen entity (e.g. "Post"), create the full CQRS stack:

1. **Model** (`src/Models/{Entity}Model.php`) — extends `Melodic\Data\Model` with typical fields (id, name/title, created_at)
2. **Queries** — `GetAll{Entity}Query.php` and `Get{Entity}ByIdQuery.php` implementing `QueryInterface`
3. **Commands** — `Create{Entity}Command.php` and `Delete{Entity}Command.php` implementing `CommandInterface`
4. **Service interface** (`src/Services/{Entity}ServiceInterface.php`)
5. **Service** (`src/Services/{Entity}Service.php`) — extends `Melodic\Service\Service`, uses queries/commands
6. **API Controller** (`src/Controllers/{Entity}ApiController.php`) — extends `ApiController`, full CRUD (if API)

### Layout (if web)

Use the same style as the example app's `main.phtml`:

- System font stack, light gray background
- Nav bar with links (Home, About, API link if applicable)
- Login/Logout in the nav if auth is enabled (check `$viewBag->userContext`)
- Container with white card for content
- Section support for `head` and `scripts`

### HomeController (if web)

- Pass `userContext` to viewBag for nav display
- `index()` action with welcome message
- `about()` action

### AppAuthenticator (if local auth)

- Implement `LocalAuthenticatorInterface`
- Include a hardcoded demo user (`admin@example.com` / `password`) with a `// TODO: Replace with database lookup` comment
- Show the pattern: lookup user, `password_verify()`, return claims array, throw `SecurityException` on failure

## Step 4: Summary

After creating all files, print a summary:

- List all files created
- Show the command to install dependencies: `cd {app-directory} && composer install`
- Show the command to start the dev server: `php -S localhost:8080 -t public`
- If auth is configured with external providers, remind the user to replace placeholder client IDs/secrets and point them to the relevant `docs/setup-*.md` guide
- Show the URLs they can visit to test

## Important

- Follow all conventions from CLAUDE.md (PHP 8.2+, PascalCase classes, camelCase methods, CQRS layering)
- Use `declare(strict_types=1)` in every PHP file
- Use constructor promotion and readonly properties
- The generated app should work out of the box with `php -S localhost:8080 -t public` (assuming dependencies are installed and placeholder credentials are updated for auth)
- Do NOT create the app inside the melodic-php framework directory — it's a separate project that depends on the framework
- If the target directory already exists and has files, warn the user before overwriting
