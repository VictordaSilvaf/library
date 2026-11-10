<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Shared\Domain\Event\DomainEvent;
use DateTimeImmutable;

class CatalogCancelled implements DomainEvent
{
    public function __construct(
        public readonly string $eventId,
        public readonly CatalogId $catalogId,
        public readonly CatalogStatus $status,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
