<?php

declare(strict_types=1);

namespace Tests\Ordering\Domain\Aggregate;

use App\Ordering\Domain\ValueObject\Book;
use App\Ordering\Domain\ValueObject\Money;

class PricedBook extends Book
{
    public function getPrice(): Money
    {
        return $this->price;
    }
}
