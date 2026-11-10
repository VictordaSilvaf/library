<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use Doctrine\ORM\Mapping as ORM;

trait RecordsDomainEvents
{
    /** @var list<DomainEvent> */
    private array $domainEvents = [];

    protected function record(DomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /**
     * @return list<DomainEvent>
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    #[ORM\PostLoad]
    public function initializeTransientState(): void
    {
        $this->domainEvents = [];
    }
}
