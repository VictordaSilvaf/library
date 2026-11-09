<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;

class CreateOrder
{
    private readonly ProductCollection $products;

    public function __construct(
        private readonly CustomerId $customerId,
        ProductCollection $products,
    ) {
        $this->products = $products->copy();
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getProducts(): ProductCollection
    {
        return $this->products->copy();
    }
}
