<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

use App\Catalog\Domain\Aggregate\Catalog;
use App\Catalog\Domain\Repository\CatalogRepository;
use App\Catalog\Domain\ValueObject\CatalogCollection;
use App\Catalog\Domain\ValueObject\CatalogId;
use App\Catalog\Domain\ValueObject\CatalogStatus;
use App\Shared\Infrastructure\Persistence\DoctrineEventStore;
use App\Shared\Infrastructure\Persistence\DomainEventSerializer;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCatalogRepository implements CatalogRepository
{
    private DoctrineEventStore $store;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $connection = $entityManager->getConnection();
        DoctrineEventStore::ensureSchema($connection);
        $this->store = new DoctrineEventStore($connection, new DomainEventSerializer());
    }

    public function save(Catalog $catalog): array
    {
        $events = $catalog->pendingEvents();
        $this->store->append(
            $catalog->getId()->getValue(),
            $catalog->getVersion() - count($events),
            $events,
        );
        $catalog->releaseEvents();
        $this->upsert($catalog);

        return $events;
    }

    public function findById(CatalogId $id): ?Catalog
    {
        $history = $this->store->load($id->getValue());

        if ($history === []) {
            return null;
        }

        return Catalog::reconstitute($history);
    }

    public function findAll(): CatalogCollection
    {
        return new CatalogCollection($this->catalogsFrom('SELECT id FROM catalogs'));
    }

    public function delete(Catalog $catalog): void
    {
        $id = $catalog->getId()->getValue();
        $this->store->delete($id);
        $this->entityManager->getConnection()->delete('catalogs', ['id' => $id]);
    }

    public function findByStatus(CatalogStatus $status): CatalogCollection
    {
        return new CatalogCollection($this->catalogsFrom(
            'SELECT id FROM catalogs WHERE status = ?',
            [$status->getValue()],
        ));
    }

    /**
     * @param list<mixed> $parameters
     * @return list<Catalog>
     */
    private function catalogsFrom(string $sql, array $parameters = []): array
    {
        /** @var list<string> $ids */
        $ids = $this->entityManager->getConnection()->fetchFirstColumn($sql, $parameters);
        $catalogs = [];

        foreach ($ids as $id) {
            $catalog = $this->findById(new CatalogId($id));

            if ($catalog !== null) {
                $catalogs[] = $catalog;
            }
        }

        return $catalogs;
    }

    private function upsert(Catalog $catalog): void
    {
        $images = array_map(
            static fn ($image): string => $image->url,
            $catalog->getImages()->all(),
        );

        $this->entityManager->getConnection()->executeStatement(
            'INSERT INTO catalogs (
                id, sku, name, description, price_amount, price_currency, category_id,
                status, images, created_at, updated_at, confirmed_at, cancelled_at, version
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET
                name = excluded.name,
                description = excluded.description,
                price_amount = excluded.price_amount,
                price_currency = excluded.price_currency,
                status = excluded.status,
                images = excluded.images,
                updated_at = excluded.updated_at,
                confirmed_at = excluded.confirmed_at,
                cancelled_at = excluded.cancelled_at,
                version = excluded.version',
            [
                $catalog->getId()->getValue(),
                $catalog->getSku()->value(),
                $catalog->getName(),
                $catalog->getDescription(),
                $catalog->getPrice()->getAmount(),
                $catalog->getPrice()->getCurrency(),
                $catalog->getCategoryId()->getValue(),
                $catalog->getStatus()->getValue(),
                json_encode($images, JSON_THROW_ON_ERROR),
                $catalog->getCreatedAt()->format(DateTimeInterface::ATOM),
                $catalog->getUpdatedAt()->format(DateTimeInterface::ATOM),
                $catalog->getConfirmedAt()?->format(DateTimeInterface::ATOM),
                $catalog->getCancelledAt()?->format(DateTimeInterface::ATOM),
                $catalog->getVersion(),
            ],
        );
    }
}
