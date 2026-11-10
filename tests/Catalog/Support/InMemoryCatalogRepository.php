<?php

declare(strict_types=1);

namespace Tests\Catalog\Support;

use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Repository\CatalogRepository;
use App\Catalog\Domain\ValueObject\CatalogCollection;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;

final class InMemoryCatalogRepository implements CatalogRepository
{
    /** @var array<string, Catalog> */
    private array $catalogs = [];

    public function save(Catalog $catalog): array
    {
        $events = $catalog->releaseEvents();
        $this->catalogs[$catalog->getId()->getValue()] = $catalog;

        return $events;
    }

    public function findById(CatalogId $id): ?Catalog
    {
        return $this->catalogs[$id->getValue()] ?? null;
    }

    public function findAll(): CatalogCollection
    {
        return new CatalogCollection(array_values($this->catalogs));
    }

    public function delete(Catalog $catalog): void
    {
        unset($this->catalogs[$catalog->getId()->getValue()]);
    }

    public function findByStatus(CatalogStatus $status): CatalogCollection
    {
        return new CatalogCollection(array_values(array_filter(
            $this->catalogs,
            static fn (Catalog $catalog): bool => $catalog->getStatus() === $status,
        )));
    }
}
