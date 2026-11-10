<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\Event\StoredEvent;
use DateTimeImmutable;

class CatalogImagesReplaced implements CatalogEvent
{
    use StoredEvent;
    use IdentifiesCatalog;

    public function __construct(
        public readonly EventId $eventId,
        public readonly CatalogId $catalogId,
        public readonly ImageCollection $images,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
