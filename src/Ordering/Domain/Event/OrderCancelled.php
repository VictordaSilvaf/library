<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\{
    OrderId,
    CustomerId,
    BookCollection,
    Money,
};

class OrderCancelled
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly CustomerId $customerId,
        public readonly BookCollection $books,
        public readonly Money $total,
    ) {}
}
