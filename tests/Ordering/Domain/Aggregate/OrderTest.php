<?php

declare(strict_types=1);

namespace Tests\Ordering\Domain\Aggregate;

use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Exception\EmptyOrderException;
use App\Ordering\Domain\Exception\InvalidOrderStateException;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Money;
use InvalidArgumentException;
use Tests\TestCase;

class OrderTest extends TestCase
{
    public function testCreateStartsPendingWithCustomerProductsAndTotal(): void
    {
        $customerId = CustomerId::random();
        $products = new ProductCollection([
            $this->product('Domain-Driven Design', '40'),
            $this->product('Implementing Domain-Driven Design', '50'),
        ]);

        $order = Order::create($customerId, $products);

        $this->assertSame($customerId->getValue(), $order->getCustomerId()->getValue());
        $this->assertNotEmpty($order->getId()->getValue());
        $this->assertTrue($order->getStatus()->isPending());
        $this->assertSame(9000, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
        $this->assertCount(2, $order->getProducts()->getProducts());
        $this->assertNull($order->getConfirmedAt());
        $this->assertNull($order->getCancelledAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getUpdatedAt());
    }

    public function testCreateWithOneProductUsesItsPriceAsTotal(): void
    {
        $order = Order::create(CustomerId::random(), new ProductCollection([
            $this->product('Clean Architecture', '30'),
        ]));

        $this->assertSame(3000, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testCreateUsesTheCurrencyOfTheProducts(): void
    {
        $order = Order::create(CustomerId::random(), new ProductCollection([
            new Product('Dom Casmurro', 'Machado de Assis', Money::create('30', 'BRL')),
            new Product('Memórias Póstumas', 'Machado de Assis', Money::create('20', 'BRL')),
        ]));

        $this->assertSame(5000, $order->getTotal()->getAmount());
        $this->assertSame('BRL', $order->getTotal()->getCurrency());
    }

    public function testCreateRejectsMixedCurrencies(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currencies must be the same');

        Order::create(CustomerId::random(), new ProductCollection([
            $this->product('Domain-Driven Design', '40'),
            new Product('Dom Casmurro', 'Machado de Assis', Money::create('30', 'BRL')),
        ]));
    }

    public function testCreateRejectsEmptyCollection(): void
    {
        $this->expectException(EmptyOrderException::class);
        $this->expectExceptionMessage('Products are required');

        Order::create(CustomerId::random(), new ProductCollection([]));
    }

    public function testConfirmMarksPendingOrderAsConfirmed(): void
    {
        $order = $this->orderWithProducts();
        $updatedAt = $order->getUpdatedAt();

        $order->confirm();

        $this->assertTrue($order->getStatus()->isConfirmed());
        $this->assertNotNull($order->getConfirmedAt());
        $this->assertNull($order->getCancelledAt());
        $this->assertGreaterThanOrEqual($updatedAt, $order->getUpdatedAt());
    }

    public function testConfirmRejectsConfirmedOrder(): void
    {
        $order = $this->orderWithProducts();
        $order->confirm();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->confirm();
    }

    public function testConfirmRejectsCancelledOrder(): void
    {
        $order = $this->orderWithProducts();
        $order->cancel();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->confirm();
    }

    public function testCancelMarksPendingOrderAsCancelled(): void
    {
        $order = $this->orderWithProducts();
        $updatedAt = $order->getUpdatedAt();

        $order->cancel();

        $this->assertTrue($order->getStatus()->isCancelled());
        $this->assertNotNull($order->getCancelledAt());
        $this->assertNull($order->getConfirmedAt());
        $this->assertGreaterThanOrEqual($updatedAt, $order->getUpdatedAt());
    }

    public function testCancelRejectsConfirmedOrder(): void
    {
        $order = $this->orderWithProducts();
        $order->confirm();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testCancelRejectsCancelledOrder(): void
    {
        $order = $this->orderWithProducts();
        $order->cancel();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->cancel();
    }

    public function testGetProductsReturnsACopy(): void
    {
        $order = $this->orderWithProducts();

        $order->getProducts()->addProduct($this->product('Refactoring', '45'));

        $this->assertCount(1, $order->getProducts()->getProducts());
    }

    public function testCreateKeepsItsOwnProductCollection(): void
    {
        $products = new ProductCollection([
            $this->product('Domain-Driven Design', '40'),
        ]);
        $order = Order::create(CustomerId::random(), $products);

        $products->addProduct($this->product('Refactoring', '45'));

        $this->assertCount(1, $order->getProducts()->getProducts());
    }

    public function testAddProductAppendsTheProductToAPendingOrder(): void
    {
        $order = $this->orderWithProducts();
        $product = $this->product('Refactoring', '45');

        $order->addProduct($product);

        $products = $order->getProducts()->getProducts();
        $this->assertCount(2, $products);
        $this->assertSame($product, $products[1]);
        $this->assertSame('Refactoring', $products[1]->title);
        $this->assertSame(8500, $order->getTotal()->getAmount());
        $this->assertSame('USD', $order->getTotal()->getCurrency());
    }

    public function testAddProductRejectsADifferentCurrency(): void
    {
        $order = $this->orderWithProducts();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currencies must be the same');

        $order->addProduct(new Product(
            'Refactoring',
            'Martin Fowler',
            Money::create('45', 'BRL'),
        ));
    }

    public function testAddProductRejectsAConfirmedOrder(): void
    {
        $order = $this->orderWithProducts();
        $order->confirm();

        $this->expectException(InvalidOrderStateException::class);
        $this->expectExceptionMessage('Order is not pending');

        $order->addProduct($this->product('Refactoring', '45'));
    }

    public function testCreateReleasesOrderCreated(): void
    {
        $customerId = CustomerId::random();
        $order = Order::create($customerId, new ProductCollection([
            $this->product('Domain-Driven Design', '40'),
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
        $order = $this->orderWithProducts();
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
        $order = $this->orderWithProducts();
        $order->releaseEvents();

        $order->cancel();
        $events = $order->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(OrderCancelled::class, $events[0]);
        $this->assertSame($order->getId()->getValue(), $events[0]->orderId->getValue());
        $this->assertSame(4000, $events[0]->total->getAmount());
    }

    private function orderWithProducts(): Order
    {
        return Order::create(CustomerId::random(), new ProductCollection([
            $this->product('Domain-Driven Design', '40'),
        ]));
    }

    private function product(string $title, string $amount): Product
    {
        return new Product($title, 'Eric Evans', Money::create($amount, 'USD'));
    }
}
