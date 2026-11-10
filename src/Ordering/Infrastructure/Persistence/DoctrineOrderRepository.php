<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Repository\OrderRepository;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderCollection;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Shared\Infrastructure\Persistence\DoctrineEventStore;
use App\Shared\Infrastructure\Persistence\DomainEventSerializer;
use App\Shared\Infrastructure\Persistence\EventStreamWriter;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineOrderRepository implements OrderRepository
{
    private EventStreamWriter $streams;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        DomainEventSerializer $serializer,
    ) {
        $connection = $entityManager->getConnection();
        DoctrineEventStore::ensureSchema($connection);
        $this->streams = new EventStreamWriter(
            $connection,
            new DoctrineEventStore($connection, $serializer),
            new OrderProjector($connection),
        );
    }

    public function save(Order $order): array
    {
        $events = $order->pendingEvents();
        $this->streams->commit($order->getId()->getValue(), $order->getVersion(), $events);
        $order->releaseEvents();

        return $events;
    }

    public function findById(OrderId $id): ?Order
    {
        $history = $this->streams->load($id->getValue());

        if ($history === []) {
            return null;
        }

        return Order::reconstitute($history);
    }

    public function findByCustomerId(CustomerId $customerId): ?Order
    {
        $id = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM orders WHERE customer_id = ?',
            [$customerId->getValue()],
        );

        if (!is_string($id) || $id === '') {
            return null;
        }

        return $this->findById(new OrderId($id));
    }

    public function findAll(): ?OrderCollection
    {
        return new OrderCollection($this->ordersFrom('SELECT id FROM orders'));
    }

    public function delete(Order $order): void
    {
        $id = $order->getId()->getValue();
        $this->streams->delete($id);
        $this->entityManager->getConnection()->delete('orders', ['id' => $id]);
    }

    public function findByStatus(OrderStatus $status): ?OrderCollection
    {
        return new OrderCollection($this->ordersFrom(
            'SELECT id FROM orders WHERE status = ?',
            [$status->getValue()],
        ));
    }

    /**
     * @param list<mixed> $parameters
     * @return list<Order>
     */
    private function ordersFrom(string $sql, array $parameters = []): array
    {
        /** @var list<string> $ids */
        $ids = $this->entityManager->getConnection()->fetchFirstColumn($sql, $parameters);
        $orders = [];

        foreach ($ids as $id) {
            $order = $this->findById(new OrderId($id));

            if ($order !== null) {
                $orders[] = $order;
            }
        }

        return $orders;
    }
}
