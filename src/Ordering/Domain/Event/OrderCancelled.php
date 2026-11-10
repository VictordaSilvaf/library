<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\{
    OrderId,
    CustomerId,
    ProductCollection,
};
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\ValueObject\Money;

class OrderCancelled implements DomainEvent
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly CustomerId $customerId,
        public readonly ProductCollection $products,
        public readonly Money $total,
    ) {
    }
}
