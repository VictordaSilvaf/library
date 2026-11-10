<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use App\Catalog\Application\Command\ConfirmCatalog;
use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Exception\CatalogNotFoundException;
use App\Catalog\Domain\Repository\CatalogRepository;
use App\Shared\Domain\Event\EventDispatcher;

final class ConfirmCatalogHandler
{
    public function __construct(
        private readonly CatalogRepository $catalogs,
        private readonly EventDispatcher $events,
    ) {
    }

    public function handle(ConfirmCatalog $command): Catalog
    {
        $catalog = $this->catalogs->findById($command->getCatalogId());

        if ($catalog === null) {
            throw new CatalogNotFoundException();
        }

        $catalog->confirm($command->getOccurredAt());

        foreach ($this->catalogs->save($catalog) as $event) {
            $this->events->dispatch($event);
        }

        return $catalog;
    }
}
