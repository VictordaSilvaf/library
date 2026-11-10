<?php

declare(strict_types=1);

namespace App\Ordering\Infrastructure\Persistence;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Event\OrderProductAdded;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\Product;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Infrastructure\Persistence\Projector;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use RuntimeException;

final class OrderProjector implements Projector
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function apply(DomainEvent $event): void
    {
        if ($event instanceof OrderCreated) {
            $this->insert($event);

            return;
        }

        if ($event instanceof OrderConfirmed) {
            $this->connection->executeStatement(
                'UPDATE orders SET status = ?, updated_at = ?, confirmed_at = ? WHERE id = ?',
                [
                    OrderStatus::CONFIRMED->value,
                    $this->at($event),
                    $this->at($event),
                    $event->orderId->getValue(),
                ],
            );

            return;
        }

        if ($event instanceof OrderCancelled) {
            $this->connection->executeStatement(
                'UPDATE orders SET status = ?, updated_at = ?, cancelled_at = ? WHERE id = ?',
                [
                    OrderStatus::CANCELLED->value,
                    $this->at($event),
                    $this->at($event),
                    $event->orderId->getValue(),
                ],
            );

            return;
        }

        if ($event instanceof OrderProductAdded) {
            $this->addProduct($event);

            return;
        }

        throw new RuntimeException('Order projector cannot apply ' . $event::class . '.');
    }

    private function insert(OrderCreated $event): void
    {
        $products = array_map(
            fn (Product $product): array => $this->productRow($product),
            $event->products->getProducts(),
        );

        $this->connection->executeStatement(
            'INSERT INTO orders (
                id, customer_id, status, total_amount, total_currency, products,
                created_at, updated_at, confirmed_at, cancelled_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, NULL)',
            [
                $event->orderId->getValue(),
                $event->customerId->getValue(),
                OrderStatus::PENDING->value,
                $event->total->getAmount(),
                $event->total->getCurrency(),
                json_encode($products, JSON_THROW_ON_ERROR),
                $this->at($event),
                $this->at($event),
            ],
        );
    }

    private function addProduct(OrderProductAdded $event): void
    {
        $current = $this->connection->fetchOne(
            'SELECT products FROM orders WHERE id = ?',
            [$event->orderId->getValue()],
        );
        /** @var list<array{title: string, author: string, amount: int, currency: string}> $products */
        $products = json_decode(is_string($current) ? $current : '[]', true, 512, JSON_THROW_ON_ERROR);
        $products[] = $this->productRow($event->product);

        $this->connection->executeStatement(
            'UPDATE orders SET products = ?, total_amount = ?, total_currency = ?, updated_at = ? WHERE id = ?',
            [
                json_encode($products, JSON_THROW_ON_ERROR),
                $event->total->getAmount(),
                $event->total->getCurrency(),
                $this->at($event),
                $event->orderId->getValue(),
            ],
        );
    }

    /**
     * @return array{title: string, author: string, amount: int, currency: string}
     */
    private function productRow(Product $product): array
    {
        return [
            'title' => $product->getTitle(),
            'author' => $product->getAuthor(),
            'amount' => $product->getPrice()->getAmount(),
            'currency' => $product->getPrice()->getCurrency(),
        ];
    }

    private function at(DomainEvent $event): string
    {
        return $event->getOccurredAt()->format(DateTimeInterface::ATOM);
    }
}
