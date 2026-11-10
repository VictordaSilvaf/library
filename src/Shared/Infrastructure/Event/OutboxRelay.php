<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Domain\Event\EventDispatcher;
use App\Shared\Infrastructure\Persistence\DomainEventSerializer;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;

final class OutboxRelay
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DomainEventSerializer $serializer,
        private readonly EventDispatcher $dispatcher,
    ) {
    }

    public function publishPending(): int
    {
        /** @var list<array{event_id: string, event_type: string, payload: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT event_id, event_type, payload
             FROM domain_events
             WHERE published_at IS NULL
             ORDER BY stream_id, version',
        );
        $published = 0;

        foreach ($rows as $row) {
            $event = $this->serializer->deserialize($row['event_type'], $row['payload']);
            $this->dispatcher->dispatch($event);
            $this->connection->executeStatement(
                'UPDATE domain_events SET published_at = ? WHERE event_id = ? AND published_at IS NULL',
                [
                    (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                    $row['event_id'],
                ],
            );
            $published++;
        }

        return $published;
    }
}
