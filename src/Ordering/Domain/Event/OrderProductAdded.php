<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\Product;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

class OrderProductAdded implements DomainEvent
{
    public function __construct(
        public readonly EventId $eventId,
        public readonly OrderId $orderId,
        public readonly Product $product,
        public readonly Money $total,
        public readonly DateTimeImmutable $occurredAt,
    ) {
    }
}
