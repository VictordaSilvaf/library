<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Command\PublishCatalog;
use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepository;

final class PublishCatalogHandler
{
    public function __construct(
        private readonly CatalogRepository $catalogs,
    ) {
    }

    public function handle(PublishCatalog $command): Catalog
    {
        $catalog = $this->catalogs->findById($command->getCatalogId());

        if ($catalog === null) {
            throw new CatalogNotFoundException();
        }

        $catalog->publish($command->getOccurredAt());
        $this->catalogs->save($catalog);

        return $catalog;
    }
}
