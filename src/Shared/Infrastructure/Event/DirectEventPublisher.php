<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventDispatcher;
use App\Shared\Domain\Event\EventPublisher;

final class DirectEventPublisher implements EventPublisher
{
    public function __construct(
        private readonly EventDispatcher $dispatcher,
    ) {
    }

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
        }
    }
}
