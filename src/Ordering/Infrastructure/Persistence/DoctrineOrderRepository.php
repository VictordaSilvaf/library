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
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineOrderRepository implements OrderRepository
{
    private DoctrineEventStore $store;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        $connection = $entityManager->getConnection();
        DoctrineEventStore::ensureSchema($connection);
        $this->store = new DoctrineEventStore($connection, new DomainEventSerializer());
    }

    public function save(Order $order): array
    {
        $events = $order->pendingEvents();
        $this->store->append(
            $order->getId()->getValue(),
            $order->getVersion() - count($events),
            $events,
        );
        $order->releaseEvents();
        $this->upsert($order);

        return $events;
    }

    public function findById(OrderId $id): ?Order
    {
        $history = $this->store->load($id->getValue());

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
        $this->store->delete($id);
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

    private function upsert(Order $order): void
    {
        $connection = $this->entityManager->getConnection();
        $products = [];

        foreach ($order->getProducts()->getProducts() as $product) {
            $products[] = [
                'title' => $product->getTitle(),
                'author' => $product->getAuthor(),
                'amount' => $product->getPrice()->getAmount(),
                'currency' => $product->getPrice()->getCurrency(),
            ];
        }

        $connection->executeStatement(
            'INSERT INTO orders (
                id, customer_id, status, total_amount, total_currency, products,
                created_at, updated_at, confirmed_at, cancelled_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT (id) DO UPDATE SET
                status = excluded.status,
                total_amount = excluded.total_amount,
                total_currency = excluded.total_currency,
                products = excluded.products,
                updated_at = excluded.updated_at,
                confirmed_at = excluded.confirmed_at,
                cancelled_at = excluded.cancelled_at',
            [
                $order->getId()->getValue(),
                $order->getCustomerId()->getValue(),
                $order->getStatus()->getValue(),
                $order->getTotal()->getAmount(),
                $order->getTotal()->getCurrency(),
                json_encode($products, JSON_THROW_ON_ERROR),
                $order->getCreatedAt()->format(DateTimeInterface::ATOM),
                $order->getUpdatedAt()->format(DateTimeInterface::ATOM),
                $order->getConfirmedAt()?->format(DateTimeInterface::ATOM),
                $order->getCancelledAt()?->format(DateTimeInterface::ATOM),
            ],
        );
    }
}
