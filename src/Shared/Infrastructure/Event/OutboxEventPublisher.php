<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Event;

use App\Shared\Domain\Event\EventDispatcher;
use App\Shared\Domain\Event\EventPublisher;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;

final class OutboxEventPublisher implements EventPublisher
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EventDispatcher $dispatcher,
    ) {
    }

    public function publish(array $events): void
    {
        foreach ($events as $event) {
            $this->dispatcher->dispatch($event);
            $this->connection->executeStatement(
                'UPDATE domain_events SET published_at = ? WHERE event_id = ? AND published_at IS NULL',
                [
                    (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
                    $event->getEventId()->getValue(),
                ],
            );
        }
    }
}
