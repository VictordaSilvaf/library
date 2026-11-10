<?php

declare(strict_types=1);

namespace App\Ordering\Application\DTO;

use App\Ordering\Application\Command\CreateOrder;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use InvalidArgumentException;

class OrderInput
{
    /**
     * @param array<int, mixed> $products
     */
    private function __construct(
        private readonly string $customerId,
        private readonly array $products,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['customerId']) || !is_string($data['customerId']) || $data['customerId'] === '') {
            throw new InvalidArgumentException('Customer id is required');
        }

        if (!isset($data['products']) || !is_array($data['products'])) {
            throw new InvalidArgumentException('Products are required');
        }

        return new self($data['customerId'], $data['products']);
    }

    public function toCommand(): CreateOrder
    {
        $products = [];

        foreach ($this->products as $product) {
            if (!is_array($product)) {
                throw new InvalidArgumentException('Invalid product');
            }

            $products[] = new Product(
                self::stringField($product, 'title'),
                self::stringField($product, 'author'),
                Money::create(self::stringField($product, 'price'), self::stringField($product, 'currency'))
            );
        }

        return new CreateOrder(new CustomerId($this->customerId), new ProductCollection($products));
    }

    /**
     * @param array<mixed> $product
     */
    private static function stringField(array $product, string $field): string
    {
        if (!isset($product[$field]) || !is_string($product[$field]) || $product[$field] === '') {
            throw new InvalidArgumentException(sprintf('Product %s is required', $field));
        }

        return $product[$field];
    }
}
