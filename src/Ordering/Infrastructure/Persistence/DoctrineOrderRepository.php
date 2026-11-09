<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderCollection;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Infrastructure\Persistence\Doctrine\DoctrineTypes;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineOrderRepository implements OrderRepository
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        DoctrineTypes::register();
    }

    public function save(Order $order): void
    {
        $this->entityManager->persist($order);
        $this->entityManager->flush();
    }

    public function findById(OrderId $id): ?Order
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id->getValue());

        return $order instanceof Order ? $order : null;
    }

    public function findByCustomerId(CustomerId $customerId): ?Order
    {
        $order = $this->entityManager->getRepository(Order::class)->findOneBy([
            'customerId' => $customerId->getValue(),
        ]);

        return $order instanceof Order ? $order : null;
    }

    public function findAll(): ?OrderCollection
    {
        return new OrderCollection($this->entityManager->getRepository(Order::class)->findAll());
    }

    public function delete(Order $order): void
    {
        $this->entityManager->remove($order);
        $this->entityManager->flush();
    }

    public function findByStatus(OrderStatus $status): ?OrderCollection
    {
        return new OrderCollection($this->entityManager->getRepository(Order::class)->findBy(['status' => $status]));
    }
}
