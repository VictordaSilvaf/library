<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObjects\{
    CatalogStatus,
    CatalogId
};
use App\Shared\Domain\Event\DomainEvent;

class CatalogCancelled implements DomainEvent
{
    public function __construct(
        public readonly CatalogId $catalogId,
        public readonly CatalogStatus $status,
    ) {
    }
}
