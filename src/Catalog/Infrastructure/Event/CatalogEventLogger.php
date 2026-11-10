<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Event;

use App\Catalog\Domain\Event\CatalogCancelled;
use App\Catalog\Domain\Event\CatalogConfirmed;
use App\Catalog\Domain\Event\CatalogCreated;
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
            $this->logger->info("CatalogCreated received for catalog {$event->catalogId->getValue()}.");

            return;
        }

        if ($event instanceof CatalogConfirmed) {
            $this->logger->info("CatalogConfirmed received for catalog {$event->catalogId->getValue()}.");

            return;
        }

        if ($event instanceof CatalogCancelled) {
            $this->logger->info("CatalogCancelled received for catalog {$event->catalogId->getValue()}.");
        }
    }
}
