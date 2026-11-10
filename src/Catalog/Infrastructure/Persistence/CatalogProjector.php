<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Event\CatalogEvent;
use App\Catalog\Domain\Event\CatalogCreated;
use App\Catalog\Domain\Event\CatalogDescriptionChanged;
use App\Catalog\Domain\Event\CatalogImagesReplaced;
use App\Catalog\Domain\Event\CatalogNameChanged;
use App\Catalog\Domain\Event\CatalogPriceChanged;
use App\Catalog\Domain\Event\CatalogPublished;
use App\Catalog\Domain\Event\CatalogWithdrawn;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Catalog\Domain\ValueObject\Image;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Persistence\Projector;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;

final class CatalogProjector implements Projector
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function apply(DomainEvent $event): void
    {
        if ($event instanceof CatalogCreated) {
            $this->insert($event);

            return;
        }

        if ($event instanceof CatalogPublished) {
            $this->touch($event, 'status = ?, published_at = ?, updated_at = ?, version = version + 1', [
                CatalogStatus::PUBLISHED->value,
                $this->at($event),
                $this->at($event),
            ]);

            return;
        }

        if ($event instanceof CatalogWithdrawn) {
            $this->touch($event, 'status = ?, withdrawn_at = ?, updated_at = ?, version = version + 1', [
                CatalogStatus::WITHDRAWN->value,
                $this->at($event),
                $this->at($event),
            ]);

            return;
        }

        if ($event instanceof CatalogNameChanged) {
            $this->touch($event, 'name = ?, updated_at = ?, version = version + 1', [
                $event->newName,
                $this->at($event),
            ]);

            return;
        }

        if ($event instanceof CatalogDescriptionChanged) {
            $this->touch($event, 'description = ?, updated_at = ?, version = version + 1', [
                $event->newDescription,
                $this->at($event),
            ]);

            return;
        }

        if ($event instanceof CatalogPriceChanged) {
            $this->touch($event, 'price_amount = ?, price_currency = ?, updated_at = ?, version = version + 1', [
                $event->newPrice->getAmount(),
                $event->newPrice->getCurrency(),
                $this->at($event),
            ]);

            return;
        }

        if ($event instanceof CatalogImagesReplaced) {
            $this->touch($event, 'images = ?, updated_at = ?, version = version + 1', [
                json_encode($this->urls($event->images->all()), JSON_THROW_ON_ERROR),
                $this->at($event),
            ]);

            return;
        }

        throw new RuntimeException('Catalog projector cannot apply ' . $event::class . '.');
    }

    private function insert(CatalogCreated $event): void
    {
        $this->connection->executeStatement(
            'INSERT INTO catalogs (
                id, sku, name, description, price_amount, price_currency, category_id,
                status, images, created_at, updated_at, published_at, withdrawn_at, version
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL, 1)',
            [
                $event->catalogId->getValue(),
                $event->sku->value(),
                $event->name,
                $event->description,
                $event->price->getAmount(),
                $event->price->getCurrency(),
                $event->categoryId->getValue(),
                $event->status->value,
                json_encode($this->urls($event->images->all()), JSON_THROW_ON_ERROR),
                $this->at($event),
                $this->at($event),
            ],
        );
    }

    /**
     * @param list<mixed> $parameters
     */
    private function touch(DomainEvent $event, string $assignments, array $parameters): void
    {
        $id = $this->catalogId($event);
        $parameters[] = $id;
        $this->connection->executeStatement(
            'UPDATE catalogs SET ' . $assignments . ' WHERE id = ?',
            $parameters,
        );
    }

    private function catalogId(DomainEvent $event): string
    {
        if (!$event instanceof CatalogEvent) {
            throw new RuntimeException('Catalog event has no catalog id.');
        }

        return $event->getCatalogId()->getValue();
    }

    /**
     * @param list<Image> $images
     * @return list<string>
     */
    private function urls(array $images): array
    {
        return array_map(static fn (Image $image): string => $image->url, $images);
    }

    private function at(DomainEvent $event): string
    {
        return $event->getOccurredAt()->format(DateTimeInterface::ATOM);
    }
}
