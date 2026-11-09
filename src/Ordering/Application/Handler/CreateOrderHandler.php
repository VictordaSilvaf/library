<?php

declare(strict_types=1);

namespace App\Ordering\Application\Handler;

use App\Ordering\Application\Command\CreateOrder;
use App\Ordering\Application\DTO\OrderOutput;
use App\Ordering\Application\Event\EventDispatcher;
use App\Ordering\Domain\Aggregate\Order;
use App\Ordering\Domain\Repository\OrderRepository;

class CreateOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EventDispatcher $events,
    ) {
    }

    public function handle(CreateOrder $command): OrderOutput
    {
        $order = Order::create($command->getCustomerId(), $command->getProducts());
        $this->orders->save($order);

        foreach ($order->releaseEvents() as $event) {
            $this->events->dispatch($event);
        }

        return OrderOutput::fromOrder($order);
    }
}
