<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Event;

use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Event\CatalogDescriptionChanged;
use App\Catalog\Domain\Event\CatalogImagesReplaced;
use App\Catalog\Domain\Event\CatalogNameChanged;
use App\Catalog\Domain\Event\CatalogPriceChanged;
use App\Catalog\Domain\Event\CatalogPublished;
use App\Catalog\Domain\Event\CatalogWithdrawn;
use App\Shared\Domain\Event\DomainEventLogger;
use Psr\Log\LoggerInterface;

final class CatalogEventLogger implements DomainEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(object $event): void
    {
        if ($event instanceof CatalogCreated) {
            $this->logger->info("CatalogCreated received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogPublished) {
            $this->logger->info("CatalogPublished received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogWithdrawn) {
            $this->logger->info("CatalogWithdrawn received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogNameChanged) {
            $this->logger->info("CatalogNameChanged received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogDescriptionChanged) {
            $this->logger->info("CatalogDescriptionChanged received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogPriceChanged) {
            $this->logger->info("CatalogPriceChanged received for catalog {$event->getCatalogId()->getValue()}.");

            return;
        }

        if ($event instanceof CatalogImagesReplaced) {
            $this->logger->info("CatalogImagesReplaced received for catalog {$event->getCatalogId()->getValue()}.");
        }
    }
}
