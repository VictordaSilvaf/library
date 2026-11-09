<?php

declare(strict_types=1);

namespace Tests\Ordering\Support;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderCollection;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;

class InMemoryOrderRepository implements OrderRepository
{
    /**
     * @var array<string, Order>
     */
    private array $orders = [];

    public function save(Order $order): void
    {
        $this->orders[$order->getId()->getValue()] = $order;
    }

    public function findById(OrderId $id): ?Order
    {
        return $this->orders[$id->getValue()] ?? null;
    }

    public function findByCustomerId(CustomerId $customerId): ?Order
    {
        foreach ($this->orders as $order) {
            if ($order->getCustomerId()->getValue() === $customerId->getValue()) {
                return $order;
            }
        }

        return null;
    }

    public function findAll(): ?OrderCollection
    {
        return new OrderCollection(array_values($this->orders));
    }

    public function delete(Order $order): void
    {
        unset($this->orders[$order->getId()->getValue()]);
    }

    public function findByStatus(OrderStatus $status): ?OrderCollection
    {
        $orders = [];

        foreach ($this->orders as $order) {
            if ($order->getStatus() === $status) {
                $orders[] = $order;
            }
        }

        return new OrderCollection($orders);
    }
}
