<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Command\CreateCatalog;
use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Repository\CatalogRepository;
use App\Shared\Domain\Event\EventDispatcher;

final class CreateCatalogHandler
{
    public function __construct(
        private readonly CatalogRepository $catalogs,
        private readonly EventDispatcher $events,
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

        foreach ($this->catalogs->save($catalog) as $event) {
            $this->events->dispatch($event);
        }

        return $catalog;
    }
}
