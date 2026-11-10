<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

class CatalogNameChanged implements DomainEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly CatalogId $catalogId,
        public readonly string $previousName,
        public readonly string $newName,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
