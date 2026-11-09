<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Event;

use App\Ordering\Application\Event\EventDispatcher;

final class SynchronousEventDispatcher implements EventDispatcher
{
    /**
     * @param list<EventListener> $listeners
     */
    public function __construct(
        private readonly array $listeners,
    ) {
    }

    public function dispatch(object $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}
