<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Entity\OrderItem;
use App\Ordering\Domain\ValueObject\{
    OrderId,
    CustomerId,
    OrderStatus,
    Money
};
use App\Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

class Order
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param OrderItem[] $items
     */
    private function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private array $items,
        private OrderStatus $status,
        private Money $total,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public static function create(CustomerId $customerId, array $items): self
    {
        $total = Money::create(0, 'USD');
        foreach ($items as $item) {
            $total = $total->add($item->getTotal());
        }
        return new self(
            Uuid::generate(),
            $customerId,
            $items,
            OrderStatus::PENDING,
            Money::create(0, 'USD'),
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
    }

    public function getOrder(): array
    {
        return [
            'id' => $this->id->getValue(),
            'customerId' => $this->customerId->getValue(),
            'items' => $this->items,
            'status' => $this->status->getValue(),
            'total' => $this->total->getAmount(),
            'createdAt' => $this->createdAt->format(self::DATE_FORMAT),
            'updatedAt' => $this->updatedAt->format(self::DATE_FORMAT),
            'confirmedAt' => $this->confirmedAt?->format(self::DATE_FORMAT),
            'cancelledAt' => $this->cancelledAt?->format(self::DATE_FORMAT),
        ];
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }
    
    public function getItems(): array
    {
        return $this->items;
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

    public function cancel()
    {
        $this->status = OrderStatus::CANCELLED;
    }

    public function confirm()
    {
        $this->status = OrderStatus::CONFIRMED;
        $this->confirmedAt = new DateTimeImmutable();
    }

    public function addItem(OrderItem $item)
    {
        $this->items[] = $item;
    }
}
