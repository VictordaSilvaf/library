<?php

declare(strict_types=1);

namespace Tests\Ordering\Application\Handler;

use App\Ordering\Application\Command\CancelOrder;
use App\Ordering\Application\Command\ConfirmOrder;
use App\Ordering\Application\Command\CreateOrder;
use App\Ordering\Application\Handler\CancelOrderHandler;
use App\Ordering\Application\Handler\ConfirmOrderHandler;
use App\Ordering\Application\Handler\CreateOrderHandler;
use App\Ordering\Domain\Event\OrderCancelled;
use App\Ordering\Domain\Event\OrderConfirmed;
use App\Ordering\Domain\Event\OrderCreated;
use App\Ordering\Domain\Exception\OrderNotFoundException;
use App\Ordering\Domain\ValueObject\Product;
use App\Ordering\Domain\ValueObject\ProductCollection;
use App\Ordering\Domain\ValueObject\CustomerId;
use App\Ordering\Domain\ValueObject\Money;
use App\Ordering\Domain\ValueObject\OrderId;
use App\Ordering\Infrastructure\Event\SynchronousEventDispatcher;
use Tests\Ordering\Support\InMemoryOrderRepository;
use Tests\Ordering\Support\RecordingListener;
use Tests\TestCase;

class OrderHandlerTest extends TestCase
{
    private InMemoryOrderRepository $orders;

    private RecordingListener $listener;

    private CreateOrderHandler $create;

    private ConfirmOrderHandler $confirm;

    private CancelOrderHandler $cancel;

    protected function setUp(): void
    {
        $this->orders = new InMemoryOrderRepository();
        $this->listener = new RecordingListener();
        $dispatcher = new SynchronousEventDispatcher([$this->listener]);
        $this->create = new CreateOrderHandler($this->orders, $dispatcher);
        $this->confirm = new ConfirmOrderHandler($this->orders, $dispatcher);
        $this->cancel = new CancelOrderHandler($this->orders, $dispatcher);
    }

    public function testCreateDispatchesOrderCreated(): void
    {
        $output = $this->create->handle($this->command());

        $this->assertCount(1, $this->listener->events);
        $this->assertInstanceOf(OrderCreated::class, $this->listener->events[0]);
        $this->assertSame($output->getId(), $this->listener->events[0]->orderId->getValue());
    }

    public function testConfirmDispatchesOrderConfirmed(): void
    {
        $created = $this->create->handle($this->command());
        $this->listener->events = [];

        $this->confirm->handle(new ConfirmOrder(new OrderId($created->getId())));

        $this->assertCount(1, $this->listener->events);
        $this->assertInstanceOf(OrderConfirmed::class, $this->listener->events[0]);
        $this->assertSame($created->getId(), $this->listener->events[0]->orderId->getValue());
    }

    public function testCancelDispatchesOrderCancelled(): void
    {
        $created = $this->create->handle($this->command());
        $this->listener->events = [];

        $this->cancel->handle(new CancelOrder(new OrderId($created->getId())));

        $this->assertCount(1, $this->listener->events);
        $this->assertInstanceOf(OrderCancelled::class, $this->listener->events[0]);
        $this->assertSame($created->getId(), $this->listener->events[0]->orderId->getValue());
    }

    public function testMissingOrderDoesNotDispatchAnEvent(): void
    {
        try {
            $this->confirm->handle(new ConfirmOrder(OrderId::random()));
            $this->fail('A missing order must be rejected');
        } catch (OrderNotFoundException) {
            $this->assertSame([], $this->listener->events);
        }
    }

    private function command(): CreateOrder
    {
        return new CreateOrder(CustomerId::random(), new ProductCollection([
            new Product('Domain-Driven Design', 'Eric Evans', Money::create('40.00', 'USD')),
        ]));
    }
}
