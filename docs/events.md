# Events

Melodic includes a simple event dispatcher for decoupling components. Listeners are registered by event class name, called in priority order, and support propagation stopping.

## Defining Events

Extend the abstract `Event` base class (or dispatch any object — the base class just adds `stopPropagation()` / `isPropagationStopped()` support):

```php
use Melodic\Event\Event;

class UserRegistered extends Event
{
    public function __construct(
        public readonly int $userId,
        public readonly string $email,
    ) {}
}

class OrderPlaced extends Event
{
    public function __construct(
        public readonly int $orderId,
        public readonly float $total,
    ) {}
}
```

## Registering Listeners

```php
use Melodic\Event\EventDispatcher;

$dispatcher = new EventDispatcher();

// Basic listener
$dispatcher->listen(UserRegistered::class, function (UserRegistered $event) {
    sendWelcomeEmail($event->email);
});

// With priority (higher runs first, default is 0)
$dispatcher->listen(UserRegistered::class, function (UserRegistered $event) {
    logRegistration($event->userId);
}, priority: 10); // runs before the welcome email listener

// Multiple listeners for the same event
$dispatcher->listen(OrderPlaced::class, function (OrderPlaced $event) {
    updateInventory($event->orderId);
});

$dispatcher->listen(OrderPlaced::class, function (OrderPlaced $event) {
    notifyWarehouse($event->orderId);
});
```

## Dispatching Events

```php
// In a service or controller
$event = new UserRegistered(userId: 42, email: 'alice@example.com');
$dispatcher->dispatch($event);
```

Dispatch is synchronous: listeners run in order inside `dispatch()`, their return values are ignored, and an exception thrown by a listener propagates to the caller (remaining listeners do not run).

### Listeners match the exact event class

Listeners are looked up by the event object's exact class (`$event::class`). There is **no parent-class or interface matching**: a listener registered for a base class or an interface is *not* called when a subclass / implementation is dispatched.

```php
class UserEvent extends Event {}
class UserRegistered extends UserEvent {}

$dispatcher->listen(UserEvent::class, $auditListener);
$dispatcher->dispatch(new UserRegistered());   // $auditListener is NOT called
```

Register the listener for each concrete class you dispatch. Always pass the class name via `::class` — the string is compared literally, so `'\App\UserRegistered'` (leading backslash) never matches.

### Returning data from listeners

The `dispatch()` method returns the event object, so listeners can modify it:

```php
class PriceCalculated extends Event
{
    public float $discount = 0.0;

    public function __construct(
        public readonly float $subtotal,
    ) {}
}

$dispatcher->listen(PriceCalculated::class, function (PriceCalculated $event) {
    if ($event->subtotal > 100) {
        $event->discount = 0.10;
    }
});

$event = $dispatcher->dispatch(new PriceCalculated(subtotal: 150.00));
$finalPrice = $event->subtotal * (1 - $event->discount); // 135.00
```

## Stopping Propagation

If an event extends `Event`, any listener can stop further listeners from being called. The flag is checked before every listener (including the first), so an event that is already stopped when dispatched reaches no listeners. Objects that do not extend `Event` cannot stop propagation.

```php
$dispatcher->listen(OrderPlaced::class, function (OrderPlaced $event) {
    if ($event->total > 10000) {
        flagForReview($event->orderId);
        $event->stopPropagation(); // no further listeners run
    }
}, priority: 100);

$dispatcher->listen(OrderPlaced::class, function (OrderPlaced $event) {
    // This won't run if propagation was stopped above
    processNormally($event->orderId);
});
```

## Priority Order

Listeners are sorted by priority in descending order (highest first; negative priorities run after the default `0`). Listeners with the same priority run in registration order.

```php
$dispatcher->listen(UserRegistered::class, $listenerA, priority: 0);   // runs third
$dispatcher->listen(UserRegistered::class, $listenerB, priority: 10);  // runs first
$dispatcher->listen(UserRegistered::class, $listenerC, priority: 10);  // runs second
```

## Removing Listeners

`EventDispatcher::removeListener()` (added in 4.0) deregisters a listener — useful for test isolation and temporary subscriptions:

```php
$listener = function (UserRegistered $event) { /* ... */ };

$dispatcher->listen(UserRegistered::class, $listener, priority: 10);
$dispatcher->removeListener(UserRegistered::class, $listener);
```

- The listener is compared by identity (`===`), so keep a reference to the same closure (or pass the same `[$object, 'method']` pair) you registered.
- Every registration of that listener for the event class is removed, whatever its priority.
- Removing a listener that was never registered is a no-op.

`removeListener()` and `getListeners()` exist on the concrete `EventDispatcher` class only — they are **not** part of `EventDispatcherInterface`. Type-hint `EventDispatcher` where you need them; with `EventServiceProvider` both type-hints resolve to the same shared instance.

## Service Provider Registration

Register the dispatcher in the DI container using the built-in service provider:

```php
use Melodic\Event\EventServiceProvider;

$app->register(new EventServiceProvider());

// Then inject it wherever needed
class UserService extends Service
{
    public function __construct(
        DbContextInterface $context,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($context);
    }

    public function register(string $username, string $email): int
    {
        $id = (new CreateUserCommand($username, $email))->execute($this->context);
        $this->dispatcher->dispatch(new UserRegistered($id, $email));
        return $id;
    }
}
```

The provider binds `EventDispatcherInterface` to a singleton `EventDispatcher`, so every service receives the same dispatcher (and the same listeners). Register your listeners once at startup — for example in a service provider's `boot()`:

```php
public function boot(Container $container): void
{
    $dispatcher = $container->get(EventDispatcherInterface::class);
    $dispatcher->listen(UserRegistered::class, new SendWelcomeEmail()); // any callable, e.g. an invokable object
}
```

## EventDispatcherInterface

| Method | Description |
|---|---|
| `listen(string $eventClass, callable $listener, int $priority = 0): void` | Register a listener for an event class |
| `dispatch(object $event): object` | Dispatch an event to the listeners registered for its exact class; returns the same event object |

`EventDispatcher` (the concrete class) additionally provides:

| Method | Description |
|---|---|
| `removeListener(string $eventClass, callable $listener): void` | Remove a previously registered listener (4.0) |
| `getListeners(string $eventClass): array` | The listeners for an event class, in the order they would run |
