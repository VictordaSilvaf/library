<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;
use App\Shared\Domain\Event\DomainEvent;

interface CatalogEvent extends DomainEvent
{
    public function getCatalogId(): CatalogId;
}
