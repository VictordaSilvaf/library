<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

final class SynchronousEventDispatcher implements EventDispatcher
{
    /**
     * @param list<DomainEventLogger> $listeners
     */
    public function __construct(
        private readonly array $listeners,
    ) {
    }

    public function dispatch(DomainEvent $event): void
    {
        foreach ($this->listeners as $listener) {
            $listener->handle($event);
        }
    }
}
