<?php

declare(strict_types=1);

namespace Melodic\Event;

interface EventDispatcherInterface
{
    public function dispatch(object $event): object;

    public function listen(string $eventClass, callable $listener, int $priority = 0): void;
}
