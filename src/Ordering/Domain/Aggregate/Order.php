<?php

declare(strict_types=1);

namespace App\Ordering\Domain\Aggregate;

use App\Ordering\Domain\ValueObject\{
    Book,
    OrderId,
    CustomerId,
    OrderStatus,
    Money,
    BookCollection
};
use DateTimeImmutable;
use DomainException;

class Order
{
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param BookCollection $books
     */
    private function __construct(
        private readonly OrderId $id,
        private readonly CustomerId $customerId,
        private readonly BookCollection $books,
        private OrderStatus $status,
        private Money $total,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $confirmedAt = null,
        private ?DateTimeImmutable $cancelledAt = null,
    ) {}

    public static function create(CustomerId $customerId, BookCollection $books): self
    {
        $total = Money::create('0', 'USD');

        foreach ($books->getBooks() as $book) {
            $total = $total->add($book->getPrice());
        }

        return new self(
            OrderId::random(),
            $customerId,
            $books,
            OrderStatus::PENDING,
            $total,
            new DateTimeImmutable(),
            new DateTimeImmutable(),
        );
    }

    public function getOrder(): Order
    {
        return new Order(
            $this->id,
            $this->customerId,
            $this->books,
            $this->status,
            $this->total,
            $this->createdAt,
            $this->updatedAt,
            $this->confirmedAt,
            $this->cancelledAt,
        );
    }

    public function getId(): OrderId
    {
        return $this->id;
    }

    public function getCustomerId(): CustomerId
    {
        return $this->customerId;
    }
    
    public function getBooks(): BookCollection
    {
        return $this->books;
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

    public function confirm()
    {
        $this->validateConfirm();

        $this->status = OrderStatus::CONFIRMED;
        $this->confirmedAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function cancel()
    {
        $this->validateCancel();

        $this->status = OrderStatus::CANCELLED;
        $this->cancelledAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function addBook(Book $book)
    {
        $this->books->addBook($book);
    }

    private function validateConfirm()
    {
        if (!$this->status->isPending()) {
            throw new DomainException('Order is not pending');
        }

        if ($this->confirmedAt !== null) {
            throw new DomainException('Order is already confirmed');
        }
        
        if ($this->cancelledAt !== null) {
            throw new DomainException('Order is cancelled');
        }

        if ($this->books->isEmpty()) {
            throw new DomainException('Order has no books');
        }
    }

    private function validateCancel()
    {
        if ($this->status !== OrderStatus::PENDING) {
            throw new DomainException('Order is not pending');
        }
    }
}
