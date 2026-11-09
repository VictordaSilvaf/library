<?php

namespace App\Ordering\Domain\ValueObject;

class ProductCollection
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        private array $products,
    ) {
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function addProduct(Product $product)
    {
        $this->products[] = $product;
    }

    public function removeProduct(Product $product)
    {
        $this->products = array_filter($this->products, fn($item) => $item->title !== $product->title);
    }

    public function isEmpty(): bool
    {
        return count($this->products) === 0;
    }

    public function copy(): self
    {
        return new self($this->products);
    }
}
