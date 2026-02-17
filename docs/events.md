# Events

Melodic includes a simple event dispatcher for decoupling components. Listeners are registered by event class name, called in priority order, and support propagation stopping.

## Defining Events

Extend the `Event` base class (or use any object — the base class just adds `stopPropagation()` support):

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

If an event extends `Event`, any listener can stop further listeners from being called:

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

Listeners are sorted by priority in descending order (highest first). Listeners with the same priority run in registration order.

```php
$dispatcher->listen(UserRegistered::class, $listenerA, priority: 0);   // runs third
$dispatcher->listen(UserRegistered::class, $listenerB, priority: 10);  // runs first
$dispatcher->listen(UserRegistered::class, $listenerC, priority: 10);  // runs second
```

## Service Provider Registration

Register the dispatcher in the DI container using the built-in service provider:

```php
$app->register(new EventServiceProvider());

// Then inject it wherever needed
class UserService extends Service
{
    public function __construct(
        protected readonly DbContextInterface $context,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function register(string $username, string $email): int
    {
        $id = (new CreateUserCommand($username, $email))->execute($this->context);
        $this->dispatcher->dispatch(new UserRegistered($id, $email));
        return $id;
    }
}
```

## EventDispatcherInterface

| Method | Description |
|---|---|
| `listen(string $eventClass, callable $listener, int $priority = 0)` | Register a listener for an event class |
| `dispatch(object $event): object` | Dispatch an event to all registered listeners |
