<?php

declare(strict_types=1);

namespace Tests\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Application\Command\ConfirmOrder;
use App\Ordering\Application\Command\CreateOrder;
use App\Ordering\Application\Handler\CancelOrderHandler;
use App\Ordering\Application\Handler\ConfirmOrderHandler;
use App\Ordering\Application\Handler\CreateOrderHandler;
use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Shared\Domain\ValueObject\Money;
use App\Ordering\Domain\ValueObject\OrderId;
use Tests\Ordering\Support\InMemoryOrderRepository;
use Tests\TestCase;

class OrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private CreateOrderHandler $create;

    private ConfirmOrderHandler $confirm;

    private CancelOrderHandler $cancel;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->create = new CreateOrderHandler($this->orders);
        $this->confirm = new ConfirmOrderHandler($this->orders);
        $this->cancel = new CancelOrderHandler($this->orders);
    }

    public function testCreateStoresAPendingOrder(): void
    {
        $output = $this->create->handle($this->command());
        $stored = $this->orders->findById(new OrderId($output->getId()));

        $this->assertNotNull($stored);
        $this->assertTrue($stored->getStatus()->isPending());
        $this->assertSame([], $stored->releaseEvents());
    }

    public function testConfirmStoresTheConfirmedOrder(): void
    {
        $created = $this->create->handle($this->command());

        $this->confirm->handle(new ConfirmOrder(new OrderId($created->getId())));
        $stored = $this->orders->findById(new OrderId($created->getId()));

        $this->assertNotNull($stored);
        $this->assertTrue($stored->getStatus()->isConfirmed());
    }

    public function testCancelStoresTheCancelledOrder(): void
    {
        $created = $this->create->handle($this->command());

        $this->cancel->handle(new CancelOrder(new OrderId($created->getId())));
        $stored = $this->orders->findById(new OrderId($created->getId()));

        $this->assertNotNull($stored);
        $this->assertTrue($stored->getStatus()->isCancelled());
    }

    public function testMissingOrderIsRejected(): void
    {
        $this->expectException(OrderNotFoundException::class);

        $this->confirm->handle(new ConfirmOrder(OrderId::random()));
    }

    private function command(): CreateOrder
    {
        return new CreateOrder(CustomerId::random(), new ProductCollection([
            new Product('Domain-Driven Design', 'Eric Evans', Money::create('40.00', 'USD')),
        ]));
    }
}
