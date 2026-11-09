<?php

declare(strict_types=1);

namespace Tests\Ordering\Support;

use App\Ordering\Infrastructure\Event\EventListener;

class RecordingListener implements EventListener
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
