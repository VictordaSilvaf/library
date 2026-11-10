<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\Event\StoredEvent;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

class OrderConfirmed implements DomainEvent
{
    use StoredEvent;

    public function __construct(
        public readonly EventId $eventId,
        public readonly OrderId $orderId,
        public readonly CustomerId $customerId,
        public readonly ProductCollection $products,
        public readonly Money $total,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
