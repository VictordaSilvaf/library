<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\OrderId;

class ConfirmOrder
{
    public function __construct(
        private readonly OrderId $orderId,
    ) {
    }

    public function getOrderId(): OrderId
    {
        return $this->orderId;
    }
}
