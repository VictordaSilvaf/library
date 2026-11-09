<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Event;

use App\Ordering\Domain\ValueObject\{
    OrderId,
    CustomerId,
    ProductCollection,
    Money,
};

class OrderConfirmed
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly CustomerId $customerId,
        public readonly ProductCollection $products,
        public readonly Money $total,
    ) {
    }
}
