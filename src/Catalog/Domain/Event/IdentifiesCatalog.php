<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Catalog\Domain\ValueObject\CatalogId;

trait IdentifiesCatalog
{
    public function getCatalogId(): CatalogId
    {
        return $this->catalogId;
    }
}
