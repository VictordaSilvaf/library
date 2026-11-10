<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Catalog;
use App\Catalog\Domain\ValueObject\{
    CatalogCollection,
    CatalogId,
    CatalogStatus,
};

interface CatalogRepository
{
    public function save(Catalog $catalog): void;

    public function findById(CatalogId $id): ?Catalog;

    public function findAll(): ?CatalogCollection;

    public function delete(Catalog $catalog): void;

    public function findByStatus(CatalogStatus $status): ?CatalogCollection;
}
