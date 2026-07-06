<?php

declare(strict_types=1);

namespace Melodic\Event;

class EventDispatcher implements EventDispatcherInterface
{
    /** @var array<string, array<int, callable[]>> */
    private array $listeners = [];

    public function listen(string $eventClass, callable $listener, int $priority = 0): void
    {
        $this->listeners[$eventClass][$priority][] = $listener;
    }

    /**
     * Remove a previously registered listener (compared by identity). Useful
     * for test isolation and dynamically scoped subscriptions. Removing a
     * listener that was never registered is a no-op.
     */
    public function removeListener(string $eventClass, callable $listener): void
    {
        foreach ($this->listeners[$eventClass] ?? [] as $priority => $listeners) {
            foreach ($listeners as $index => $registered) {
                if ($registered === $listener) {
                    unset($this->listeners[$eventClass][$priority][$index]);
                }
            }

            if ($this->listeners[$eventClass][$priority] === []) {
                unset($this->listeners[$eventClass][$priority]);
            }
        }

        if (($this->listeners[$eventClass] ?? null) === []) {
            unset($this->listeners[$eventClass]);
        }
    }

    public function dispatch(object $event): object
    {
        $eventClass = $event::class;

        foreach ($this->getListeners($eventClass) as $listener) {
            if ($event instanceof Event && $event->isPropagationStopped()) {
                break;
            }

            $listener($event);
        }

        return $event;
    }

    /**
     * @return callable[]
     */
    public function getListeners(string $eventClass): array
    {
        if (!isset($this->listeners[$eventClass])) {
            return [];
        }

        $prioritized = $this->listeners[$eventClass];
        krsort($prioritized);

        $sorted = [];
        foreach ($prioritized as $listeners) {
            foreach ($listeners as $listener) {
                $sorted[] = $listener;
            }
        }

        return $sorted;
    }
}
