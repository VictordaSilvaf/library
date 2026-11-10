<?php

declare(strict_types=1);

namespace Tests\Ordering\Support;

use App\Shared\Domain\Event\DomainEventLogger;

class RecordingListener implements DomainEventLogger
{
    /**
     * @var list<object>
     */
    public array $events = [];

    public function handle(object $event): void
    {
        $this->events[] = $event;
    }
}
