<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Repository;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderCollection;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;

interface OrderRepository
{
    /**
     * @return list<\App\Shared\Domain\Event\DomainEvent>
     */
    public function save(Order $order): array;

    public function findById(OrderId $id): ?Order;

    public function findByCustomerId(CustomerId $customerId): ?Order;

    public function findAll(): ?OrderCollection;

    public function delete(Order $order): void;

    public function findByStatus(OrderStatus $status): ?OrderCollection;
}
