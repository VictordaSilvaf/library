<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventId;
use DateTimeImmutable;

class CatalogImagesReplaced implements DomainEvent
{
    public function __construct(
        public readonly EventId $eventId,
        public readonly CatalogId $catalogId,
        public readonly ImageCollection $images,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
