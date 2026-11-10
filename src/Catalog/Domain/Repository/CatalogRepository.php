<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\ValueObject\CatalogCollection;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;

interface CatalogRepository
{
    /**
     * @return list<\App\Shared\Domain\Event\DomainEvent>
     */
    public function save(Catalog $catalog): array;

    public function findById(CatalogId $id): ?Catalog;

    public function findAll(): CatalogCollection;

    public function delete(Catalog $catalog): void;

    public function findByStatus(CatalogStatus $status): CatalogCollection;
}
