<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\CategoryId;
use App\Catalog\Domain\ValueObject\ImageCollection;
use App\Catalog\Domain\ValueObject\Sku;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\Event\StoredEvent;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

class CatalogCreated implements CatalogEvent
{
    use StoredEvent;
    use IdentifiesCatalog;

    public function __construct(
        public readonly EventId $eventId,
        public readonly CatalogId $catalogId,
        public readonly Sku $sku,
        public readonly string $name,
        public readonly string $description,
        public readonly Money $price,
        public readonly CategoryId $categoryId,
        public readonly ImageCollection $images,
        public readonly CatalogStatus $status,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
