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
