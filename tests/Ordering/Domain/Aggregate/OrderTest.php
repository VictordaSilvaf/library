<?php

declare(strict_types=1);

namespace Tests\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\ValueObject\Book;
use App\Ordering\Domain\ValueObject\BookCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Money;
use DomainException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function testCreateStartsPendingWithCustomerBooksAndTotal(): void
    {
        $customerId = CustomerId::random();
        $books = new BookCollection([
            $this->book('Domain-Driven Design', '40'),
            $this->book('Implementing Domain-Driven Design', '50'),
        ]);

        $order = Order::create($customerId, $books);

        $this->assertSame($customerId->getValue(), $order->getCustomerId()->getValue());
        $this->assertNotEmpty($order->getId()->getValue());
        $this->assertTrue($order->getStatus()->isPending());
        $this->assertSame('90', $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertCount(2, $order->getBooks()->getBooks());
        $this->assertNull($order->getConfirmedAt());
        $this->assertNull($order->getCancelledAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getUpdatedAt());
    }

    public function testCreateWithOneBookUsesItsPriceAsTotal(): void
    {
        $order = Order::create(CustomerId::random(), new BookCollection([
            $this->book('Clean Architecture', '30'),
        ]));

        $this->assertSame('30', $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testCreateWithEmptyCollectionHasZeroTotal(): void
    {
        $order = $this->emptyOrder();

        $this->assertTrue($order->getBooks()->isEmpty());
        $this->assertSame('0', $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertTrue($order->getStatus()->isPending());
    }

    public function testConfirmMarksPendingOrderAsConfirmed(): void
    {
        $order = $this->orderWithBooks();
        $updatedAt = $order->getUpdatedAt();

        $order->confirm();

        $this->assertTrue($order->getStatus()->isConfirmed());
        $this->assertNotNull($order->getConfirmedAt());
        $this->assertNull($order->getCancelledAt());
        $this->assertGreaterThanOrEqual($updatedAt, $order->getUpdatedAt());
    }

    public function testConfirmRejectsEmptyOrder(): void
    {
        $order = $this->emptyOrder();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order has no books');

        $order->confirm();
    }

    public function testConfirmRejectsConfirmedOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->confirm();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->confirm();
    }

    public function testConfirmRejectsCancelledOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->cancel();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->confirm();
    }

    public function testCancelMarksPendingOrderAsCancelled(): void
    {
        $order = $this->orderWithBooks();
        $updatedAt = $order->getUpdatedAt();

        $order->cancel();

        $this->assertTrue($order->getStatus()->isCancelled());
        $this->assertNotNull($order->getCancelledAt());
        $this->assertNull($order->getConfirmedAt());
        $this->assertGreaterThanOrEqual($updatedAt, $order->getUpdatedAt());
    }

    public function testCancelRejectsConfirmedOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->confirm();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testCancelRejectsCancelledOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->cancel();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testAddBookAppendsTheBookToAPendingOrder(): void
    {
        $order = $this->emptyOrder();
        $book = $this->book('Refactoring', '45');

        $order->addBook($book);

        $books = $order->getBooks()->getBooks();
        $this->assertCount(1, $books);
        $this->assertSame($book, $books[0]);
        $this->assertSame('Refactoring', $books[0]->title);
    }

    public function testGetOrderReturnsAnotherInstanceWithTheSameState(): void
    {
        $order = $this->orderWithBooks();
        $order->confirm();

        $copy = $order->getOrder();

        $this->assertNotSame($order, $copy);
        $this->assertSame($order->getId()->getValue(), $copy->getId()->getValue());
        $this->assertSame($order->getCustomerId()->getValue(), $copy->getCustomerId()->getValue());
        $this->assertTrue($copy->getStatus()->isConfirmed());
        $this->assertSame($order->getTotal()->getAmount(), $copy->getTotal()->getAmount());
        $this->assertSame($order->getTotal()->getCurrency(), $copy->getTotal()->getCurrency());
        $this->assertEquals($order->getCreatedAt(), $copy->getCreatedAt());
        $this->assertEquals($order->getUpdatedAt(), $copy->getUpdatedAt());
        $this->assertEquals($order->getConfirmedAt(), $copy->getConfirmedAt());
        $this->assertNull($copy->getCancelledAt());
        $this->assertCount(1, $copy->getBooks()->getBooks());
    }

    private function emptyOrder(): Order
    {
        return Order::create(CustomerId::random(), new BookCollection([]));
    }

    private function orderWithBooks(): Order
    {
        return Order::create(CustomerId::random(), new BookCollection([
            $this->book('Domain-Driven Design', '40'),
        ]));
    }

    private function book(string $title, string $amount): Book
    {
        return new PricedBook($title, 'Eric Evans', Money::create($amount, 'USD'));
    }
}
