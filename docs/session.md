# Session

Melodic provides a session abstraction with pluggable drivers. `NativeSession` wraps PHP's built-in session functions, and `ArraySession` provides an in-memory implementation for testing.

## SessionInterface

| Method | Description |
|---|---|
| `start(): void` | Start the session |
| `get(string $key, mixed $default = null): mixed` | Retrieve a session value |
| `set(string $key, mixed $value): void` | Store a session value |
| `has(string $key): bool` | Check if a key exists |
| `remove(string $key): void` | Remove a value |
| `destroy(): void` | Destroy the entire session |
| `regenerate(bool $deleteOld = true): void` | Regenerate the session ID |
| `isStarted(): bool` | Check if the session is active |

## NativeSession

Wraps PHP's `$_SESSION` superglobal and `session_*` functions. Sessions are started automatically on first `get()`, `set()`, or `has()` call.

```php
use Melodic\Session\NativeSession;

$session = new NativeSession();

$session->set('user_id', 42);
$session->get('user_id');          // 42
$session->get('missing', 'default'); // 'default'
$session->has('user_id');          // true

$session->remove('user_id');
$session->regenerate();            // new session ID, old data preserved
$session->destroy();               // clears all data, ends session
```

## ArraySession

In-memory session for unit tests. Same interface, no PHP session functions called.

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

This binds `SessionInterface` to `NativeSession` as a singleton. Inject it where needed:

```php
class CartController extends MvcController
{
    public function __construct(
        private readonly SessionInterface $session,
    ) {}

    public function addItem(): Response
    {
        $cart = $this->session->get('cart', []);
        $cart[] = $this->request->body()['item_id'];
        $this->session->set('cart', $cart);

        return $this->json(['cart_size' => count($cart)]);
    }
}
```

## Security

The framework regenerates the session ID after successful authentication to prevent session fixation attacks. This happens automatically in the authentication middleware via `$session->regenerate()`.
