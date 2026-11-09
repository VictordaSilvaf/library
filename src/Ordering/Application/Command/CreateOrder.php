<?php

declare(strict_types=1);

namespace App\Ordering\Application\Command;

use App\Ordering\Domain\ValueObject\BookCollection;
use App\Ordering\Domain\ValueObject\CustomerId;

class CreateOrder
{
    private readonly BookCollection $books;

    public function __construct(
        private readonly CustomerId $customerId,
        BookCollection $books,
    ) {
        $this->books = $books->copy();
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getBooks(): BookCollection
    {
        return $this->books->copy();
    }
}
