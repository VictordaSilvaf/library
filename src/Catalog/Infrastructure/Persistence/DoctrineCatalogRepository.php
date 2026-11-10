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
use App\Shared\Infrastructure\Persistence\EventStreamWriter;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCatalogRepository implements CatalogRepository
{
    private EventStreamWriter $streams;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        DomainEventSerializer $serializer,
    ) {
        $connection = $entityManager->getConnection();
        DoctrineEventStore::ensureSchema($connection);
        $this->streams = new EventStreamWriter(
            $connection,
            new DoctrineEventStore($connection, $serializer),
            new CatalogProjector($connection),
        );
    }

    public function save(Catalog $catalog): array
    {
        $events = $catalog->pendingEvents();
        $this->streams->commit($catalog->getId()->getValue(), $catalog->getVersion(), $events);
        $catalog->releaseEvents();

        return $events;
    }

    public function findById(CatalogId $id): ?Catalog
    {
        $history = $this->streams->load($id->getValue());

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
        $this->streams->delete($id);
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
}
