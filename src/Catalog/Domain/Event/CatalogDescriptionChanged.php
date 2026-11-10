<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

class CatalogDescriptionChanged implements DomainEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly CatalogId $catalogId,
        public readonly string $previousDescription,
        public readonly string $newDescription,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
