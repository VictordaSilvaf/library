<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Event\OrderProductAdded;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateException;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Domain\ValueObject\OrderStatus;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Event\EventId;
use App\Shared\Domain\Event\EventSourcedAggregate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

final class Order
{
    use EventSourcedAggregate;

    private OrderId $id;

    private CustomerId $customerId;

    private ProductCollection $products;

    private OrderStatus $status;

    private Money $total;

    private DateTimeImmutable $createdAt;

    private DateTimeImmutable $updatedAt;

    private ?DateTimeImmutable $confirmedAt = null;

    private ?DateTimeImmutable $cancelledAt = null;

    private function __construct()
    {
    }

    public static function create(CustomerId $customerId, ProductCollection $products): self
    {
        $items = array_values($products->getProducts());

        if ($items === []) {
            throw new EmptyOrderException('Products are required');
        }

        $total = $items[0]->getPrice();

        foreach (array_slice($items, 1) as $product) {
            $total = $total->add($product->getPrice());
        }

        $occurredAt = new DateTimeImmutable();
        $order = new self();
        $order->record(new OrderCreated(
            EventId::random(),
            OrderId::random(),
            $customerId,
            $products->copy(),
            $total,
            $occurredAt,
        ));

        return $order;
    }

    /**
     * @param list<DomainEvent> $history
     */
    public static function reconstitute(array $history): self
    {
        $order = new self();
        $order->replay($history);

        return $order;
    }

    public function confirm(): void
    {
        $this->validateConfirm();

        $this->record(new OrderConfirmed(
            EventId::random(),
            $this->id,
            $this->customerId,
            $this->products->copy(),
            $this->total,
            new DateTimeImmutable(),
        ));
    }

    public function cancel(): void
    {
        $this->validateCancel();

        $this->record(new OrderCancelled(
            EventId::random(),
            $this->id,
            $this->customerId,
            $this->products->copy(),
            $this->total,
            new DateTimeImmutable(),
        ));
    }

    public function addProduct(Product $product): void
    {
        $this->validateAddProduct();

        $this->record(new OrderProductAdded(
            EventId::random(),
            $this->id,
            $product,
            $this->total->add($product->getPrice()),
            new DateTimeImmutable(),
        ));
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }

    public function getProducts(): ProductCollection
    {
        return $this->products->copy();
    }

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function getTotal(): Money
    {
        return $this->total;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function getCancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    protected function apply(DomainEvent $event): void
    {
        if ($event instanceof OrderCreated) {
            $this->id = $event->orderId;
            $this->customerId = $event->customerId;
            $this->products = $event->products->copy();
            $this->status = OrderStatus::PENDING;
            $this->total = $event->total;
            $this->createdAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;
            $this->confirmedAt = null;
            $this->cancelledAt = null;

            return;
        }

        if ($event instanceof OrderConfirmed) {
            $this->status = OrderStatus::CONFIRMED;
            $this->confirmedAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof OrderCancelled) {
            $this->status = OrderStatus::CANCELLED;
            $this->cancelledAt = $event->occurredAt;
            $this->updatedAt = $event->occurredAt;

            return;
        }

        if ($event instanceof OrderProductAdded) {
            $items = $this->products->getProducts();
            $items[] = $event->product;
            $this->products = new ProductCollection($items);
            $this->total = $event->total;
            $this->updatedAt = $event->occurredAt;
        }
    }

    private function validateAddProduct(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }
    }

    private function validateConfirm(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }

        if ($this->products->isEmpty()) {
            throw new EmptyOrderException('Order has no products');
        }
    }

    private function validateCancel(): void
    {
        if (!$this->status->isPending()) {
            throw new InvalidOrderStateException('Order is not pending');
        }
    }
}
