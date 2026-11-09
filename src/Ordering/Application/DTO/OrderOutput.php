<?php

declare(strict_types=1);

namespace App\Ordering\Application\DTO;

use App\Ordering\Domain\Aggregate\Order;
use JsonSerializable;

class OrderOutput implements JsonSerializable
{
    /**
     * @param list<array{title: string, author: string, price: array{amount: int, currency: string}}> $products
     */
    private function __construct(
        private readonly string $id,
        private readonly string $customerId,
        private readonly string $status,
        private readonly int $totalAmount,
        private readonly string $currency,
        private readonly array $products,
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        $products = [];

        foreach ($order->getProducts()->getProducts() as $product) {
            $products[] = [
                'title' => $product->getTitle(),
                'author' => $product->getAuthor(),
                'price' => [
                    'amount' => $product->getPrice()->getAmount(),
                    'currency' => $product->getPrice()->getCurrency(),
                ],
            ];
        }

        return new self(
            $order->getId()->getValue(),
            $order->getCustomerId()->getValue(),
            $order->getStatus()->getValue(),
            $order->getTotal()->getAmount(),
            $order->getTotal()->getCurrency(),
            $products,
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'customerId' => $this->customerId,
            'status' => $this->status,
            'total' => [
                'amount' => $this->totalAmount,
                'currency' => $this->currency,
            ],
            'products' => $this->products,
        ];
    }
}
