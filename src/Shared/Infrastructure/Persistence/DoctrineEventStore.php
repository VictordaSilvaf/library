<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Exception\ConcurrentModificationException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class DoctrineEventStore
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DomainEventSerializer $serializer,
    ) {
    }

    public static function ensureSchema(Connection $connection): void
    {
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS domain_events (
                stream_id VARCHAR(36) NOT NULL,
                version INTEGER NOT NULL,
                event_id VARCHAR(36) NOT NULL,
                event_type VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                occurred_at VARCHAR(40) NOT NULL,
                published_at VARCHAR(40) NULL,
                PRIMARY KEY (stream_id, version)
            )'
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS orders (
                id VARCHAR(36) PRIMARY KEY,
                customer_id VARCHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL,
                total_amount INTEGER NOT NULL,
                total_currency VARCHAR(3) NOT NULL,
                products TEXT NOT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                confirmed_at VARCHAR(40) NULL,
                cancelled_at VARCHAR(40) NULL
            )'
        );
        $connection->executeStatement(
            'CREATE TABLE IF NOT EXISTS catalogs (
                id VARCHAR(36) PRIMARY KEY,
                sku VARCHAR(64) NOT NULL,
                name VARCHAR(255) NOT NULL,
                description TEXT NOT NULL,
                price_amount INTEGER NOT NULL,
                price_currency VARCHAR(3) NOT NULL,
                category_id VARCHAR(36) NOT NULL,
                status VARCHAR(32) NOT NULL,
                images TEXT NOT NULL,
                created_at VARCHAR(40) NOT NULL,
                updated_at VARCHAR(40) NOT NULL,
                published_at VARCHAR(40) NULL,
                withdrawn_at VARCHAR(40) NULL,
                version INTEGER NOT NULL
            )'
        );
        self::ensureColumn($connection, 'domain_events', 'published_at', 'VARCHAR(40) NULL');
        self::ensureColumn($connection, 'catalogs', 'published_at', 'VARCHAR(40) NULL');
        self::ensureColumn($connection, 'catalogs', 'withdrawn_at', 'VARCHAR(40) NULL');
    }

    private static function ensureColumn(Connection $connection, string $table, string $column, string $definition): void
    {
        foreach (array_keys($connection->createSchemaManager()->listTableColumns($table)) as $name) {
            if (strtolower((string) $name) === $column) {
                return;
            }
        }

        $connection->executeStatement(sprintf('ALTER TABLE %s ADD COLUMN %s %s', $table, $column, $definition));
    }

    /**
     * @param list<DomainEvent> $events
     */
    public function append(string $streamId, int $expectedVersion, array $events): void
    {
        if ($events === []) {
            return;
        }

        $this->connection->transactional(function () use ($streamId, $expectedVersion, $events): void {
            $current = (int) $this->connection->fetchOne(
                'SELECT COALESCE(MAX(version), 0) FROM domain_events WHERE stream_id = ?',
                [$streamId],
            );

            if ($current !== $expectedVersion) {
                throw new ConcurrentModificationException($streamId, $expectedVersion, $current);
            }

            $version = $expectedVersion;

            foreach ($events as $event) {
                $version++;
                $this->connection->insert('domain_events', [
                    'stream_id' => $streamId,
                    'version' => $version,
                    'event_id' => $this->eventId($event),
                    'event_type' => $event::class,
                    'payload' => $this->serializer->serialize($event),
                    'occurred_at' => $this->occurredAt($event),
                ], [
                    'version' => ParameterType::INTEGER,
                ]);
            }
        });
    }

    /**
     * @return list<DomainEvent>
     */
    public function load(string $streamId): array
    {
        /** @var list<array{event_type: string, payload: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT event_type, payload FROM domain_events WHERE stream_id = ? ORDER BY version ASC',
            [$streamId],
        );

        return array_map(
            fn (array $row): DomainEvent => $this->serializer->deserialize($row['event_type'], $row['payload']),
            $rows,
        );
    }

    public function delete(string $streamId): void
    {
        $this->connection->delete('domain_events', ['stream_id' => $streamId]);
    }

    private function eventId(DomainEvent $event): string
    {
        return $event->getEventId()->getValue();
    }

    private function occurredAt(DomainEvent $event): string
    {
        return $event->getOccurredAt()->format(\DateTimeInterface::ATOM);
    }
}
