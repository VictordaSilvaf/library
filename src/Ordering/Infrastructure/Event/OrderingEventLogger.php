<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Event;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Shared\Domain\Event\DomainEventLogger;
use Psr\Log\LoggerInterface;

final class OrderingEventLogger implements DomainEventLogger
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(object $event): void
    {
        if ($event instanceof OrderCreated) {
            $this->logger->info("OrderCreated received for order {$event->orderId->getValue()}.");

            return;
        }

        if ($event instanceof OrderConfirmed) {
            $this->logger->info("OrderConfirmed received for order {$event->orderId->getValue()}.");

            return;
        }

        if ($event instanceof OrderCancelled) {
            $this->logger->info("OrderCancelled received for order {$event->orderId->getValue()}.");
        }
    }
}
