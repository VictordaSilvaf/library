<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObjects\{
    CatalogId,
    Sku,
    CategoryId,
    CatalogStatus,
};
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;

class CatalogCreated implements DomainEvent
{
    public function __construct(
        public readonly CatalogId $catalogId,
        public readonly Sku $sku,
        public readonly string $name,
        public readonly string $description,
        public readonly Money $price,
        public readonly CategoryId $categoryId,
        public readonly CatalogStatus $status,
    ) {
    }
}
