<?php

namespace App\Ordering\Domain\ValueObject;

use App\Ordering\Domain\Aggregate\Order;

class OrderCollection
{
    /**
     * @param Order[] $orders
     */
    public function __construct(
        private array $orders,
    ) {
    }

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function addOrder(Order $order)
    {
        $this->orders[] = $order;
    }

    public function removeOrder(Order $order)
    {
        $this->orders = array_filter($this->orders, fn($o) => $o->getId() !== $order->getId());
    }

    public function isEmpty(): bool
    {
        return count($this->orders) === 0;
    }

    public function copy(): self
    {
        return new self($this->orders);
    }
}
