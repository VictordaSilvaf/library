<?php

namespace App\Ordering\Domain\ValueObject;

use App\Shared\Domain\ValueObject\Money;

class Product
{
    public function __construct(
        public readonly string $title,
        public readonly string $author,
        public readonly Money $price,
    ) {
        $this->validatePrice($price);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getPrice(): Money
    {
        return $this->price;
    }

    private function validatePrice(Money $price)
    {
        if ($price->getAmount() <= 0) {
            throw new \InvalidArgumentException('Price must be greater than 0');
        }
    }
}
