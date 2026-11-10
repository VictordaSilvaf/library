<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence;

use App\Shared\Domain\Event\DomainEvent;
use Doctrine\DBAL\Connection;

final class EventStreamWriter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DoctrineEventStore $store,
        private readonly Projector $projector,
    ) {
    }

    /**
     * @param list<DomainEvent> $events
     */
    public function commit(string $streamId, int $aggregateVersion, array $events): void
    {
        $this->connection->transactional(function () use ($streamId, $aggregateVersion, $events): void {
            $this->store->append($streamId, $aggregateVersion - count($events), $events);

            foreach ($events as $event) {
                $this->projector->apply($event);
            }
        });
    }

    /**
     * @return list<DomainEvent>
     */
    public function load(string $streamId): array
    {
        return $this->store->load($streamId);
    }

    public function delete(string $streamId): void
    {
        $this->store->delete($streamId);
    }
}
