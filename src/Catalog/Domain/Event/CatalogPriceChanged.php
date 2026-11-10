<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

class CatalogPriceChanged implements DomainEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly CatalogId $catalogId,
        public readonly Money $previousPrice,
        public readonly Money $newPrice,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
