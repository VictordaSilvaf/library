<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

use DateTimeImmutable;

interface DomainEvent
{
    public function getEventId(): EventId;

    public function getOccurredAt(): DateTimeImmutable;
}
