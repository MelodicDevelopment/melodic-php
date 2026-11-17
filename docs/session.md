# Session

Melodic provides a session abstraction with pluggable drivers. `NativeSession` wraps PHP's built-in session functions, and `ArraySession` provides an in-memory implementation for testing.

## SessionInterface

| Method | Description |
|---|---|
| `start(): void` | Start the session (no-op if already started) |
| `get(string $key, mixed $default = null): mixed` | Retrieve a session value (`$default` if missing or `null`) |
| `set(string $key, mixed $value): void` | Store a session value |
| `has(string $key): bool` | Check if a key exists (true even when the value is `null`) |
| `remove(string $key): void` | Remove a value |
| `destroy(): void` | Destroy the entire session |
| `regenerate(bool $deleteOld = true): void` | Regenerate the session ID |
| `isStarted(): bool` | Check if the session is active |

## NativeSession

Wraps PHP's `$_SESSION` superglobal and `session_*` functions. The session is started automatically on the first `get()`, `set()`, `has()`, or `remove()` call.

```php
use Melodic\Session\NativeSession;

$session = new NativeSession();

$session->set('user_id', 42);
$session->get('user_id');          // 42
$session->get('missing', 'default'); // 'default'
$session->has('user_id');          // true

$session->remove('user_id');
$session->regenerate();            // new session ID, old data preserved
$session->destroy();               // clears all data, ends session, expires the cookie
```

### Cookie Settings

The session cookie is hardened when the session starts. The attributes come from the constructor:

```php
new NativeSession(
    cookieSecure: true,       // default — cookie is only sent over HTTPS
    cookieSameSite: 'Lax',    // default
    cookiePath: '/',          // default
    cookieDomain: '',         // default — current host only
);
```

The cookie is always `HttpOnly` and is a browser-session cookie (lifetime `0`). These settings are applied only if no session is active yet and headers have not been sent.

At the same point `NativeSession` also enables PHP's `session.use_strict_mode` and `session.use_only_cookies`, so a session id the server did not issue is never adopted and ids are never accepted from URLs (since 4.1).

> **Local development over plain HTTP:** with the default `cookieSecure: true` the browser never sends the session cookie back over `http://`, so every request starts a new, empty session. Turn it off in your development config only — see [Configuration](#configuration) below.

### `destroy()` and `regenerate()`

- `destroy()` destroys the server-side session, empties `$_SESSION`, **and expires the browser's session cookie** (4.0) using the same attributes it was set with, so the old session id is not adopted again by the next request.
- Both `destroy()` and `regenerate()` act only on a session that is already started in the current request; otherwise they are no-ops. They do not start the session themselves — to log a user out, read or write the session first (or call `start()`) and then call `destroy()`.
- `regenerate()` deletes the old session file by default; pass `false` to keep it.

## ArraySession

In-memory session for unit tests. Same interface, no PHP session functions called. `regenerate()` is a no-op, `isStarted()` becomes true after `start()` or `set()`, and `destroy()` clears the data and resets the started flag.

```php
use Melodic\Session\ArraySession;

$session = new ArraySession();
$session->set('key', 'value');
$session->get('key'); // 'value'
```

## Service Provider

Register the session in the DI container:

```php
use Melodic\Session\SessionServiceProvider;

$app->register(new SessionServiceProvider());
```

This binds `SessionInterface` to a singleton `NativeSession`.

### Configuration

The provider builds the session from the `session.*` configuration keys:

```json
{
    "session": {
        "cookieSecure": true,
        "cookieSameSite": "Lax",
        "cookiePath": "/",
        "cookieDomain": ""
    }
}
```

The values shown are the defaults. For plain-HTTP local development, put this in `config/config.dev.json`:

```json
{ "session": { "cookieSecure": false } }
```

> Before 4.1 `SessionServiceProvider` ignored these keys and always used the constructor defaults (`Secure` on).

Inject it where needed:

```php
class CartController extends ApiController
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function addItem(): JsonResponse
    {
        $cart = $this->session->get('cart', []);
        $cart[] = $this->request->body()['item_id'];
        $this->session->set('cart', $cart);

        return $this->json(['cart_size' => count($cart)]);
    }
}
```

## Sessions and the Security Layer

`SecurityServiceProvider` registers its own session binding, `Melodic\Security\SessionManager`, built from the same `session.*` keys shown above. `AuthCallbackMiddleware` and `WebAuthenticationMiddleware` are constructed with `SessionManager` specifically, not with the `SessionInterface` binding.

`SessionManager` is an empty subclass of `NativeSession`, so it behaves identically.

`SecurityServiceProvider` also binds `SessionInterface` to that same `SessionManager` instance when `SessionInterface` is not already bound, so with only the security provider registered you can inject `SessionInterface` without registering `SessionServiceProvider`. If you register `SessionServiceProvider` as well (before or after), its `NativeSession` binding is the one `SessionInterface` resolves to. That is harmless: both objects wrap PHP's single native session, so data written through one is visible through the other, and both read the same configuration, so their cookie attributes agree.

## Security

- **Session fixation:** `AuthCallbackMiddleware` calls `regenerate(true)` on the session after every successful login callback, before the auth cookie is issued.
- **Cookie defaults:** `Secure` + `HttpOnly` + `SameSite=Lax` (3.0).
- **Strict session ids:** `session.use_strict_mode` and `session.use_only_cookies` are enabled when the session starts (since 4.1).
- **Logout:** `destroy()` expires the browser cookie as well as the server-side session (4.0). The built-in `POST /auth/logout` route calls `destroy()` on the session in addition to clearing the auth cookie (since 4.1 — earlier versions only cleared the auth cookie).
