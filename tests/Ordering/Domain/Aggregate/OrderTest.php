<?php

declare(strict_types=1);

namespace Tests\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateException;
use App\Ordering\Domain\ValueObject\Book;
use App\Ordering\Domain\ValueObject\BookCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Money;
use InvalidArgumentException;
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
        $this->assertSame(9000, $order->getTotal()->getAmount());
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

        $this->assertSame(3000, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testCreateUsesTheCurrencyOfTheBooks(): void
    {
        $order = Order::create(CustomerId::random(), new BookCollection([
            new Book('Dom Casmurro', 'Machado de Assis', Money::create('30', 'BRL')),
            new Book('Memórias Póstumas', 'Machado de Assis', Money::create('20', 'BRL')),
        ]));

        $this->assertSame(5000, $order->getTotal()->getAmount());
        $this->assertSame('BRL', $order->getTotal()->getCurrency());
    }

    public function testCreateRejectsMixedCurrencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currencies must be the same');

        Order::create(CustomerId::random(), new BookCollection([
            $this->book('Domain-Driven Design', '40'),
            new Book('Dom Casmurro', 'Machado de Assis', Money::create('30', 'BRL')),
        ]));
    }

    public function testCreateRejectsEmptyCollection(): void
    {
        $this->expectException(EmptyOrderException::class);
        $this->expectExceptionMessage('Books are required');

        Order::create(CustomerId::random(), new BookCollection([]));
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

    public function testConfirmRejectsConfirmedOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->confirm();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->confirm();
    }

    public function testConfirmRejectsCancelledOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->cancel();

        $this->expectException(InvalidOrderStateException::class);
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

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testCancelRejectsCancelledOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->cancel();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testGetBooksReturnsACopy(): void
    {
        $order = $this->orderWithBooks();

        $order->getBooks()->addBook($this->book('Refactoring', '45'));

        $this->assertCount(1, $order->getBooks()->getBooks());
    }

    public function testCreateKeepsItsOwnBookCollection(): void
    {
        $books = new BookCollection([
            $this->book('Domain-Driven Design', '40'),
        ]);
        $order = Order::create(CustomerId::random(), $books);

        $books->addBook($this->book('Refactoring', '45'));

        $this->assertCount(1, $order->getBooks()->getBooks());
    }

    public function testAddBookAppendsTheBookToAPendingOrder(): void
    {
        $order = $this->orderWithBooks();
        $book = $this->book('Refactoring', '45');

        $order->addBook($book);

        $books = $order->getBooks()->getBooks();
        $this->assertCount(2, $books);
        $this->assertSame($book, $books[1]);
        $this->assertSame('Refactoring', $books[1]->title);
        $this->assertSame(8500, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testAddBookRejectsADifferentCurrency(): void
    {
        $order = $this->orderWithBooks();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currencies must be the same');

        $order->addBook(new Book(
            'Refactoring',
            'Martin Fowler',
            Money::create('45', 'BRL'),
        ));
    }

    public function testAddBookRejectsAConfirmedOrder(): void
    {
        $order = $this->orderWithBooks();
        $order->confirm();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->addBook($this->book('Refactoring', '45'));
    }

    public function testCreateReleasesOrderCreated(): void
    {
        $customerId = CustomerId::random();
        $order = Order::create($customerId, new BookCollection([
            $this->book('Domain-Driven Design', '40'),
        ]));

        $events = $order->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCreated::class, $events[0]);
        $this->assertSame($order->getId()->getValue(), $events[0]->orderId->getValue());
        $this->assertSame($customerId->getValue(), $events[0]->customerId->getValue());
        $this->assertSame(4000, $events[0]->total->getAmount());
        $this->assertCount(0, $order->releaseEvents());
    }

    public function testConfirmReleasesOrderConfirmed(): void
    {
        $order = $this->orderWithBooks();
        $order->releaseEvents();

        $order->confirm();
        $events = $order->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderConfirmed::class, $events[0]);
        $this->assertSame($order->getId()->getValue(), $events[0]->orderId->getValue());
        $this->assertSame(4000, $events[0]->total->getAmount());
    }

    public function testCancelReleasesOrderCancelled(): void
    {
        $order = $this->orderWithBooks();
        $order->releaseEvents();

        $order->cancel();
        $events = $order->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCancelled::class, $events[0]);
        $this->assertSame($order->getId()->getValue(), $events[0]->orderId->getValue());
        $this->assertSame(4000, $events[0]->total->getAmount());
    }

    private function orderWithBooks(): Order
    {
        return Order::create(CustomerId::random(), new BookCollection([
            $this->book('Domain-Driven Design', '40'),
        ]));
    }

    private function book(string $title, string $amount): Book
    {
        return new Book($title, 'Eric Evans', Money::create($amount, 'USD'));
    }
}
