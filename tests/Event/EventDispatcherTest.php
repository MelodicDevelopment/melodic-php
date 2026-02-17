<?php

declare(strict_types=1);

namespace Tests\Event;

use Melodic\DI\Container;
use Melodic\Event\Event;
use Melodic\Event\EventDispatcher;
use Melodic\Event\EventDispatcherInterface;
use Melodic\Event\EventServiceProvider;
use PHPUnit\Framework\TestCase;

// --- Test fixtures ---

class UserCreatedEvent extends Event
{
    public function __construct(
        public readonly string $username
    ) {}
}

class OrderPlacedEvent extends Event
{
    public function __construct(
        public readonly int $orderId
    ) {}
}

// --- Tests ---

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = new EventDispatcher();
    }

    public function testDispatchCallsRegisteredListener(): void
    {
        $called = false;

        $this->dispatcher->listen(UserCreatedEvent::class, function (UserCreatedEvent $event) use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new UserCreatedEvent('alice'));

        $this->assertTrue($called);
    }

    public function testDispatchPassesEventToListener(): void
    {
        $receivedUsername = null;

        $this->dispatcher->listen(UserCreatedEvent::class, function (UserCreatedEvent $event) use (&$receivedUsername) {
            $receivedUsername = $event->username;
        });

        $this->dispatcher->dispatch(new UserCreatedEvent('bob'));

        $this->assertSame('bob', $receivedUsername);
    }

    public function testDispatchReturnsTheEvent(): void
    {
        $event = new UserCreatedEvent('charlie');

        $returned = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $returned);
    }

    public function testMultipleListenersAreCalledInOrder(): void
    {
        $order = [];

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'first';
        });

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'second';
        });

        $this->dispatcher->dispatch(new UserCreatedEvent('dave'));

        $this->assertSame(['first', 'second'], $order);
    }

    public function testHigherPriorityListenersRunFirst(): void
    {
        $order = [];

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'low';
        }, priority: 0);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'high';
        }, priority: 10);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'medium';
        }, priority: 5);

        $this->dispatcher->dispatch(new UserCreatedEvent('eve'));

        $this->assertSame(['high', 'medium', 'low'], $order);
    }

    public function testSamePriorityListenersRunInRegistrationOrder(): void
    {
        $order = [];

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'first';
        }, priority: 5);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'second';
        }, priority: 5);

        $this->dispatcher->dispatch(new UserCreatedEvent('frank'));

        $this->assertSame(['first', 'second'], $order);
    }

    public function testStopPropagationPreventsLaterListeners(): void
    {
        $order = [];

        $this->dispatcher->listen(UserCreatedEvent::class, function (UserCreatedEvent $event) use (&$order) {
            $order[] = 'first';
            $event->stopPropagation();
        }, priority: 10);

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$order) {
            $order[] = 'second';
        }, priority: 0);

        $this->dispatcher->dispatch(new UserCreatedEvent('grace'));

        $this->assertSame(['first'], $order);
    }

    public function testDispatchWithNoListenersDoesNothing(): void
    {
        $event = new UserCreatedEvent('heidi');

        $returned = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $returned);
    }

    public function testListenersAreIsolatedByEventClass(): void
    {
        $userCalled = false;
        $orderCalled = false;

        $this->dispatcher->listen(UserCreatedEvent::class, function () use (&$userCalled) {
            $userCalled = true;
        });

        $this->dispatcher->listen(OrderPlacedEvent::class, function () use (&$orderCalled) {
            $orderCalled = true;
        });

        $this->dispatcher->dispatch(new UserCreatedEvent('ivan'));

        $this->assertTrue($userCalled);
        $this->assertFalse($orderCalled);
    }

    public function testGetListenersReturnsEmptyArrayForUnknownEvent(): void
    {
        $listeners = $this->dispatcher->getListeners(OrderPlacedEvent::class);

        $this->assertSame([], $listeners);
    }

    public function testGetListenersReturnsSortedListeners(): void
    {
        $low = function () {};
        $high = function () {};

        $this->dispatcher->listen(UserCreatedEvent::class, $low, priority: 0);
        $this->dispatcher->listen(UserCreatedEvent::class, $high, priority: 10);

        $listeners = $this->dispatcher->getListeners(UserCreatedEvent::class);

        $this->assertCount(2, $listeners);
        $this->assertSame($high, $listeners[0]);
        $this->assertSame($low, $listeners[1]);
    }

    public function testEventServiceProviderRegistersDispatcher(): void
    {
        $container = new Container();
        $provider = new EventServiceProvider();
        $provider->register($container);

        $dispatcher = $container->get(EventDispatcherInterface::class);

        $this->assertInstanceOf(EventDispatcher::class, $dispatcher);
    }

    public function testEventServiceProviderRegistersSingleton(): void
    {
        $container = new Container();
        $provider = new EventServiceProvider();
        $provider->register($container);

        $a = $container->get(EventDispatcherInterface::class);
        $b = $container->get(EventDispatcherInterface::class);

        $this->assertSame($a, $b);
    }
}
