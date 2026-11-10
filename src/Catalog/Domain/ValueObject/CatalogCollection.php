<?php

declare(strict_types=1);

namespace App\Catalog\Domain\ValueObject;

use App\Catalog\Domain\Aggregate\Catalog;

final class CatalogCollection
{
    /**
     * @param list<Catalog> $catalogs
     */
    public function __construct(
        private readonly array $catalogs,
    ) {
    }

    /**
     * @return list<Catalog>
     */
    public function all(): array
    {
        return $this->catalogs;
    }

    public function isEmpty(): bool
    {
        return $this->catalogs === [];
    }
}
