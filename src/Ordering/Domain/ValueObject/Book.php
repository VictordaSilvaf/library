<?php

namespace App\Ordering\Domain\ValueObject;

class Book
{
    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly Money $price,
    ) {
        $this->validatePrice($price);
    }

    private function validatePrice(Money $price)
    {
        if ($price->getAmount() <= '0') {
            throw new \InvalidArgumentException('Price must be greater than 0');
        }
    }
}