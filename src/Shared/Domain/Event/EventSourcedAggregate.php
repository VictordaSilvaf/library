<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

trait EventSourcedAggregate
{
    /** @var list<DomainEvent> */
    private array $pendingEvents = [];

    private int $aggregateVersion = 0;

    abstract protected function apply(DomainEvent $event): void;

    protected function record(DomainEvent $event): void
    {
        $this->apply($event);
        $this->pendingEvents[] = $event;
        $this->aggregateVersion++;
    }

    /**
     * @param list<DomainEvent> $history
     */
    protected function replay(array $history): void
    {
        foreach ($history as $event) {
            $this->apply($event);
            $this->aggregateVersion++;
        }
    }

    /**
     * @return list<DomainEvent>
     */
    public function pendingEvents(): array
    {
        return $this->pendingEvents;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->pendingEvents;
        $this->pendingEvents = [];

        return $events;
    }

    public function getVersion(): int
    {
        return $this->aggregateVersion;
    }
}
