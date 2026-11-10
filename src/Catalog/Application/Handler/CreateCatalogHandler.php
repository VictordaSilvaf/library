<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Repository\CatalogRepository;

final class CreateCatalogHandler
{
    public function __construct(
        private readonly CatalogRepository $catalogs,
    ) {
    }

    public function handle(CreateCatalog $command): Catalog
    {
        $catalog = Catalog::create(
            $command->getCatalogId(),
            $command->getSku(),
            $command->getName(),
            $command->getDescription(),
            $command->getPrice(),
            $command->getCategoryId(),
            $command->getImages(),
            $command->getOccurredAt(),
        );
        $this->catalogs->save($catalog);

        return $catalog;
    }
}
